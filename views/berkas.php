<?php
/** @var array $d @var array $o @var string $base @var array $cfg */
use Idrg\BerkasHelper as H;
use Idrg\FormRenderer as F;

$reg = $d['reg']; $p = $d['pasien']; $ins = $d['instansi'];
$pdf = !empty($o['pdf']);
$store = $o['store'] ?? null;
$module = $d['module'];
$ranap = $module === 'ranap';
$title = 'Berkas_Klaim_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $reg['no_rawat']);
$dirRad = $cfg['berkas']['path_radiologi'] ?? '/webapps/radiologi/';
$dirBks = $cfg['berkas']['path_berkas'] ?? '/webapps/berkasrawat/';
$namaRs = $cfg['rs']['nama'] ?? ($ins['nama_instansi'] ?? 'Rumah Sakit');
$isImg = function ($f) { return (bool)preg_match('/\.(jpe?g|png|gif|bmp|webp)$/i', (string)$f); };
$jk = (string)($p['jk'] ?? '');
list($umurTxt, $umurTh) = H::umur((string)($p['tgl_lahir'] ?? ''), (string)$reg['tgl_registrasi']);
$dicetak = date('d/m/Y H:i');

$c = ['rs' => $namaRs, 'instansi' => $ins, 'logo' => $d['logo'], 'logo_bpjs' => $cfg['berkas']['logo_bpjs'] ?? '', 'pasien' => $p, 'reg' => $reg,
      'module' => $module, 'umur' => $umurTxt, 'jk' => $jk, 'tahun' => $umurTh, 'dicetak' => $dicetak];

/* billing */
$grand = null; $sum = 0.0; $bill = [];
foreach ($d['billing'] as $r) {
    $st = (string)$r['status'];
    if (strcasecmp($st, 'Total') === 0) { $grand = (float)$r['totalbiaya']; continue; }
    if (stripos($st, 'Ttl') === 0) continue;
    $bill[$st][] = $r; $sum += (float)$r['totalbiaya'];
}
$totalBiaya = $grand !== null ? $grand : ($bill ? $sum : null);
$lama = (int)array_sum(array_column($d['kamar'], 'lama'));
$lamaTxt = $lama > 0 ? $lama . ' hari' : ($ranap ? '-' : '1 hari');
$nTind = count($d['tindakan']) + count($d['operasi']);
$kamarAkhir = $d['kamar'] ? end($d['kamar']) : null; $kamarAwal = $d['kamar'] ? reset($d['kamar']) : null;
$c['ruang'] = $kamarAkhir ? $kamarAkhir['nm_bangsal'] . ' / ' . $kamarAkhir['kd_kamar'] : '';
$c['masuk'] = $kamarAwal ? $kamarAwal['tgl_masuk'] . ' ' . $kamarAwal['jam_masuk'] : '';
$c['keluar'] = ($kamarAkhir && $kamarAkhir['stts_pulang'] !== '-') ? $kamarAkhir['tgl_keluar'] . ' ' . $kamarAkhir['jam_keluar'] : '';
$c['dpjp'] = (string)$reg['nm_dokter'];


$T = function (array $heads, array $rows) {
    $h = '<table class="fk"><thead><tr>';
    foreach ($heads as $x) $h .= '<th' . (isset($x[1]) ? ' style="width:' . $x[1] . '"' : '') . '>' . F::e($x[0]) . '</th>';
    $h .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr' . (isset($r['_cls']) ? ' class="' . F::e($r['_cls']) . '"' : '') . '>';
        foreach ($r as $k => $cell) {
            if ($k === '_cls') continue;
            $h .= is_array($cell) ? '<td' . (isset($cell['c']) ? ' class="' . $cell['c'] . '"' : '') . (isset($cell['span']) ? ' colspan="' . $cell['span'] . '"' : '') . '>' . ($cell['h'] ?? '') . '</td>' : '<td>' . F::e($cell) . '</td>';
        }
        $h .= '</tr>';
    }
    return $h . '</tbody></table>';
};

$docs = [];
$add = function ($key, $label, $html, $on = true) use (&$docs) {
    if ($html === '') return;
    if (!isset($docs[$key])) $docs[$key] = ['label' => $label, 'html' => '', 'on' => $on];
    $docs[$key]['html'] .= $html;
};
$fmtTgl = function ($x) { return F::tgl($x); };

/* ---------- 1. Ringkasan (sampul) ---------- (tidak ikut cetak secara default)*/
ob_start(); ?>
<div class="form cover">
  <div class="hero"><div class="rs"><b><?= F::e($namaRs) ?></b>
    <span><?= F::e(trim(($ins['alamat_instansi'] ?? '') . ' ' . ($ins['kabupaten'] ?? '') . ' ' . ($ins['propinsi'] ?? '') . ' ' . ($ins['kontak'] ?? ''))) ?></span>
    <div class="doc">Berkas Klaim · <?= $ranap ? 'Rawat Inap' : 'Rawat Jalan' ?></div></div>
    <div class="meta"><span class="pill"><?= $ranap ? 'Rawat Inap' : 'Rawat Jalan' ?></span><b><?= F::e($reg['no_rawat']) ?></b><?= !empty($d['sep']) ? 'SEP ' . F::e($d['sep']['no_sep']) : '' ?></div></div>
  <div class="kpis">
    <div class="kpi"><span>Lama rawat</span><b><?= F::e($lamaTxt) ?></b></div>
    <div class="kpi"><span>Diagnosa</span><b><?= count($d['diagnosa']) ?></b></div>
    <div class="kpi"><span>Tindakan / operasi</span><b><?= $nTind ?></b></div>
    <div class="kpi"><span>Total biaya RS</span><b><?= $totalBiaya === null ? '-' : F::e(H::rupiah($totalBiaya)) ?></b></div>
  </div>
  <?= F::identity($c) ?>
  <?php if ($d['diagnosa']): ?>
    <?= F::section('Diagnosa (ICD-10)', $T([['Kode', '14%'], ['Nama penyakit'], ['Peran', '14%'], ['Status', '12%']],
        array_map(function ($r, $i) { return [$r['kd_penyakit'], $r['nm_penyakit'], $i === 0 ? 'Utama' : 'Sekunder', $r['status']]; }, $d['diagnosa'], array_keys($d['diagnosa'])))) ?>
  <?php endif; ?>
  <?php if ($d['prosedur']): ?>
    <?= F::section('Prosedur (ICD-9-CM)', $T([['Kode', '14%'], ['Nama prosedur'], ['Status', '12%']], array_map(function ($r) { return [$r['kode'], $r['deskripsi_panjang'], $r['status']]; }, $d['prosedur']))) ?>
  <?php endif; ?>
  <?php if ($d['kamar']): ?>
    <?= F::section('Riwayat Kamar Inap', $T([['Bangsal / kamar'], ['Masuk'], ['Keluar'], ['Lama', '9%'], ['Status pulang']],
        array_map(function ($r) { return [$r['nm_bangsal'] . ' · ' . $r['kd_kamar'], F::tgl($r['tgl_masuk'] . ' ' . $r['jam_masuk']), $r['stts_pulang'] === '-' ? 'masih dirawat' : F::tgl($r['tgl_keluar'] . ' ' . $r['jam_keluar']), $r['lama'] . ' hr', $r['stts_pulang']]; }, $d['kamar']))) ?>
  <?php endif; ?>
</div>
<?php $add('ringkasan', 'Ringkasan', ob_get_clean());

/* ---- 2. Lampiran tambahan / lembar individual klaim -----*/
if ($d['lampiran']) {
    $b = '<div class="gal one">';
    foreach ($d['lampiran'] as $f) {
        $isI = strpos($f['mime'], 'image/') === 0; $url = 'files.php?action=get&id=' . (int)$f['id'];
        $src = $pdf ? ($store ? $store->dataUri($f) : '') : $url;
        $b .= '<figure>';
        if ($isI) { if ($src) $b .= '<a href="' . F::e($url) . '" target="_blank"><img src="' . F::e($src) . '" alt="' . F::e($f['judul']) . '"></a>'; }
        else {
            $b .= '<div class="pdfnote"><b>PDF</b> · ' . F::e($f['nama_asli']) . ' (' . F::e(H::size((int)$f['ukuran'])) . ') — lampiran PDF tidak ikut tercetak ke PDF ini; '
                . ($pdf ? 'unduh terpisah dari aplikasi.' : '<a href="' . F::e($url) . '&download=1">unduh terpisah</a> dan gabungkan bila diperlukan.') . '</div>'
                . ($pdf ? '' : '<object class="pdfobj noprint" data="' . F::e($url) . '" type="application/pdf"></object>');
        }
        $b .= '<figcaption><b>' . F::e($f['judul']) . '</b> · ' . F::e($f['nama_asli']) . '</figcaption></figure>';
    }
    $add('lampiran', 'Lampiran Tambahan', F::page($c, 'berkas individual klaim', $b . '</div>'));
}

/* ---------- 3. SEP ---------- */
if (!empty($d['sep'])) $add('sep', 'SEP', F::sep($d['sep'], $c));

/* ---------- 4. Resume ---------- */
foreach ($d['forms']['resume'] as $f) {
    $body = F::resume($f['row'], $c + ['dpjp' => $f['dokter'] ?: $c['dpjp']]) . F::sign($c, 'Dokter DPJP', $f['dokter'] ?: $c['dpjp'], $f['tanggal']);
    $add('resume', 'Resume Medis', F::page($c, $f['title'], $body));
}

/* ---------- 5/6. Asesmen awal medis & keperawatan ---------- */
foreach (['medis' => ['asesmen_medis', 'Asesmen Medis', 'Dokter Pemeriksa'], 'perawat' => ['asesmen_perawat', 'Asesmen Keperawatan', 'Perawat Pemeriksa']] as $grp => $cfgDoc) {
    foreach ($d['forms'][$grp] as $f) {
        $who = $grp === 'medis' ? $f['dokter'] : ($f['petugas'] ?: $f['dokter']);
        $body = F::groups($f['row'], $f['kind']) . F::sign($c, $cfgDoc[2], $who, $f['tanggal']);
        $add($cfgDoc[0], $cfgDoc[1], F::page($c, $f['title'], $body, $f['tanggal'] !== '' ? 'Tanggal asesmen: ' . F::tgl($f['tanggal']) : ''));
    }
}

/* ---------- 7. CPPT ---------- (tidak ikut cetak secara default) */
if ($d['soap']) $add('cppt', 'CPPT / SOAP', F::page($c, 'CATATAN PERKEMBANGAN PASIEN TERINTEGRASI (CPPT)', F::cppt($d['soap'], $c)));

/* ---------- 8. Tindakan ---------- */
if ($d['tindakan']) $add('tindakan', 'Tindakan', F::page($c, 'RINCIAN TINDAKAN / PERAWATAN',
    $T([['Tanggal / Jam', '19%'], ['Tindakan'], ['Pelaksana', '28%'], ['Jenis', '9%']],
        array_map(function ($r) { return [F::tgl($r['tgl_perawatan'] . ' ' . $r['jam_rawat']), $r['nm_perawatan'], $r['pelaksana'], $r['jenis']]; }, $d['tindakan']))
    . F::sign($c, 'Dokter DPJP', $c['dpjp'])));

/* ---------- 9. Operasi ---------- */
if ($d['operasi']) $add('operasi', 'Operasi', F::page($c, 'DAFTAR TINDAKAN OPERASI',
    $T([['Tanggal', '18%'], ['Tindakan operasi'], ['Operator', '22%'], ['Anestesi', '22%']],
        array_map(function ($r) { return [F::tgl($r['tgl_operasi']), $r['nm_perawatan'], $r['operator'], trim($r['anestesi'] . ($r['jenis_anasthesi'] ? ' (' . $r['jenis_anasthesi'] . ')' : ''))]; }, $d['operasi']))));
foreach ($d['forms']['operasi'] as $f) {
    $add('operasi', 'Operasi', F::page($c, $f['title'], F::groups($f['row'], 'generic') . F::sign($c, 'Dokter Operator', $f['dokter'], $f['tanggal']),
        $f['tanggal'] !== '' ? 'Tanggal: ' . F::tgl($f['tanggal']) : ''));
}

/* ---------- 10. Laboratorium ---------- */
if ($d['lab_groups']) $add('lab', 'Laboratorium', F::page($c, 'HASIL PEMERIKSAAN LABORATORIUM', F::lab($d['lab_groups'], $c)));

/* ---------- 11. Radiologi ---------- */
if ($d['radiologi'] || $d['periksa_rad']) {
    $b = '';
    if ($d['periksa_rad']) $b .= F::section('Pemeriksaan', $T([['Tanggal / Jam', '22%'], ['Pemeriksaan'], ['Dokter', '28%']],
        array_map(function ($r) { return [F::tgl($r['tgl_periksa'] . ' ' . $r['jam']), $r['nm_perawatan'], $r['nm_dokter']]; }, $d['periksa_rad'])));
    foreach ($d['radiologi'] as $r) {
        $b .= '<div class="sc"><h3>Hasil / Bacaan — ' . F::e(F::tgl($r['tgl_periksa'] . ' ' . $r['jam'])) . '</h3><div class="txt">' . nl2br(F::e($r['hasil'])) . '</div>';
        $gm = array_values(array_filter($d['gambar_rad'], function ($g) use ($r, $isImg) { return $g['tgl_periksa'] == $r['tgl_periksa'] && $g['jam'] == $r['jam'] && $isImg($g['lokasi_gambar']); }));
        if ($gm) { $b .= '<div class="gal">'; foreach ($gm as $g) { $u = berkas_url($base, $dirRad, $g['lokasi_gambar']); $b .= '<figure><a href="' . F::e($u) . '" target="_blank"><img src="' . F::e($u) . '" alt="radiologi"></a></figure>'; } $b .= '</div>'; }
        $b .= '</div>';
    }
    $add('rad', 'Radiologi', F::page($c, 'HASIL PEMERIKSAAN RADIOLOGI', $b));
}

/* ---------- 12. Obat ---------- */
if ($d['obat'] || $d['resep']) {
    $b = '';
    if ($d['obat']) $b .= F::section('Pemberian Obat / BHP / Alkes', $T([['Tanggal / Jam', '22%'], ['Nama obat / BHP'], ['Jumlah', '16%']],
        array_map(function ($r) { return [F::tgl($r['tgl_perawatan'] . ' ' . $r['jam']), $r['nama_brng'], ['h' => F::e($r['jml'] . ' ' . $r['kode_sat']), 'c' => 'r']]; }, $d['obat'])));
    if ($d['resep']) $b .= F::section('Resep Pulang', $T([['Obat'], ['Jumlah', '16%'], ['Aturan pakai', '30%']],
        array_map(function ($r) { return [$r['nama_brng'], ['h' => F::e(($r['jml_barang'] ?? '') . ' ' . $r['kode_sat']), 'c' => 'r'], $r['dosis'] ?? '']; }, $d['resep'])));
    $add('obat', 'Obat & Resep', F::page($c, 'RIWAYAT PEMBERIAN OBAT / BHP', $b));
}

/* ---------- 13. Rincian biaya ---------- */
if ($bill) {
    $rows = [];
    foreach ($bill as $st => $items) {
        $rows[] = ['_cls' => 'grp', ['h' => F::e($st), 'span' => 4]];
        foreach ($items as $r) $rows[] = [$r['nm_perawatan'], ['h' => F::e($r['jumlah'] ?? ''), 'c' => 'r'], ['h' => isset($r['biaya']) ? F::e(number_format((float)$r['biaya'], 0, ',', '.')) : '', 'c' => 'r'], ['h' => F::e(number_format((float)$r['totalbiaya'], 0, ',', '.')), 'c' => 'r']];
    }
    $rows[] = ['_cls' => 'tot', ['h' => 'TOTAL', 'span' => 3], ['h' => F::e(number_format((float)$totalBiaya, 0, ',', '.')), 'c' => 'r']];
    $add('billing', 'Rincian Biaya', F::page($c, 'RINCIAN BIAYA PERAWATAN', $T([['Uraian'], ['Jumlah', '10%'], ['Tarif', '16%'], ['Total', '16%']], $rows)));
}

/* ---------- 14. Berkas digital RS ---------- */
if ($d['berkas']) {
    $b = '<div class="gal one">';
    foreach ($d['berkas'] as $r) {
        $nm = $r['nama'] ?: $r['kode'];
        $b .= '<figure>';
        if ($isImg($r['lokasi_file'])) {
            $u = berkas_url($base, $dirBks, $r['lokasi_file']);
            $b .= '<a href="' . F::e($u) . '" target="_blank"><img src="' . F::e($u) . '" alt="' . F::e($nm) . '"></a>';
        } else {
            $b .= '<div class="pdfnote">Lampiran non-gambar (' . F::e(basename($r['lokasi_file'])) . ') tidak dapat disisipkan ke PDF ini.</div>';
        }
        $b .= '<figcaption><b>' . F::e($nm) . '</b></figcaption></figure>';
    }
    $add('berkas', 'Berkas Digital RS', F::page($c, 'BERKAS DIGITAL PERAWATAN', $b . '</div>'));
}


/* ---------- 15. Status klaim (tidak ikut cetak secara default) ---------- */
if ($d['grouping']) {
    $rows = [];
    foreach ($d['grouping'] as $r) {
        $sm = ''; $j = json_decode((string)$r['response_json'], true);
        if (!empty($j['_summary']['drg'])) $sm = 'DRG ' . $j['_summary']['drg'] . ' ' . ($j['_summary']['drg_desc'] ?? '') . ' (MDC ' . ($j['_summary']['mdc'] ?? '-') . ')';
        $rows[] = [$r['step'], $r['status'], trim($sm . ' ' . $r['message']), $r['updated_at']];
    }
    $add('grouping', 'Status Klaim', F::page($c, 'STATUS PROSES KLAIM IDRG / INA-CBG', $T([['Langkah'], ['Status', '10%'], ['Keterangan'], ['Waktu', '20%']], $rows)), false);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= F::e($title) ?></title>
<style>
:root { --ac:#0e7490; --ac2:#164e63; --ink:#0f172a; --mut:#64748b; --line:#e2e8f0; --soft:#f1f5f9; }
@page { size: A4; margin: 10mm 10mm; }
* { box-sizing: border-box; }
body { margin: 0; font: 11px/1.45 Arial, Helvetica, sans-serif; color: #111; background: <?= $pdf ? '#fff' : '#cfd6df' ?>; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.form { background: #fff; <?= $pdf ? '' : 'max-width: 210mm; margin: 14px auto; padding: 11mm 13mm; box-shadow: 0 6px 24px #0f172a30; border-radius: 4px;' ?> break-before: page; page-break-before: always; }
.doc:first-child > .form:first-child { break-before: auto; page-break-before: auto; }
/* kop surat & judul form */
.kop { display: flex; align-items: center; gap: 12px; border-bottom: 3px double #000; padding-bottom: 6px; margin-bottom: 8px; }
.kop img { height: 56px; width: auto; } .kop .kx { width: 56px; } .kop .kc { flex: 1; text-align: center; } .kop b { font-size: 15px; display: block; text-transform: uppercase; } .kop span { display: block; font-size: 10px; }
h1.ft { text-align: center; font-size: 13.5px; margin: 8px 0 2px; text-transform: uppercase; letter-spacing: .3px; text-decoration: underline; }
.fsub { text-align: center; font-size: 10.5px; margin-bottom: 4px; }
table.id { width: 100%; border-collapse: collapse; margin: 6px 0 8px; } .id td { border: 1px solid #000; padding: 3px 6px; } .id td.k { width: 15%; font-weight: 700; background: #efefef; }
.sc { margin: 8px 0 0; break-inside: auto; } .sc > h3 { margin: 0; background: #e4e4e4; border: 1px solid #000; border-bottom: 0; padding: 3px 6px; font-size: 11px; text-transform: uppercase; break-after: avoid; }
table.fk { width: 100%; border-collapse: collapse; } .fk th, .fk td { border: 1px solid #000; padding: 3px 6px; vertical-align: top; text-align: left; }
.fk th { background: #f4f4f4; font-weight: 700; } .fk tbody th { width: 27%; font-weight: 600; background: #fafafa; }
.fk thead { display: table-header-group; } .fk tr { break-inside: avoid; page-break-inside: avoid; }
.fk td.c { text-align: center; } .fk td.r { text-align: right; white-space: nowrap; } .fk td.nw { white-space: nowrap; }
.fk tr.grp td { background: #e9e9e9; font-weight: 700; } .fk tr.tot td { font-weight: 700; border-top: 2px solid #000; background: #f1f1f1; }
td.fl { font-weight: 700; }
.strip { display: flex; flex-wrap: wrap; border-left: 1px solid #000; border-top: 1px solid #000; } .strip div { flex: 1 1 14%; padding: 3px 6px; border-right: 1px solid #000; border-bottom: 1px solid #000; }
.strip small { display: block; font-size: 9px; color: #444; } .txt { border: 1px solid #000; border-top: 0; padding: 5px 7px; white-space: pre-wrap; }
.sg { display: flex; justify-content: flex-end; margin-top: 14px; break-inside: avoid; } .sg > div { width: 44%; text-align: center; } .sg .sp { height: 52px; }
.ffoot { display: flex; justify-content: space-between; margin-top: 12px; padding-top: 4px; border-top: 1px solid #999; font-size: 9px; color: #555; }
.pj { font-size: 9.5px; color: #444; margin: 3px 0; }
.cp .vt { font-size: 10px; color: #333; border-bottom: 1px dotted #888; margin-bottom: 2px; } .cp .sp4 { margin: 1px 0; white-space: pre-wrap; } .cp td.par { height: 30px; }
.gal { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 6px; } .gal.one { grid-template-columns: 1fr; }
figure { margin: 0; border: 1px solid #000; padding: 5px; text-align: center; break-inside: avoid; page-break-inside: avoid; }
figure img { max-width: 100%; max-height: 220mm; object-fit: contain; } figcaption { font-size: 10px; margin-top: 4px; }
.pdfnote { border: 1px dashed #666; padding: 8px; color: #444; } .pdfobj { width: 100%; height: 520px; border: 1px solid #ccc; margin-top: 6px; }
/* SEP (meniru cetakan SEP BPJS) */
.sep { font-size: 11.5px; } .sep-h { width: 100%; border-collapse: collapse; margin-bottom: 8px; } .sep-h .bp { width: 34%; vertical-align: middle; }
.bpjs { font-size: 17px; color: #0b6b3a; line-height: 1.1; } .bpjs b { color: #0b6b3a; } .bpjs small { display: block; font-size: 8px; color: #1d4e89; } .sep-h img { height: 42px; }
.sep-h .tt { text-align: left; font-size: 12px; } .sep-h .tt b { font-size: 15px; }
.sep-b { width: 100%; border-collapse: collapse; } .sep-b td { padding: 2.5px 3px; vertical-align: top; } .sep-b .l { width: 17%; } .sep-b .m { width: 2%; } .sep-b .v { width: 33%; } .sep-b .l2 { width: 11%; } .sep-b .v2 { width: 17%; }
.sep-f { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 14px; } .sep-f .nt { font-size: 9.5px; font-style: italic; max-width: 56%; }
.sep-f .ttd { text-align: center; font-size: 11px; } .sep-f .ttd span { display: block; } .sep-f .ttd .sp { height: 54px; } .cet { font-size: 9.5px; margin-top: 10px; }
/* sampul ringkasan */
.cover .hero { background: linear-gradient(120deg, var(--ac2), var(--ac)); color: #fff; padding: 14px 18px; display: flex; justify-content: space-between; gap: 16px; border-radius: 6px; margin-bottom: 10px; }
.cover .rs b { font-size: 16px; display: block; } .cover .rs span { font-size: 10px; opacity: .85; } .cover .doc { margin-top: 6px; font-size: 9px; letter-spacing: .16em; text-transform: uppercase; opacity: .85; }
.cover .meta { text-align: right; font-size: 10px; } .cover .meta b { display: block; font-size: 12px; margin-top: 2px; }
.pill { display: inline-block; border-radius: 999px; padding: 1px 9px; font-size: 10px; font-weight: 700; background: #ffffff2e; border: 1px solid #ffffff66; }
.kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 8px; } .kpi { border: 1px solid var(--line); border-top: 3px solid var(--ac); border-radius: 6px; padding: 6px 9px; background: var(--soft); }
.kpi b { display: block; font-size: 14px; } .kpi span { font-size: 9px; color: var(--mut); text-transform: uppercase; letter-spacing: .06em; }
.note { color: #8a5a00; font-size: 10px; margin: 4px auto; max-width: 210mm; }
/* toolbar layar */
.bar { position: sticky; top: 0; z-index: 9; background: #0f172a; color: #e2e8f0; padding: 8px 12px; display: flex; gap: 8px 12px; flex-wrap: wrap; align-items: center; font-size: 12px; }
.bar button, .bar a.btn { background: #06b6d4; color: #04222b; font-weight: 700; border: 0; border-radius: 6px; padding: 6px 13px; cursor: pointer; text-decoration: none; font: inherit; font-weight: 700; }
.chips { display: flex; gap: 5px; flex-wrap: wrap; } .chips label { cursor: pointer; } .chips input { display: none; }
.chips span { display: inline-block; padding: 2px 10px; border-radius: 999px; border: 1px solid #475569; color: #94a3b8; font-size: 11px; } .chips input:checked + span { background: #0e7490; border-color: #06b6d4; color: #fff; }
body.eco .cover .hero { background: #fff; color: #111; border: 1px solid #000; } body.eco .cover .pill { color: #111; border-color: #111; background: #fff; }
@media print { .noprint { display: none !important; } body { background: #fff; } .form { margin: 0; padding: 0; box-shadow: none; border-radius: 0; max-width: none; } }
</style>
</head>
<body>
<?php if (!$pdf): ?>
<div class="bar noprint">
  <button id="btnPrint" type="button">⬇ Unduh PDF</button>
  <?php if (!empty($o['has_dompdf'])): ?><a class="btn" href="?no_rawat=<?= F::e(rawurlencode($reg['no_rawat'])) ?>&format=pdf">PDF dari server</a><?php endif; ?>
  <label><input type="checkbox" id="eco"> Hemat tinta</label>
  <div class="chips">
  <?php foreach ($docs as $k => $dc): ?>
    <label><input type="checkbox" class="tg" value="<?= F::e($k) ?>" <?= $dc['on'] ? 'checked' : '' ?>><span><?= F::e($dc['label']) ?></span></label>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<div class="wrap">
<?php foreach ($docs as $k => $dc): ?>
<section class="doc" data-sec="<?= F::e($k) ?>"<?= $dc['on'] ? '' : ' style="display:none"' ?>><?= $dc['html'] ?></section>
<?php endforeach; ?>
</div>
<?php foreach (array_unique($d['notes']) as $nt): ?><div class="note noprint">⚠ <?= F::e($nt) ?></div><?php endforeach; ?>
<?php if (!$pdf): ?>
<script>
document.querySelectorAll('.tg').forEach(function (cb) {
  cb.addEventListener('change', function () {
    document.querySelectorAll('[data-sec="' + cb.value + '"]').forEach(function (e) { e.style.display = cb.checked ? '' : 'none'; });
  });
});
document.getElementById('eco').addEventListener('change', function (e) { document.body.classList.toggle('eco', e.target.checked); });
document.getElementById('btnPrint').addEventListener('click', function () { window.print(); });
(function () {   // catatan "bagian dilewati": tampilkan sebagai pop up di aplikasi induk bila berkas ini ditanamkan
  var notes = <?= json_encode(array_values(array_unique($d['notes'])), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  if (notes.length && window.parent !== window && window.parent.Notify) {
    window.parent.Notify.warn(notes, 'Sebagian data berkas klaim tidak dapat dimuat');
    document.querySelectorAll('.note').forEach(function (e) { e.remove(); });
  }
})();
if (new URLSearchParams(location.search).get('print') === '1') {
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 500); });
}
</script>
<?php endif; ?>
</body>
</html>
