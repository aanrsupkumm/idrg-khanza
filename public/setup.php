<?php
declare(strict_types=1);
// Wizard pemasangan: cek persyaratan, uji DB, buat tabel, tulis config/config.php.
// Terkunci otomatis setelah selesai (config/installed.lock).
$root = dirname(__DIR__);
$lock = $root . '/config/installed.lock';

function priv_ip(string $ip): bool
{
    return $ip === '127.0.0.1' || $ip === '::1' || (bool)preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $ip);
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if (is_file($lock)) { http_response_code(403); exit('Sudah terpasang. Hapus config/installed.lock bila ingin menjalankan ulang.'); }
if (!priv_ip($_SERVER['REMOTE_ADDR'] ?? '')) { http_response_code(403); exit('Setup hanya dari jaringan lokal.'); }

$checks = [
    'PHP >= 7.4 (sekarang ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'Ekstensi pdo_mysql' => extension_loaded('pdo_mysql'),
    'Ekstensi mbstring'  => extension_loaded('mbstring'),
    'Ekstensi json'      => extension_loaded('json'),
    'Ekstensi openssl (wajib untuk mode produksi)' => extension_loaded('openssl'),
    'Folder config/ dapat ditulis' => is_writable($root . '/config'),
];
$optional = ['Ekstensi curl (opsional, ada fallback)' => extension_loaded('curl')];
$ok = !in_array(false, $checks, true);

$err = ''; $manual = '';
$v = $_POST + ['db_host' => 'localhost', 'db_port' => '3306', 'db_name' => 'sik', 'db_user' => 'root', 'db_pass' => '',
    'ek_url' => 'http://192.168.1.10', 'ek_debug' => '1', 'ek_key' => '', 'nik' => '', 'kode_tarif' => 'AP', 'rs_nama' => '', 'k_user' => 'nur', 'k_pass' => 'windi', 'coder_login' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') $v['ek_debug'] = isset($_POST['ek_debug']) ? '1' : '';

if ($ok && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($v['ek_debug']) && !preg_match('/^[0-9a-fA-F]{64}$/', $v['ek_key'])) throw new RuntimeException('Kunci E-Klaim harus 64 karakter hex untuk mode produksi.');

        $pdo = new PDO("mysql:host={$v['db_host']};port={$v['db_port']};dbname={$v['db_name']};charset=utf8mb4", $v['db_user'], $v['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (['reg_periksa', 'pasien', 'bridging_sep', 'diagnosa_pasien', 'billing', 'user', 'pegawai'] as $t) {
            if (!$pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn()) throw new RuntimeException("Tabel Khanza '$t' tidak ditemukan: bukan database Khanza?");
        }
        $ok = $pdo->prepare('SELECT COUNT(*) FROM `user` u JOIN pegawai p ON AES_DECRYPT(u.id_user, ?) = p.nik');
        $ok->execute([$v['k_user']]);
        if ((int)$ok->fetchColumn() === 0) throw new RuntimeException('Kunci AES id_user tidak cocok: tidak ada akun Khanza yang bisa didekripsi. Periksa kunci (bawaan Khanza: nur / windi).');
        $sql = preg_replace('/^--.*$/m', '', (string)file_get_contents($root . '/sql/schema.sql'));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) $pdo->exec($stmt);

        $cfg = require $root . '/config/config.sample.php';
        $cfg['db'] = ['host' => $v['db_host'], 'port' => (int)$v['db_port'], 'name' => $v['db_name'], 'user' => $v['db_user'], 'pass' => $v['db_pass']];
        $cfg['auth']['key_user'] = $v['k_user'];
        $cfg['auth']['key_pass'] = $v['k_pass'];
        $cfg['eklaim'] = ['base_url' => rtrim($v['ek_url'], '/'), 'endpoint' => '/E-Klaim/ws.php', 'debug_mode' => !empty($v['ek_debug']),
            'key' => $v['ek_key'], 'timeout' => 30, 'coder_nik' => $v['nik'], 'coder_dari_login' => !empty($v['coder_login'])];
        $cfg['rs']['kode_tarif'] = $v['kode_tarif'];
        if ($v['rs_nama'] !== '') $cfg['rs']['nama'] = $v['rs_nama'];

        $code = "<?php\nreturn " . var_export($cfg, true) . ";\n";
        if (@file_put_contents($root . '/config/config.php', $code) === false) { $manual = $code; throw new RuntimeException('Tidak bisa menulis config.php. Salin isi di bawah secara manual.'); }
        @file_put_contents($lock, date('c'));
        header('Location: index.php');
        exit;
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pemasangan Bridging iDRG</title>
<style>
:root{--bg:#f6f7f9;--fg:#1c2330;--card:#fff;--bd:#d9dde3;--ok:#14804a;--err:#c02626}
@media(prefers-color-scheme:dark){:root{--bg:#12151b;--fg:#e8ebf0;--card:#1a1f28;--bd:#2b3240;--ok:#3ccf84;--err:#ff7a7a}}
body{margin:0;background:var(--bg);color:var(--fg);font:14px/1.5 system-ui,sans-serif}
main{max-width:640px;margin:24px auto;padding:0 14px}.card{background:var(--card);border:1px solid var(--bd);border-radius:10px;padding:16px;margin-bottom:14px}
h1{font-size:18px}h2{font-size:14px;margin:0 0 8px}label{display:block;margin:8px 0 2px;font-size:12px;opacity:.8}
input[type=text],input[type=password]{width:100%;padding:7px 9px;border:1px solid var(--bd);border-radius:6px;background:var(--bg);color:var(--fg);font:inherit}
.g{display:grid;grid-template-columns:1fr 1fr;gap:10px}button{background:#0f6fde;color:#fff;border:0;border-radius:6px;padding:9px 16px;font:inherit;cursor:pointer}
.ok{color:var(--ok)}.no{color:var(--err)}textarea{width:100%;height:200px}
</style></head><body><main>
<h1>Pemasangan Bridging SIMRS Khanza → E-Klaim iDRG</h1>
<div class="card"><h2>1. Persyaratan</h2>
<?php foreach ($checks + $optional as $l => $r): ?><div class="<?= $r ? 'ok' : 'no' ?>"><?= $r ? '✔' : '✘' ?> <?= e($l) ?></div><?php endforeach; ?>
</div>
<?php if ($ok): ?>
<form method="post">
<?php if ($err): ?><div class="card no"><?= e($err) ?></div><?php endif; ?>
<?php if ($manual): ?><div class="card"><textarea readonly><?= e($manual) ?></textarea></div><?php endif; ?>
<div class="card"><h2>2. Database Khanza</h2><small>Biasanya sama dengan koneksi aplikasi Khanza (database <b>sik</b>).</small>
  <div class="g"><div><label>Host</label><input type="text" name="db_host" value="<?= e($v['db_host']) ?>"></div><div><label>Port</label><input type="text" name="db_port" value="<?= e($v['db_port']) ?>"></div>
  <div><label>Nama database</label><input type="text" name="db_name" value="<?= e($v['db_name']) ?>"></div><div><label>User</label><input type="text" name="db_user" value="<?= e($v['db_user']) ?>"></div></div>
  <label>Password</label><input type="password" name="db_pass" value="<?= e($v['db_pass']) ?>" autocomplete="off"></div>
<div class="card"><h2>3. E-Klaim iDRG</h2>
  <label>URL server E-Klaim</label><input type="text" name="ek_url" value="<?= e($v['ek_url']) ?>">
  <label><input type="checkbox" name="ek_debug" value="1" <?= !empty($v['ek_debug']) ? 'checked' : '' ?>> Mode debug (JSON polos, untuk uji coba)</label>
  <label>Kunci enkripsi (64 hex, wajib bila mode debug dimatikan)</label><input type="text" name="ek_key" value="<?= e($v['ek_key']) ?>" autocomplete="off">
  <div class="g"><div><label>NIK coder</label><input type="text" name="nik" value="<?= e($v['nik']) ?>"></div><div><label>Kode tarif</label><input type="text" name="kode_tarif" value="<?= e($v['kode_tarif']) ?>"></div></div>
  <label>Nama RS di kop berkas (kosong = ambil dari tabel setting Khanza)</label><input type="text" name="rs_nama" value="<?= e($v['rs_nama']) ?>"></div>
<div class="card"><h2>4. Login (akun SIMRS Khanza)</h2><small>Pengguna masuk dengan username &amp; password Khanza. Kunci AES bawaan Khanza: <b>nur</b> / <b>windi</b>.</small>
  <div class="g"><div><label>Kunci AES id_user</label><input type="text" name="k_user" value="<?= e($v['k_user']) ?>"></div><div><label>Kunci AES password</label><input type="text" name="k_pass" value="<?= e($v['k_pass']) ?>"></div></div>
  <label><input type="checkbox" name="coder_login" value="1" <?= !empty($v['coder_login']) ? 'checked' : '' ?>> Pakai No. KTP pegawai yang login sebagai NIK coder (menggantikan NIK coder di atas)</label></div>
<button>Pasang</button>
</form>
<?php endif; ?>
</main></body></html>
