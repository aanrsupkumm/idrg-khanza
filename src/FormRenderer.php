<?php
declare(strict_types=1);
namespace Idrg;

/** Pembuat HTML form cetak (meniru format form asli SIMRS Khanza / SEP BPJS). Semua nilai di-escape. */
final class FormRenderer
{
    public static function e($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Nilai kosong/placeholder -> ''. */
    public static function v($x): string
    {
        $x = trim((string)$x);
        return in_array($x, ['', '-', '0000-00-00', '0000-00-00 00:00:00'], true) ? '' : $x;
    }

    private static function txt($x): string
    {
        return nl2br(self::e($x));
    }

    public static function tgl($x, bool $jam = true): string
    {
        $x = self::v($x);
        $t = $x === '' ? false : strtotime($x);
        return $t === false ? $x : date($jam && strlen($x) > 10 ? 'd-m-Y H:i' : 'd-m-Y', $t);
    }

    /* ----------------------------------------------------------- bingkai */

    public static function kop(array $c): string
    {
        $img = !empty($c['logo']) ? '<img src="' . self::e($c['logo']) . '" alt="">' : '';
        $alamat = trim(($c['instansi']['alamat_instansi'] ?? '') . ' ' . ($c['instansi']['kabupaten'] ?? '') . ' ' . ($c['instansi']['propinsi'] ?? ''));
        return '<div class="kop">' . $img . '<div class="kc"><b>' . self::e($c['rs']) . '</b><span>' . self::e($alamat) . '</span><span>'
            . self::e(trim('Telp. ' . ($c['instansi']['kontak'] ?? ''))) . '</span></div>' . ($img ? '<div class="kx"></div>' : '') . '</div>';
    }

    public static function identity(array $c): string
    {
        $p = $c['pasien']; $r = $c['reg'];
        $jk = ($p['jk'] ?? '') === 'L' ? 'Laki-laki' : (($p['jk'] ?? '') === 'P' ? 'Perempuan' : '-');
        $row = function ($a, $b, $c2, $d) { return '<tr><td class="k">' . $a . '</td><td>' . $b . '</td><td class="k">' . $c2 . '</td><td>' . $d . '</td></tr>'; };
        return '<table class="id">'
            . $row('No. RM', self::e($r['no_rkm_medis']), 'No. Rawat', self::e($r['no_rawat']))
            . $row('Nama Pasien', '<b>' . self::e($p['nm_pasien'] ?? '') . '</b>', 'Jenis Kelamin', self::e($jk))
            . $row('Tgl. Lahir', self::e(self::tgl($p['tgl_lahir'] ?? '', false)) . ' (' . self::e($c['umur']) . ')', 'Tgl. Registrasi', self::e(self::tgl($r['tgl_registrasi'] . ' ' . $r['jam_reg'])))
            . $row('Alamat', self::e($p['alamat'] ?? ''), $c['module'] === 'ranap' ? 'Cara Bayar' : 'Poli / Cara Bayar', self::e(($c['module'] === 'ranap' ? '' : ($r['nm_poli'] ?? '') . ' / ') . ($r['png_jawab'] ?? '')))
            . '</table>';
    }

    public static function page(array $c, string $title, string $body, string $sub = '', bool $identity = true): string
    {
        return '<div class="form">' . self::kop($c) . '<h1 class="ft">' . self::e($title) . '</h1>' . ($sub !== '' ? '<div class="fsub">' . self::e($sub) . '</div>' : '')
            . ($identity ? self::identity($c) : '') . $body
            . '<div class="ffoot"><span>' . self::e($c['reg']['no_rawat']) . ' · ' . self::e($c['pasien']['nm_pasien'] ?? '') . '</span><span>Dicetak ' . self::e($c['dicetak']) . '</span></div></div>';
    }

    public static function section(string $title, string $inner): string
    {
        return $inner === '' ? '' : '<div class="sc"><h3>' . self::e($title) . '</h3>' . $inner . '</div>';
    }

    /** @param array<string,string> $pairs label => nilai mentah */
    public static function kv(array $pairs): string
    {
        $h = '';
        foreach ($pairs as $l => $v) {
            $v = self::v($v);
            if ($v !== '') $h .= '<tr><th>' . self::e($l) . '</th><td>' . self::txt($v) . '</td></tr>';
        }
        return $h === '' ? '' : '<table class="fk">' . $h . '</table>';
    }

    public static function strip(array $pairs): string
    {
        $h = '';
        foreach ($pairs as $l => $v) {
            $v = self::v($v);
            if ($v !== '') $h .= '<div><small>' . self::e($l) . '</small>' . self::e($v) . '</div>';
        }
        return $h === '' ? '' : '<div class="strip">' . $h . '</div>';
    }

    public static function sign(array $c, string $peran, string $nama, string $tgl = ''): string
    {
        $kab = trim((string)($c['instansi']['kabupaten'] ?? ''));
        return '<div class="sg"><div>' . self::e(trim($kab . ($kab !== '' ? ', ' : '') . self::tgl($tgl !== '' ? $tgl : date('Y-m-d H:i:s'), false)))
            . '<br>' . self::e($peran) . ',<div class="sp"></div><b>(' . self::e($nama !== '' ? $nama : '........................') . ')</b></div></div>';
    }

    /* ------------------------------------------------- form berbasis kolom */

    /** Render baris Khanza ke kelompok (jenis 'medis'/'perawat') + sisa kolom (data lainnya). */
    public static function groups(array $row, string $kind): string
    {
        $used = [];
        $html = '';
        foreach (FormCatalog::GROUPS[$kind] ?? [] as $g) {
            list($title, $strip, $cols) = $g;
            $sp = [];
            foreach ($strip as $k) {
                $used[$k] = 1;
                if (isset($row[$k])) $sp[FormCatalog::label($k) . (isset(FormCatalog::STRIP_UNIT[$k]) ? ' (' . FormCatalog::STRIP_UNIT[$k] . ')' : '')] = $row[$k];
            }
            $kv = [];
            foreach ($cols as $k) { $used[$k] = 1; if (isset($row[$k])) $kv[FormCatalog::label($k)] = $row[$k]; }
            $html .= self::section($title, self::strip($sp) . self::kv($kv));
        }
        $rest = [];
        foreach ($row as $k => $val) {
            if (isset($used[$k]) || in_array($k, FormCatalog::EXCLUDE, true) || $k[0] === '_') continue;
            $rest[FormCatalog::label($k)] = $val;
        }
        return $html . self::section($html === '' ? 'Data Pemeriksaan' : 'Data Lainnya', self::kv($rest));
    }

    public static function resume(array $row, array $c): string
    {
        $used = array_flip(array_merge(FormCatalog::RESUME_NARASI, FormCatalog::RESUME_PENUTUP));
        $meta = [];
        if (!empty($c['ruang'])) $meta['Ruang / Kelas'] = $c['ruang'];
        if (!empty($c['masuk'])) $meta['Tanggal Masuk'] = self::tgl($c['masuk']);
        if (!empty($c['keluar'])) $meta['Tanggal Keluar'] = self::tgl($c['keluar']);
        $meta['DPJP'] = $c['dpjp'] ?? '';
        $h = self::kv($meta);
        $nar = [];
        foreach (FormCatalog::RESUME_NARASI as $k) if (isset($row[$k])) $nar[FormCatalog::label($k)] = $row[$k];
        $h .= self::kv($nar);
        $t = '';
        foreach (FormCatalog::RESUME_DX as $d) {
            $used[$d[0]] = $used[$d[1]] = 1;
            if (self::v($row[$d[0]] ?? '') !== '') $t .= '<tr><td>' . self::e($d[2]) . '</td><td>' . self::txt($row[$d[0]]) . '</td><td class="c">' . self::e($row[$d[1]] ?? '') . '</td></tr>';
        }
        if ($t !== '') $h .= self::section('Diagnosa', '<table class="fk"><thead><tr><th style="width:20%">Jenis</th><th>Diagnosa</th><th style="width:14%">Kode ICD-10</th></tr></thead>' . $t . '</table>');
        $t = '';
        foreach (FormCatalog::RESUME_PX as $d) {
            $used[$d[0]] = $used[$d[1]] = 1;
            if (self::v($row[$d[0]] ?? '') !== '') $t .= '<tr><td>' . self::e($d[2]) . '</td><td>' . self::txt($row[$d[0]]) . '</td><td class="c">' . self::e($row[$d[1]] ?? '') . '</td></tr>';
        }
        if ($t !== '') $h .= self::section('Prosedur / Tindakan', '<table class="fk"><thead><tr><th style="width:20%">Jenis</th><th>Prosedur</th><th style="width:14%">Kode ICD-9-CM</th></tr></thead>' . $t . '</table>');
        $pen = [];
        foreach (FormCatalog::RESUME_PENUTUP as $k) if (isset($row[$k])) $pen[FormCatalog::label($k)] = $row[$k];
        $h .= self::section('Kondisi Saat Keluar & Tindak Lanjut', self::kv($pen));
        $rest = [];
        foreach ($row as $k => $val) {
            if (isset($used[$k]) || in_array($k, FormCatalog::EXCLUDE, true) || $k[0] === '_' || strpos($k, 'kd_') === 0) continue;
            $rest[FormCatalog::label($k)] = $val;
        }
        return $h . self::section('Data Lainnya', self::kv($rest));
    }

    /* ----------------------------------------------------------- SEP */

    public static function sep(array $s, array $c): string
    {
        $v = function ($k) use ($s) { $x = self::v($s[$k] ?? ''); return $x === '' ? '-' : $x; };
        $kls = ['1' => 'Kelas 1', '2' => 'Kelas 2', '3' => 'Kelas 3'];
        $kelas = $kls[substr(trim((string)($s['klsrawat'] ?? '')), 0, 1)] ?? $v('klsrawat');
        if (self::v($s['klsnaik'] ?? '') !== '') $kelas .= ' (naik ke ' . preg_replace('/^\d+\.\s*/', '', (string)$s['klsnaik']) . ')';
        $ri = substr(trim((string)($s['jnspelayanan'] ?? '')), 0, 1) === '1';
        $cob = self::v($s['cob'] ?? '');
        $cob = ($cob === '' || $cob[0] === '0') ? '-' : preg_replace('/^\d+\.\s*/', '', $cob);
        $kll = self::v($s['lakalantas'] ?? '');
        $penjamin = ($kll !== '' && $kll[0] !== '0') ? self::v($s['pjnaikkelas'] ?? $s['pembiayaan'] ?? '') : '-';
        $jk = ['L' => 'Laki-Laki', 'P' => 'Perempuan'][strtoupper(substr(trim((string)($s['jkel'] ?? '')), 0, 1))] ?? '-';
        $diag = trim(self::v($s['diagawal'] ?? '') . ' ' . self::v($s['nmdiagnosaawal'] ?? ''));
        $left = [
            ['No.SEP', '<b>' . self::e($v('no_sep')) . '</b>'], ['Tgl.SEP', self::e($v('tglsep'))],
            ['No.Kartu', self::e($v('no_kartu')) . ' ( MR. ' . self::e($v('nomr')) . ' )'], ['Nama Peserta', self::e($v('nama_pasien'))],
            ['Tgl.Lahir', self::e($v('tanggal_lahir'))], ['Jns.Kelamin', self::e($jk)], ['No.Telepon', self::e($v('notelep'))],
            ['Sub/Spesialis', self::e($v('nmpolitujuan'))], ['DPJP Yg Melayani', self::e($v('nmdpjplayanan'))],
            ['Faskes Perujuk', self::e($v('nmppkrujukan'))], ['Diagnosa Awal', self::e($diag !== '' ? $diag : '-')], ['Catatan', self::e($v('catatan'))],
        ];
        $right = [['Peserta', self::e($v('peserta'))], ['COB', self::e($cob)], ['Jns.Rawat', $ri ? 'R.Inap' : 'R.Jalan'], ['Kls.Rawat', self::e($kelas)], ['Penjamin', self::e($penjamin)]];
        $rows = '';
        foreach ($left as $i => $l) {
            $r = $right[$i] ?? null;
            $rows .= '<tr><td class="l">' . $l[0] . '</td><td class="m">:</td><td class="v">' . $l[1] . '</td>'
                . ($r ? '<td class="l2">' . $r[0] . '</td><td class="m">:</td><td class="v2">' . $r[1] . '</td>' : '<td colspan="3"></td>') . '</tr>';
        }
        $bpjs = !empty($c['logo_bpjs']) ? '<img src="' . self::e($c['logo_bpjs']) . '" alt="BPJS Kesehatan">' : '<div class="bpjs"><b>BPJS</b> KESEHATAN<small>Badan Penyelenggara Jaminan Sosial</small></div>';
        return '<div class="form sep"><table class="sep-h"><tr><td class="bp">' . $bpjs . '</td><td class="tt"><b>SURAT ELEGIBILITAS PESERTA</b><br>'
            . self::e($c['rs']) . '</td></tr></table><table class="sep-b">' . $rows . '</table>'
            . '<div class="sep-f"><div class="nt">*Saya menyetujui BPJS Kesehatan menggunakan informasi medis pasien jika diperlukan.<br>*SEP Bukan sebagai bukti penjaminan peserta.</div>'
            . '<div class="ttd"><span>Persetujuan</span><span>Pasien/Keluarga Pasien</span><div class="sp"></div><span>(' . self::e($v('nama_pasien')) . ')</span></div></div>'
            . '<div class="cet">Cetakan ke 1 ' . self::e($c['dicetak']) . ' WIB</div></div>';
    }

    /* ----------------------------------------------------------- CPPT */

    public static function cppt(array $rows, array $c): string
    {
        $t = '';
        foreach ($rows as $r) {
            $vit = [];
            foreach (['tensi' => 'TD', 'nadi' => 'Nadi', 'respirasi' => 'RR', 'suhu_tubuh' => 'Suhu', 'spo2' => 'SpO2', 'gcs' => 'GCS', 'berat' => 'BB', 'tinggi' => 'TB'] as $k => $l)
                if (self::v($r[$k] ?? '') !== '') $vit[] = $l . ' ' . $r[$k];
            $soap = '';
            foreach (['keluhan' => 'S', 'pemeriksaan' => 'O', 'penilaian' => 'A', 'rtl' => 'P'] as $k => $l)
                if (self::v($r[$k] ?? '') !== '') $soap .= '<div class="sp4"><b>' . $l . ':</b> ' . self::txt($r[$k]) . '</div>';
            $ins = trim(self::v($r['instruksi'] ?? '') . (self::v($r['evaluasi'] ?? '') !== '' ? "\n" . 'Evaluasi: ' . $r['evaluasi'] : ''));
            $t .= '<tr><td class="nw">' . self::e(self::tgl($r['tgl_perawatan'] . ' ' . $r['jam_rawat'])) . '<br><small>' . self::e($r['_jenis'] ?? '') . '</small></td>'
                . '<td>' . self::e($r['_petugas'] ?? '') . '</td>'
                . '<td>' . ($vit ? '<div class="vt">' . self::e(implode('  |  ', $vit)) . '</div>' : '') . $soap . '</td><td>' . self::txt($ins) . '</td><td class="par"></td></tr>';
        }
        return '<table class="fk cp"><thead><tr><th style="width:13%">Tanggal / Jam</th><th style="width:14%">Profesi / PPA</th>'
            . '<th>Hasil Asesmen Pasien &amp; Pemberian Pelayanan (SOAP)</th><th style="width:18%">Instruksi PPA</th><th style="width:7%">Paraf</th></tr></thead>' . $t . '</table>';
    }

    /* ------------------------------------------------- laboratorium dsb. */

    /** @param array $groups tiap elemen: nm, tgl, jam, perujuk, petugas, items[] */
    public static function lab(array $groups, array $c): string
    {
        $h = '';
        foreach ($groups as $g) {
            $rows = '';
            $i = 0;
            foreach ($g['items'] as $r) {
                $ref = BerkasHelper::labRef($r, $c['jk'], $c['tahun']);
                $fl = BerkasHelper::labFlag((string)$r['nilai'], $ref);
                $rows .= '<tr><td class="c">' . (++$i) . '</td><td>' . self::e($r['Pemeriksaan']) . '</td><td class="r' . ($fl ? ' fl' : '') . '">' . self::e(trim($r['nilai'])) . ($fl ? ' ' . $fl : '') . '</td>'
                    . '<td>' . self::e($r['satuan']) . '</td><td>' . self::e($ref) . '</td><td>' . self::e(self::v($r['keterangan'] ?? '')) . '</td></tr>';
            }
            $h .= '<div class="sc"><h3>' . self::e($g['nm']) . ' — ' . self::e(self::tgl($g['tgl'] . ' ' . $g['jam'])) . ($g['perujuk'] !== '' ? ' — Dokter Perujuk: ' . self::e($g['perujuk']) : '') . '</h3>'
                . '<table class="fk"><thead><tr><th style="width:5%">No</th><th>Pemeriksaan</th><th style="width:13%">Hasil</th><th style="width:11%">Satuan</th><th style="width:20%">Nilai Rujukan</th><th style="width:16%">Keterangan</th></tr></thead>' . $rows . '</table>'
                . ($g['petugas'] !== '' ? '<div class="pj">Petugas: ' . self::e($g['petugas']) . '</div>' : '') . '</div>';
        }
        return $h . '<div class="pj">H = di atas nilai rujukan, L = di bawah nilai rujukan (penilaian otomatis sederhana).</div>';
    }
}
