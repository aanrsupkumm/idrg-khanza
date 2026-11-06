<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';
guard_ip();
start_session();
require_login(false);

$no = trim((string)($_GET['no_rawat'] ?? ''));
if (!preg_match('~^[0-9/]{8,25}$~', $no)) { http_response_code(400); exit('no_rawat tidak valid'); }

$cfg = cfg();
$d = (new Idrg\BerkasRepository(pdo(), $cfg))->collect($no);
$store = new Idrg\BerkasStore(pdo(), $cfg);
if (empty($d['reg'])) { http_response_code(404); exit('Data registrasi tidak ditemukan: ' . h($no)); }

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $cfg['berkas']['base_url'] ?? ($scheme . '://' . $_SERVER['HTTP_HOST']);

/** URL file Khanza (webapps) dengan segmen ter-encode & tanpa ".." */
function berkas_url(string $base, string $dir, string $path): string
{
    $seg = array_filter(explode('/', str_replace('\\', '/', $path)), function ($s) { return $s !== '' && $s !== '..'; });
    return rtrim($base, '/') . '/' . trim($dir, '/') . '/' . implode('/', array_map('rawurlencode', $seg));
}

function render_berkas(array $d, array $o, string $base, array $cfg): string
{
    ob_start();
    include __DIR__ . '/../views/berkas.php';
    return (string)ob_get_clean();
}

if (($_GET['format'] ?? '') === 'pdf') {
    // Opsional: PDF dari server jika Dompdf terpasang (vendor/autoload.php). Tidak wajib.
    $auto = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($auto)) { http_response_code(501); exit('Dompdf tidak terpasang. Gunakan tombol "Unduh PDF" (disimpan dari browser).'); }
    require $auto;
    $opt = new \Dompdf\Options();
    $opt->set('isRemoteEnabled', true);
    $opt->set('defaultFont', 'Helvetica');
    $pdf = new \Dompdf\Dompdf($opt);
    $pdf->loadHtml(render_berkas($d, ['pdf' => true, 'store' => $store], $base, $cfg));
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();
    $pdf->stream('Berkas_Klaim_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $no) . '.pdf', ['Attachment' => true]);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo render_berkas($d, ['pdf' => false, 'store' => $store, 'has_dompdf' => is_file(dirname(__DIR__) . '/vendor/autoload.php')], $base, $cfg);
