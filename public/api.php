<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';
guard_ip();
start_session();

function out(array $d, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Filter status bayar: Sudah (default) | Belum | Semua */
function bayar_param(): string
{
    $b = (string)($_GET['bayar'] ?? 'Sudah');
    return in_array($b, ['Sudah', 'Belum'], true) ? $b : '';
}

if (!auth_ok()) out(['ok' => false, 'error' => 'Sesi berakhir, silakan login ulang.'], 401);

$action = $_GET['action'] ?? '';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$in = $isPost ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];

if ($isPost && !hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF'] ?? '')) {
    out(['ok' => false, 'error' => 'CSRF token tidak valid'], 403);
}

try {
    $svc = service();
    switch ($action) {
        case 'list':
            $from = ($_GET['from'] ?? date('Y-m-d', strtotime('-7 days')));
            $to   = ($_GET['to'] ?? date('Y-m-d'));
            out(['ok' => true, 'rows' => $svc->list($from, $to, $_GET['lanjut'] ?? '', trim($_GET['q'] ?? ''), bayar_param())]);

        case 'summary':
            out(['ok' => true, 'data' => $svc->summary((string)($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))), (string)($_GET['to'] ?? date('Y-m-d')), bayar_param())]);

        case 'me':
            out(['ok' => true, 'nama' => (string)($_SESSION['nama'] ?? ''), 'user' => (string)($_SESSION['user'] ?? '')]);

        case 'log':
            $st = pdo()->query('SELECT id, method, nomor_sep, http_code, code, error_no, message, ms, created_at FROM idrg_log ORDER BY id DESC LIMIT 200');
            out(['ok' => true, 'rows' => $st->fetchAll()]);

        case 'log_detail':
            $st = pdo()->prepare('SELECT request_json, response_json FROM idrg_log WHERE id = ?');
            $st->execute([(int)($_GET['id'] ?? 0)]);
            out(['ok' => true, 'row' => $st->fetch() ?: null]);

        case 'preview':
            out(['ok' => true, 'data' => $svc->preview((string)($_GET['no_rawat'] ?? ''))]);

        case 'form':
            out(['ok' => true] + $svc->form((string)($_GET['no_rawat'] ?? '')));

        case 'state':
            out(['ok' => true] + $svc->state((string)($_GET['no_rawat'] ?? '')));

        case 'claim_status':
            out($svc->claimStatus((string)($_GET['no_rawat'] ?? '')));

        case 'icd':
            out(['ok' => true, 'rows' => $svc->searchIcd((string)($_GET['type'] ?? 'icd10'), (string)($_GET['q'] ?? ''), ($_GET['nonim'] ?? '') === '1', (string)($_GET['pane'] ?? ''))]);

        case 'save_claim':
            if (!$isPost) out(['ok' => false, 'error' => 'POST diperlukan'], 405);
            out($svc->saveClaim((string)($in['no_rawat'] ?? ''), (array)($in['form'] ?? [])));

        case 'group_idrg':
            if (!$isPost) out(['ok' => false, 'error' => 'POST diperlukan'], 405);
            out($svc->groupIdrg((string)($in['no_rawat'] ?? ''), isset($in['dx']) ? (array)$in['dx'] : null, isset($in['px']) ? (array)$in['px'] : null));

        case 'group_inacbg':
            if (!$isPost) out(['ok' => false, 'error' => 'POST diperlukan'], 405);
            out($svc->groupInacbg((string)($in['no_rawat'] ?? ''), isset($in['dx']) ? (array)$in['dx'] : null, isset($in['px']) ? (array)$in['px'] : null));

        case 'run':
            if (!$isPost) out(['ok' => false, 'error' => 'POST diperlukan'], 405);
            out($svc->run((string)($in['no_rawat'] ?? ''), (string)($in['step'] ?? ''), (array)($in['params'] ?? [])));

        case 'print':
            $b64 = $svc->printPdf((string)($_GET['no_rawat'] ?? ''));
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="klaim.pdf"');
            echo base64_decode($b64);
            exit;

        default:
            out(['ok' => false, 'error' => 'Action tidak dikenal'], 404);
    }
} catch (Idrg\EklaimException $e) {
    out(['ok' => false, 'error' => $e->getMessage(), 'error_no' => $e->errorNo, 'payload' => $e->payload], 200);
} catch (\InvalidArgumentException | \RuntimeException $e) {
    out(['ok' => false, 'error' => $e->getMessage()], 200);
} catch (\Throwable $e) {
    error_log('idrg: ' . $e);
    out(['ok' => false, 'error' => 'Kesalahan internal: ' . $e->getMessage()], 500);
}
