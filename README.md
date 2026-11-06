# Bridging SIMRS Khanza → E-Klaim iDRG (PHP native)

Membaca database Khanza (`sik`) dan mengirim ke Web Service E-Klaim iDRG, dengan halaman klaim yang meniru langkah aplikasi E-Klaim,
plus preview & unduh PDF berkas klaim dan lampiran berkas digital tambahan.

## Pasang (3 langkah)
1. Salin folder ke webroot, mis. `C:\xampp\htdocs\idrg\` (`.htaccess` memblokir `config/`, `src/`, `views/`; hanya `public/` yang terbuka).
2. Buka `http://IP-SERVER/idrg/public/setup.php`, isi form (DB Khanza, URL E-Klaim, NIK coder, kunci AES Khanza).
   Wizard memeriksa persyaratan, menguji dekripsi akun Khanza, membuat tabel `idrg_*`, menulis `config/config.php`, lalu mengunci diri.
3. Buka `http://IP-SERVER/idrg/public/` dan login.

Uji dulu dengan *Mode debug* aktif. Untuk produksi, matikan debug dan isi kunci enkripsi 64 hex dari E-Klaim.

## Login & hak akses
Login memakai **akun SIMRS Khanza** .
- Batasi akun tertentu: `auth.allowed_users = ['NIK1', 'NIK2']` (kosong = semua akun Khanza).
- Batas tidak aktif `auth.session_minutes` (default 120); 5 kali gagal per IP dalam 10 menit memblokir sementara.
- `eklaim.coder_dari_login = true` memakai **No. KTP pegawai (`pegawai.no_ktp`) yang sedang login** sebagai NIK coder E-Klaim.
- Logo RS: `public/assets/logo.png` .

## Navigasi (sidebar)
**Beranda** (ringkasan dua modul) · **Rawat Jalan** (Klaim RJ + submenu Belum diproses / Dalam proses / Final / Terkirim) ·
**Rawat Inap** (struktur sama) · **Log E-Klaim**. Lencana kuning = jumlah SEP yang belum diproses.
**Filter status bayar** (`reg_periksa.status_bayar`) ada di tiap daftar dan di beranda: *Sudah bayar* (**default**), *Belum bayar*, atau *Semua*;
kolom *Bayar* menampilkan statusnya, dan lencana/ringkasan beranda mengikuti filter yang sama. Modul memfilter
`status_lanjut` Ralan/Ranap; kolom daftar menyesuaikan (RJ: poli; RI: tanggal pulang).

## Berkas klaim (format form asli)
Tombol **Berkas** (atau tab *Berkas klaim & lampiran*) menampilkan satu halaman A4 per dokumen, dengan kop surat (logo dari
`setting.logo`), identitas pasien, dan kolom tanda tangan:
- **Ringkasan** (sampul): KPI, diagnosa/prosedur ICD, kamar inap.
- **SEP**: meniru cetakan SEP BPJS (No.SEP, Tgl.SEP, No.Kartu + MR, peserta, COB, jenis & kelas rawat, penjamin, faskes perujuk,
  diagnosa awal, catatan, persetujuan pasien, "Cetakan ke 1"). SEP internal juga terbaca.
- **Resume medis** (`resume_pasien_ranap` / `resume_pasien`): ruang, DPJP, narasi, tabel diagnosa & prosedur dengan kode ICD,
  kondisi keluar, tindak lanjut.
- **Asesmen awal medis** (`penilaian_medis_ralan/ranap/igd` + tabel spesialis) dan **asesmen awal keperawatan**
  (`penilaian_awal_keperawatan_*`): dikelompokkan seperti form (anamnesis, tanda vital, pemeriksaan fisik, risiko jatuh, gizi, nyeri…).
  Tabel/kolom yang tidak dikenal tetap tampil di "Data Lainnya", jadi tidak ada isi form yang hilang.
- **CPPT** (SOAP terintegrasi: tanggal, PPA, SOAP, instruksi, paraf), tindakan, **laporan operasi**, **hasil laboratorium**
  per pemeriksaan (nilai rujukan menurut JK/umur, penanda H/L), **hasil radiologi** + gambar, obat/BHP & resep pulang,
  **rincian biaya**, berkas digital RS, lampiran tambahan, status klaim (tidak ikut cetak secara default).
- Dokumen yang tabelnya tidak ada atau kosong otomatis tidak muncul. Form tambahan RS: `berkas.form_tambahan` di config.
- Tiap dokumen bisa dicentang/dilepas; *Hemat tinta*; **Unduh PDF** = dialog cetak browser (*Simpan sebagai PDF*).
- **Lampiran tambahan**: seret/pilih file (JPG, PNG, GIF, WEBP, PDF), disimpan di `storage/uploads/` (di luar webroot, nama acak,
  isi file divalidasi, hanya terbuka setelah login). PDF tidak bisa digabung ke PDF cetak browser: tampil di layar dan bisa
  diunduh terpisah.

## Halaman klaim (langkah sama dengan E-Klaim)
Tombol **Proses** pada daftar membuka halaman *KLAIM* yang mengikuti aplikasi E-Klaim, dengan data awal dari Khanza
(boleh diubah coder sebelum disimpan):

1. **Header klaim**: tanggal masuk/pulang, jaminan, No. SEP, tipe RJ/RI, CBG, status, petugas.
2. **Data klaim**: jenis rawat, kelas hak, tanggal rawat, umur, cara masuk, LOS, berat lahir, ADL, cara pulang, DPJP,
   jenis tarif, pasien TB, **18 komponen tarif RS** (tanda `?` menunjukkan sumber billing Khanza), data klinis
   (tekanan darah, ICU, naik kelas). Pernyataan kebenaran tarif wajib dicentang → **Simpan Data Klaim**
   (`new_claim` + `set_claim_data`).
3. **Tab iDRG**: daftar diagnosa ICD-10 & prosedur ICD-9-CM (urut/seret, hapus, tambah lewat pencarian master ICD Khanza,
   jumlah tindakan) → **Simpan & Grouping iDRG** → tabel *Hasil Grouping iDRG* (MDC, DRG, cost weight, NBR, total) →
   top-up (Stage 2) bila ada → **Final iDRG** / **Edit Ulang iDRG**. MDC 36 memblokir Final.
4. **Tab INACBG**: **Import dari iDRG** → daftar INACBG (kode non-IM dari Khanza) → **Simpan & Grouping INACBG** →
   tabel *Hasil Grouping INACBG* (Group, Sub Acute, Chronic, Special CMG, Total Klaim) → Special CMG (Stage 2) →
   **Final INACBG** / **Edit Ulang INACBG**.
5. **Status Klaim** (Final, DC Kemkes, DC BPJS) dan bar bawah: **Cetak Klaim**, **Kirim Klaim Online**,
   **Final Klaim**, **Edit Ulang Klaim**.

Hasil grouping tersimpan, jadi halaman dapat dibuka kembali kapan saja. Perubahan diagnosa/prosedur di layar ini hanya
dikirim ke E-Klaim dan **tidak mengubah data Khanza**.


## Kepatuhan checkpoint uji coba iDRG (25 kriteria)
Final iDRG tersembunyi untuk MDC 36 (#7/#8); form & coding iDRG read-only setelah iDRG final (#10); Edit Ulang iDRG menggantikan
Final iDRG (#11); INACBG baru tampil setelah iDRG final dan wajib import dulu (#12/#13); kode IM ditandai "tidak berlaku" (#14);
Final INACBG hanya untuk hasil valid (#16/#17); Final Klaim hanya setelah INACBG final, Kirim & Cetak hanya setelah klaim final
(#20-#23); Edit Ulang iDRG/INACBG hilang setelah klaim final (#24); multiplicity & setting prosedur iDRG (#4, kode berulang =
setting berbeda); seluruh request/respons tersimpan di `idrg_log` dan `idrg_step` (#25).

