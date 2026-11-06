<?php
declare(strict_types=1);

// Polyfill agar berjalan di PHP 7.4+
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $h, string $n): bool { return $n === '' || strncmp($h, $n, strlen($n)) === 0; }
}
if (!function_exists('str_contains')) {
    function str_contains(string $h, string $n): bool { return $n === '' || strpos($h, $n) !== false; }
}

spl_autoload_register(function (string $c): void {
    if (strncmp($c, 'Idrg\\', 5) === 0) {
        $f = __DIR__ . '/src/' . str_replace('\\', '/', substr($c, 5)) . '.php';
        if (is_file($f)) require $f;
    }
});

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cfg(): array
{
    static $c = null;
    if ($c === null) {
        $f = __DIR__ . '/config/config.php';
        if (!is_file($f)) {
            if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'api.php' && !headers_sent()) {
                header('Location: setup.php');
                exit;
            }
            http_response_code(500);
            exit('Belum terpasang. Buka setup.php');
        }
        $c = require $f;
    }
    return $c;
}

function pdo(): PDO
{
    static $p = null;
    if ($p === null) {
        $d = cfg()['db'];
        $p = new PDO(
            "mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4",
            $d['user'], $d['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
    }
    return $p;
}

/** Batasi akses berdasarkan IP (prefix match). */
function guard_ip(): void
{
    $allowed = cfg()['auth']['allowed_ips'] ?? [];
    if (!$allowed) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    foreach ($allowed as $prefix) {
        if ($ip === $prefix || str_starts_with($ip, rtrim($prefix, '*'))) return;
    }
    http_response_code(403);
    exit('Akses ditolak');
}

function start_session(): void
{
    session_set_cookie_params(0, '/', '', !empty($_SERVER['HTTPS']), true);
    session_start();
}

/** Sesi valid + batas idle (menit) dari config auth.session_minutes. */
function auth_ok(): bool
{
    if (empty($_SESSION['ok'])) return false;
    $idle = (int)(cfg()['auth']['session_minutes'] ?? 120) * 60;
    if (!empty($_SESSION['last']) && time() - (int)$_SESSION['last'] > $idle) { $_SESSION = []; return false; }
    $_SESSION['last'] = time();
    return true;
}

function require_login(bool $json): void
{
    if (auth_ok()) return;
    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Sesi berakhir, silakan login ulang.']);
    } else {
        header('Location: login.php');
    }
    exit;
}

function service(): Idrg\ClaimService
{
    $c = cfg();
    // NIK coder dari akun yang login (No. KTP pegawai) bila diaktifkan
    if (!empty($c['eklaim']['coder_dari_login']) && !empty($_SESSION['ktp'])) $c['eklaim']['coder_nik'] = (string)$_SESSION['ktp'];
    $log = new Idrg\Logger(pdo());
    return new Idrg\ClaimService(
        new Idrg\KhanzaRepository(pdo(), $c),
        new Idrg\EklaimClient($c['eklaim'], $log),
        pdo(), $c
    );
}
