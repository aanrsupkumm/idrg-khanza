<?php
// Salin menjadi config.php lalu sesuaikan.
return [
    // Database SIMRS Khanza (default nama DB: sik)
    'db' => ['host' => 'localhost', 'port' => 3306, 'name' => 'sik', 'user' => 'root', 'pass' => ''],

    // Login memakai akun SIMRS Khanza (tabel user + pegawai). Kunci AES bawaan Khanza: 'nur' (id_user) dan 'windi' (password).
    'auth' => [
        'key_user'        => 'nur',
        'key_pass'        => 'windi',
        'allowed_users'   => [],            // kosong = semua akun Khanza; isi daftar NIK pegawai untuk membatasi
        'session_minutes' => 120,           // batas tidak aktif
        // Prefix IP yang boleh mengakses, kosongkan array untuk menonaktifkan
        'allowed_ips'     => ['127.0.0.1', '::1', '192.168.', '10.'],
    ],

    // Server aplikasi E-Klaim iDRG (lokal RS)
    'eklaim' => [
        'base_url'   => 'http://192.168.1.10',
        'endpoint'   => '/E-Klaim/ws.php',
        'debug_mode' => true,   // true = JSON polos (?mode=debug). Produksi: false + isi key
        'key'        => '',     // 64 karakter hex (256-bit) dari menu Setting E-Klaim
        'timeout'    => 30,
        'coder_nik'  => '',     // NIK coder terdaftar di E-Klaim
        'coder_dari_login' => false, // true = pakai No. KTP pegawai yang sedang login sebagai coder (pegawai.no_ktp)
    ],

    'rs' => [
        // 'nama' => 'RSU CONTOH',            // kosong = ambil dari tabel setting Khanza
        'kode_tarif' => 'AP',   // sesuaikan kelas RS & kepemilikan
        'payor_id'   => '3',
        'payor_cd'   => 'JKN',
        'cob_cd'     => '0',
        'kd_poli_igd'  => [],                              // isi (mis. ['IGDK']) bila IGD ingin dikirim sebagai jenis_rawat 3
        'icu_keywords' => ['ICU', 'ICCU', 'NICU', 'PICU'], // nama bangsal dianggap ICU
    ],

    // Lokasi gambar di webapps Khanza (untuk berkas klaim). base_url kosong = host saat ini.
    'berkas' => [
        // 'base_url' => 'http://192.168.1.5',
        'path_radiologi' => '/webapps/radiologi/',
        'path_berkas'    => '/webapps/berkasrawat/',
        // 'logo_bpjs'   => 'assets/bpjs.png',      // logo BPJS pada cetakan SEP (bila kosong dipakai tulisan)
        // Tabel form tambahan (kelompok: resume | medis | perawat | operasi): [tabel, judul, kelompok]
        // 'form_tambahan' => [['penilaian_medis_ralan_paru', 'ASESMEN AWAL MEDIS RAWAT JALAN - PARU', 'medis']],
    ],

    // Unggah lampiran berkas digital tambahan (disimpan di storage/uploads, di luar webroot)
    'upload' => [
        'max_mb'    => 10,                                            // dibatasi juga oleh upload_max_filesize/post_max_size php.ini
        'allowed'   => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'],
        'max_files' => 50,                                            // per kunjungan
        // 'dir' => 'D:/data/idrg_uploads',                           // opsional: lokasi lain
    ],

    // Pemetaan billing.status Khanza -> komponen tarif_rs E-Klaim.
    // Status yang tidak ada di sini DIABAIKAN (dan muncul sebagai peringatan).
    // WAJIB divalidasi dengan billing RS Anda.
    'billing_map' => [
        'Registrasi'             => 'konsultasi',
        'Ralan Dokter'           => 'tenaga_ahli',
        'Ralan Dokter Paramedis' => 'tenaga_ahli',
        'Ralan Paramedis'        => 'keperawatan',
        'Ranap Dokter'           => 'tenaga_ahli',
        'Ranap Dokter Paramedis' => 'tenaga_ahli',
        'Ranap Paramedis'        => 'keperawatan',
        'Operasi'                => 'prosedur_bedah',
        'Paket Operasi'          => 'prosedur_bedah',
        'Laborat'                => 'laboratorium',
        'Radiologi'              => 'radiologi',
        'Kamar'                  => 'kamar',
        'Harian'                 => 'kamar',
        'Obat'                   => 'obat',
        'Retur Obat'             => 'obat',
        'Resep Pulang'           => 'obat',
        'Tambahan'               => 'penunjang',
        'Service'                => 'penunjang',
    ],
];
