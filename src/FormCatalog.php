<?php
declare(strict_types=1);
namespace Idrg;

/** Katalog form dokumen klinis Khanza: tabel, judul, pengelompokan kolom, dan label. */
final class FormCatalog
{
    /** [grup dokumen] => daftar [tabel, judul, jenis, modul('ralan'|'ranap'|'*')] — tabel yang tidak ada diabaikan. */
    public const FORMS = [
        'resume' => [
            ['resume_pasien_ranap', 'RESUME MEDIS PASIEN RAWAT INAP', 'resume', 'ranap'],
            ['resume_pasien', 'RESUME MEDIS PASIEN RAWAT JALAN', 'resume', 'ralan'],
        ],
        'medis' => [
            ['penilaian_medis_igd', 'ASESMEN AWAL MEDIS IGD', 'medis', '*'],
            ['penilaian_medis_ralan', 'ASESMEN AWAL MEDIS RAWAT JALAN', 'medis', 'ralan'],
            ['penilaian_medis_ralan_anak', 'ASESMEN AWAL MEDIS RAWAT JALAN - ANAK', 'generic', 'ralan'],
            ['penilaian_medis_ralan_bedah', 'ASESMEN AWAL MEDIS RAWAT JALAN - BEDAH', 'generic', 'ralan'],
            ['penilaian_medis_ralan_kandungan', 'ASESMEN AWAL MEDIS RAWAT JALAN - KEBIDANAN & KANDUNGAN', 'generic', 'ralan'],
            ['penilaian_medis_ralan_mata', 'ASESMEN AWAL MEDIS RAWAT JALAN - MATA', 'generic', 'ralan'],
            ['penilaian_medis_ralan_tht', 'ASESMEN AWAL MEDIS RAWAT JALAN - THT', 'generic', 'ralan'],
            ['penilaian_medis_ralan_psikiatrik', 'ASESMEN AWAL MEDIS RAWAT JALAN - PSIKIATRIK', 'generic', 'ralan'],
            ['penilaian_medis_ralan_neurologi', 'ASESMEN AWAL MEDIS RAWAT JALAN - NEUROLOGI', 'generic', 'ralan'],
            ['penilaian_medis_ralan_penyakit_dalam', 'ASESMEN AWAL MEDIS RAWAT JALAN - PENYAKIT DALAM', 'generic', 'ralan'],
            ['penilaian_medis_ralan_orthopedi', 'ASESMEN AWAL MEDIS RAWAT JALAN - ORTHOPEDI', 'generic', 'ralan'],
            ['penilaian_medis_ralan_kulitdankelamin', 'ASESMEN AWAL MEDIS RAWAT JALAN - KULIT & KELAMIN', 'generic', 'ralan'],
            ['penilaian_medis_ralan_urologi', 'ASESMEN AWAL MEDIS RAWAT JALAN - UROLOGI', 'generic', 'ralan'],
            ['penilaian_medis_ralan_geriatri', 'ASESMEN AWAL MEDIS RAWAT JALAN - GERIATRI', 'generic', 'ralan'],
            ['penilaian_medis_ralan_rehab_medik', 'ASESMEN AWAL MEDIS RAWAT JALAN - REHAB MEDIK', 'generic', 'ralan'],
            ['penilaian_medis_ranap', 'ASESMEN AWAL MEDIS RAWAT INAP', 'medis', 'ranap'],
            ['penilaian_medis_ranap_kandungan', 'ASESMEN AWAL MEDIS RAWAT INAP - KEBIDANAN & KANDUNGAN', 'generic', 'ranap'],
            ['penilaian_medis_ranap_neonatus', 'ASESMEN AWAL MEDIS RAWAT INAP - NEONATUS', 'generic', 'ranap'],
        ],
        'perawat' => [
            ['penilaian_awal_keperawatan_igd', 'ASESMEN AWAL KEPERAWATAN IGD', 'generic', '*'],
            ['penilaian_awal_keperawatan_ralan', 'ASESMEN AWAL KEPERAWATAN RAWAT JALAN', 'perawat', 'ralan'],
            ['penilaian_awal_keperawatan_ranap', 'ASESMEN AWAL KEPERAWATAN RAWAT INAP', 'generic', 'ranap'],
            ['penilaian_awal_keperawatan_kebidanan', 'ASESMEN AWAL KEPERAWATAN KEBIDANAN', 'generic', '*'],
        ],
        'operasi' => [
            ['laporan_operasi', 'LAPORAN OPERASI', 'generic', '*'],
        ],
    ];

    /** Kolom teknis yang tidak ditampilkan pada form. */
    public const EXCLUDE = ['no_rawat', 'kd_dokter', 'nip', 'kd_petugas', 'kd_dokter_pj', 'tanggal', 'jam', 'password', 'id', 'no_sep', 'kd_penyakit_utama'];

    public const LABELS = [
        'anamnesis' => 'Anamnesis', 'hubungan' => 'Hubungan', 'keluhan_utama' => 'Keluhan Utama', 'rps' => 'Riwayat Penyakit Sekarang',
        'rpd' => 'Riwayat Penyakit Dahulu', 'rpk' => 'Riwayat Penyakit Keluarga', 'rpo' => 'Riwayat Penggunaan Obat', 'alergi' => 'Riwayat Alergi',
        'keadaan' => 'Keadaan Umum', 'kesadaran' => 'Kesadaran', 'gcs' => 'GCS', 'td' => 'TD', 'nadi' => 'Nadi', 'rr' => 'RR', 'suhu' => 'Suhu',
        'spo' => 'SpO2', 'bb' => 'BB', 'tb' => 'TB', 'bmi' => 'BMI', 'kepala' => 'Kepala', 'mata' => 'Mata', 'gigi' => 'Gigi & Mulut', 'tht' => 'THT',
        'leher' => 'Leher', 'thoraks' => 'Thoraks', 'abdomen' => 'Abdomen', 'genital' => 'Genital & Anus', 'ekstremitas' => 'Ekstremitas', 'kulit' => 'Kulit',
        'ket_fisik' => 'Keterangan Pemeriksaan Fisik', 'ket_lokalis' => 'Status Lokalis', 'penunjang' => 'Pemeriksaan Penunjang', 'ekg' => 'EKG',
        'rad' => 'Radiologi', 'lab' => 'Laboratorium', 'diagnosis' => 'Diagnosis / Asesmen', 'tata' => 'Tata Laksana / Terapi', 'edukasi' => 'Edukasi',
        'konsulrujuk' => 'Konsul / Rujuk', 'informasi' => 'Informasi Diperoleh Dari', 'alat_bantu' => 'Alat Bantu', 'ket_bantu' => 'Keterangan Alat Bantu',
        'prothesa' => 'Prothesa', 'ket_pro' => 'Keterangan Prothesa', 'adl' => 'Aktivitas Sehari-hari (ADL)', 'status_psiko' => 'Status Psikologis',
        'ket_psiko' => 'Keterangan Psikologis', 'hub_keluarga' => 'Hubungan dengan Keluarga', 'tinggal_dengan' => 'Tinggal Dengan', 'ket_tinggal' => 'Keterangan Tinggal',
        'ekonomi' => 'Status Ekonomi', 'ket_edukasi' => 'Keterangan Edukasi', 'berjalan_a' => 'Cara Berjalan Tidak Seimbang', 'berjalan_b' => 'Berjalan Dengan Alat Bantu',
        'berjalan_c' => 'Menopang Saat Duduk', 'hasil' => 'Hasil Skrining Jatuh', 'lapor' => 'Dilaporkan ke Dokter', 'ket_lapor' => 'Waktu Dilaporkan',
        'sg1' => 'Skrining Gizi 1', 'nilai1' => 'Nilai 1', 'sg2' => 'Skrining Gizi 2', 'nilai2' => 'Nilai 2', 'total_hasil' => 'Total Skor Gizi',
        'nyeri' => 'Nyeri', 'provokes' => 'Provokes', 'ket_provokes' => 'Ket. Provokes', 'quality' => 'Quality', 'ket_quality' => 'Ket. Quality', 'lokasi' => 'Lokasi',
        'menyebar' => 'Menyebar', 'skala_nyeri' => 'Skala Nyeri', 'durasi' => 'Durasi', 'nyeri_hilang' => 'Nyeri Hilang Bila', 'ket_nyeri' => 'Ket. Nyeri',
        'pada_dokter' => 'Lapor Dokter', 'ket_dokter' => 'Ket. Lapor Dokter', 'rencana' => 'Rencana Keperawatan',
        'diagnosa_awal' => 'Diagnosa Awal Masuk', 'alasan' => 'Alasan Masuk Rumah Sakit', 'pemeriksaan_fisik' => 'Pemeriksaan Fisik', 'jalannya_penyakit' => 'Jalannya Penyakit Selama Perawatan',
        'pemeriksaan_penunjang' => 'Pemeriksaan Penunjang Radiologi / Laboratorium', 'hasil_laborat' => 'Hasil Laboratorium Penting', 'tindakan_dan_operasi' => 'Tindakan / Operasi Selama Perawatan',
        'obat_di_rs' => 'Obat-obatan Selama di Rumah Sakit', 'diet' => 'Diet', 'lab_belum' => 'Hasil Laboratorium yang Belum Selesai', 'cara_keluar' => 'Cara Keluar',
        'ket_keluar' => 'Keterangan Cara Keluar', 'ket_keadaan' => 'Keterangan Keadaan', 'dilanjutkan' => 'Dilanjutkan', 'ket_dilanjutkan' => 'Keterangan Dilanjutkan',
        'kontrol' => 'Tanggal Kontrol', 'obat_pulang' => 'Obat Pulang', 'kondisi_pulang' => 'Kondisi Pulang',
        'diagnosa_preop' => 'Diagnosa Pre-Operasi', 'diagnosa_postop' => 'Diagnosa Post-Operasi', 'jaringan_dieksisi' => 'Jaringan yang Dieksisi',
        'permintaan_pa' => 'Permintaan Pemeriksaan PA', 'laporan_operasi' => 'Laporan Operasi',
    ];
    private const TOKENS = ['td' => 'TD', 'rr' => 'RR', 'gcs' => 'GCS', 'spo' => 'SpO2', 'spo2' => 'SpO2', 'bb' => 'BB', 'tb' => 'TB', 'bmi' => 'BMI', 'ket' => 'Ket.',
        'hub' => 'Hubungan', 'rpd' => 'RPD', 'rpk' => 'RPK', 'rpo' => 'RPO', 'rps' => 'RPS', 'ekg' => 'EKG', 'tht' => 'THT', 'ttv' => 'TTV', 'nm' => 'Nama',
        'tgl' => 'Tanggal', 'kd' => 'Kode', 'rs' => 'RS', 'icd' => 'ICD', 'ugd' => 'UGD', 'igd' => 'IGD', 'pa' => 'PA', 'dr' => 'Dr.'];

    /** Kelompok kolom per jenis form: [judul, [kolom ringkas (strip)], [kolom baris]] */
    public const GROUPS = [
        'medis' => [
            ['Anamnesis', [], ['anamnesis', 'hubungan', 'keluhan_utama', 'rps', 'rpd', 'rpk', 'rpo', 'alergi']],
            ['Pemeriksaan Fisik', ['keadaan', 'kesadaran', 'gcs', 'td', 'nadi', 'rr', 'suhu', 'spo', 'bb', 'tb'],
                ['kepala', 'mata', 'gigi', 'tht', 'leher', 'thoraks', 'abdomen', 'genital', 'ekstremitas', 'kulit', 'ket_fisik']],
            ['Status Lokalis', [], ['ket_lokalis']],
            ['Pemeriksaan Penunjang', [], ['penunjang', 'ekg', 'rad', 'lab']],
            ['Diagnosis / Asesmen', [], ['diagnosis']],
            ['Tata Laksana / Terapi', [], ['tata']],
            ['Edukasi', [], ['edukasi']],
            ['Konsul / Rujuk', [], ['konsulrujuk']],
        ],
        'perawat' => [
            ['Informasi & Tanda Vital', ['td', 'nadi', 'rr', 'suhu', 'gcs', 'bb', 'tb', 'bmi'], ['informasi']],
            ['Anamnesis', [], ['keluhan_utama', 'rpd', 'rpk', 'rpo', 'alergi']],
            ['Kebutuhan Fungsional', [], ['alat_bantu', 'ket_bantu', 'prothesa', 'ket_pro', 'adl']],
            ['Psikososial, Ekonomi & Edukasi', [], ['status_psiko', 'ket_psiko', 'hub_keluarga', 'tinggal_dengan', 'ket_tinggal', 'ekonomi', 'edukasi', 'ket_edukasi']],
            ['Risiko Jatuh', [], ['berjalan_a', 'berjalan_b', 'berjalan_c', 'hasil', 'lapor', 'ket_lapor']],
            ['Skrining Gizi', [], ['sg1', 'nilai1', 'sg2', 'nilai2', 'total_hasil']],
            ['Penilaian Nyeri', [], ['nyeri', 'provokes', 'ket_provokes', 'quality', 'ket_quality', 'lokasi', 'menyebar', 'skala_nyeri', 'durasi', 'nyeri_hilang', 'ket_nyeri', 'pada_dokter', 'ket_dokter']],
            ['Rencana Keperawatan', [], ['rencana']],
        ],
    ];
    public const STRIP_UNIT = ['td' => 'mmHg', 'nadi' => 'x/mnt', 'rr' => 'x/mnt', 'suhu' => '°C', 'spo' => '%', 'bb' => 'Kg', 'tb' => 'Cm'];

    public const RESUME_NARASI = ['diagnosa_awal', 'alasan', 'keluhan_utama', 'pemeriksaan_fisik', 'jalannya_penyakit', 'pemeriksaan_penunjang', 'hasil_laborat', 'tindakan_dan_operasi', 'obat_di_rs'];
    public const RESUME_DX = [['diagnosa_utama', 'kd_diagnosa_utama', 'Diagnosa Utama'], ['diagnosa_sekunder', 'kd_diagnosa_sekunder', 'Diagnosa Sekunder 1'],
        ['diagnosa_sekunder2', 'kd_diagnosa_sekunder2', 'Diagnosa Sekunder 2'], ['diagnosa_sekunder3', 'kd_diagnosa_sekunder3', 'Diagnosa Sekunder 3'], ['diagnosa_sekunder4', 'kd_diagnosa_sekunder4', 'Diagnosa Sekunder 4']];
    public const RESUME_PX = [['prosedur_utama', 'kd_prosedur_utama', 'Prosedur Utama'], ['prosedur_sekunder', 'kd_prosedur_sekunder', 'Prosedur Sekunder 1'],
        ['prosedur_sekunder2', 'kd_prosedur_sekunder2', 'Prosedur Sekunder 2'], ['prosedur_sekunder3', 'kd_prosedur_sekunder3', 'Prosedur Sekunder 3']];
    public const RESUME_PENUTUP = ['alergi', 'diet', 'lab_belum', 'edukasi', 'cara_keluar', 'ket_keluar', 'keadaan', 'ket_keadaan', 'dilanjutkan', 'ket_dilanjutkan', 'kontrol', 'obat_pulang', 'kondisi_pulang'];

    public static function label(string $col): string
    {
        if (isset(self::LABELS[$col])) return self::LABELS[$col];
        $w = [];
        foreach (explode('_', $col) as $t) $w[] = self::TOKENS[strtolower($t)] ?? ucfirst(strtolower($t));
        return implode(' ', $w);
    }
}
