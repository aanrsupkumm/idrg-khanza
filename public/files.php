<?php
declare(strict_types=1);
// Lampiran berkas digital tambahan: list | upload | delete | get
require __DIR__ . '/../bootstrap.php';
guard_ip();
start_session();

function jout(array $d, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!auth_ok()) jout(['ok' => false, 'error' => 'Sesi berakhir, silakan login ulang.'], 401);

$action = (string)($_GET['action'] ?? '');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost && !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) {
    jout(['ok' => false, 'error' => 'CSRF token tidak valid'], 403);
}

try {
    $store = new Idrg\BerkasStore(pdo(), cfg());

    switch ($action) {
        case 'list':
            $no = (string)($_GET['no_rawat'] ?? '');
            if (!preg_match('~^[0-9/]{8,25}$~', $no)) jout(['ok' => false, 'error' => 'no_rawat tidak valid'], 400);
            $rows = $store->list($no);
            foreach ($rows as &$r) $r['ukuran_txt'] = Idrg\BerkasHelper::size((int)$r['ukuran']);
            jout(['ok' => true, 'rows' => $rows,
                  'limits' => ['max' => Idrg\BerkasHelper::size($store->maxBytes()), 'allowed' => $store->allowed()]]);

        case 'upload':
            if (!$isPost) jout(['ok' => false, 'error' => 'POST diperlukan'], 405);
            if (empty($_FILES) && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
                jout(['ok' => false, 'error' => 'Ukuran total upload melebihi post_max_size (' . ini_get('post_max_size') . ') di php.ini.']);
            }
            $no = (string)($_POST['no_rawat'] ?? '');
            $judul = (string)($_POST['judul'] ?? '');
            $f = $_FILES['files'] ?? null;
            if (!$f) jout(['ok' => false, 'error' => 'Tidak ada file yang dipilih.']);
            $items = [];
            if (is_array($f['name'])) {
                foreach (array_keys($f['name']) as $i) {
                    $items[] = ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'size' => $f['size'][$i], 'error' => $f['error'][$i]];
                }
            } else {
                $items[] = $f;
            }
            $saved = [];
            $errors = [];
            foreach ($items as $it) {
                try {
                    // judul tunggal hanya dipakai bila satu file; jika banyak, judul = nama file
                    $saved[] = $store->save($no, count($items) === 1 ? $judul : '', $it);
                } catch (\RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
            jout(['ok' => !$errors || $saved, 'saved' => $saved, 'errors' => $errors,
                  'error' => $errors && !$saved ? implode(' | ', $errors) : null]);

        case 'delete':
            if (!$isPost) jout(['ok' => false, 'error' => 'POST diperlukan'], 405);
            $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
            $id = (int)($in['id'] ?? $_POST['id'] ?? 0);
            jout($store->delete($id) ? ['ok' => true] : ['ok' => false, 'error' => 'Lampiran tidak ditemukan']);

        case 'get':
            $row = $store->find((int)($_GET['id'] ?? 0));
            $path = $row ? $store->path($row) : '';
            if (!$row || !is_file($path)) { http_response_code(404); exit('Berkas tidak ditemukan'); }
            $dl = !empty($_GET['download']);
            header('Content-Type: ' . $row['mime']);
            header('Content-Length: ' . filesize($path));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=300');
            header('Content-Disposition: ' . ($dl ? 'attachment' : 'inline') . "; filename*=UTF-8''" . rawurlencode((string)$row['nama_asli']));
            readfile($path);
            exit;

        default:
            jout(['ok' => false, 'error' => 'Action tidak dikenal'], 404);
    }
} catch (\Throwable $e) {
    error_log('idrg files: ' . $e);
    jout(['ok' => false, 'error' => 'Kesalahan: ' . $e->getMessage()], 500);
}
