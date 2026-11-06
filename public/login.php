<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';
guard_ip();
start_session();
if (auth_ok()) { header('Location: ./'); exit; }

$cfg = cfg();
$rsNama = (string)($cfg['rs']['nama'] ?? '');
if ($rsNama === '') {
    try { $rsNama = (string)pdo()->query('SELECT nama_instansi FROM setting LIMIT 1')->fetchColumn(); } catch (\Throwable $e) { $rsNama = ''; }
}
$rsNama = $rsNama !== '' ? $rsNama : 'Rumah Sakit';
$logo = is_file(__DIR__ . '/assets/logo.png') ? 'assets/logo.png' : '';

if (empty($_SESSION['lcsrf'])) $_SESSION['lcsrf'] = bin2hex(random_bytes(16));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string)$_SESSION['lcsrf'], (string)($_POST['t'] ?? ''))) {
        $error = 'Sesi formulir kedaluwarsa, silakan coba lagi.';
    } else {
        try {
            $r = Idrg\Auth::attempt(pdo(), $cfg, (string)($_POST['user'] ?? ''), (string)($_POST['pass'] ?? ''), (string)($_SERVER['REMOTE_ADDR'] ?? ''));
        } catch (\Throwable $e) {
            error_log('idrg login: ' . $e);
            $r = ['ok' => false, 'error' => 'Tidak dapat memeriksa akun (cek koneksi database / tabel user Khanza).'];
        }
        if ($r['ok']) {
            session_regenerate_id(true);
            $_SESSION = ['ok' => true, 'user' => $r['nik'], 'nama' => $r['nama'], 'ktp' => $r['ktp'],
                         'csrf' => bin2hex(random_bytes(16)), 'last' => time()];
            header('Location: ./');
            exit;
        }
        $error = $r['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · Klaim E-Klaim iDRG</title>
<style>
:root{--primary:#2563eb;--primary-light:#60a5fa;--dark:#0f172a;--muted:#64748b;--border:#e2e8f0;--danger:#dc2626}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:"Segoe UI",system-ui,Roboto,sans-serif;min-height:100vh;overflow:hidden;display:flex;align-items:center;justify-content:center;position:relative;
 background:radial-gradient(circle at top left,rgba(37,99,235,.15),transparent 35%),radial-gradient(circle at bottom right,rgba(96,165,250,.18),transparent 35%),linear-gradient(135deg,#0f172a,#111827,#1e293b)}
.bg span{position:absolute;display:block;border-radius:50%;background:rgba(255,255,255,.04);animation:float 18s linear infinite;bottom:-150px}
.bg span:nth-child(1){left:10%;width:90px;height:90px;animation-duration:20s}.bg span:nth-child(2){left:30%;width:30px;height:30px;animation-duration:12s}
.bg span:nth-child(3){left:50%;width:70px;height:70px;animation-duration:18s}.bg span:nth-child(4){left:70%;width:120px;height:120px;animation-duration:25s}
.bg span:nth-child(5){left:85%;width:40px;height:40px;animation-duration:14s}
@keyframes float{0%{transform:translateY(0) rotate(0);opacity:0}10%{opacity:1}100%{transform:translateY(-110vh) rotate(360deg);opacity:0}}
.wrap{position:relative;z-index:10;width:100%;max-width:1050px;min-height:600px;display:grid;grid-template-columns:1.1fr .9fr;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);
 backdrop-filter:blur(30px);border-radius:28px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,.35);animation:fadeIn 1s ease;margin:18px}
@keyframes fadeIn{from{opacity:0;transform:translateY(20px) scale(.98)}to{opacity:1;transform:none}}
.left{padding:56px;color:#fff;display:flex;flex-direction:column;justify-content:center}
.logo{width:90px;height:90px;border-radius:24px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;margin-bottom:26px;font-weight:800;font-size:28px;animation:pulse 4s infinite}
.logo img{width:58px;height:58px;object-fit:contain}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.04)}}
.left h1{font-size:2.1rem;font-weight:800;line-height:1.25;margin-bottom:16px}.left p{color:rgba(255,255,255,.72);line-height:1.8;max-width:430px}
.feat{margin-top:34px;display:grid;gap:14px}.fi{display:flex;align-items:center;gap:16px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.06);border-radius:18px;padding:14px 18px;transition:.3s}
.fi:hover{transform:translateX(6px);background:rgba(255,255,255,.08)}
.fic{width:44px;height:44px;border-radius:14px;background:rgba(255,255,255,.08);display:grid;place-items:center;color:#93c5fd;flex:none}
.fi h6{font-size:.95rem;font-weight:600}.fi small{color:rgba(255,255,255,.6)}
.right{background:#fff;padding:52px 48px;display:flex;flex-direction:column;justify-content:center}
.badge{display:inline-flex;gap:8px;align-items:center;background:#eff6ff;color:#2563eb;padding:8px 14px;border-radius:999px;font-size:.8rem;font-weight:600;margin-bottom:20px;width:max-content}
.title{font-size:2rem;font-weight:800;color:var(--dark);margin-bottom:8px}.sub{color:var(--muted);margin-bottom:28px;line-height:1.7}
.fg{margin-bottom:20px}.fg label{display:block;margin-bottom:9px;font-size:.86rem;font-weight:600;color:#334155}
.ig{position:relative}.ig svg{position:absolute;top:50%;left:18px;transform:translateY(-50%);color:#94a3b8}
.ig input{width:100%;height:56px;border-radius:16px;border:1px solid var(--border);background:#f8fafc;padding:0 18px 0 52px;font-size:.95rem;transition:.3s}
.ig input:focus{outline:none;border-color:var(--primary);background:#fff;box-shadow:0 0 0 5px rgba(37,99,235,.12)}
.btn{width:100%;height:56px;border:0;border-radius:16px;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;transition:.3s;box-shadow:0 12px 24px rgba(37,99,235,.25)}
.btn:hover{transform:translateY(-2px);box-shadow:0 18px 32px rgba(37,99,235,.35)}
.alert{border-radius:16px;padding:14px 18px;margin-bottom:20px;font-size:.88rem;background:#fef2f2;color:var(--danger);border:1px solid #fecaca;animation:shake .4s ease}
@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-4px)}50%{transform:translateX(4px)}75%{transform:translateX(-2px)}}
.foot{margin-top:26px;text-align:center;color:#94a3b8;font-size:.75rem}
@media(max-width:980px){.wrap{grid-template-columns:1fr;max-width:480px}.left{display:none}}
</style>
</head>
<body>
<div class="bg"><span></span><span></span><span></span><span></span><span></span></div>
<div class="wrap">
  <div class="left">
    <div class="logo"><?php if ($logo): ?><img src="<?= h($logo) ?>" alt="Logo"><?php else: ?>iD<?php endif; ?></div>
    <h1>Klaim E-Klaim iDRG<br>&amp; INA-CBG</h1>
    <p>Bridging SIMRS Khanza ke E-Klaim: langkah klaim sama dengan aplikasi E-Klaim, data langsung dari Khanza, berkas klaim siap cetak.</p>
    <div class="feat">
      <div class="fi"><div class="fic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg></div><div><h6>Data Langsung dari Khanza</h6><small>SEP, diagnosa, tindakan, billing, resume &amp; asesmen</small></div></div>
      <div class="fi"><div class="fic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/></svg></div><div><h6>Akun Khanza</h6><small>Masuk dengan username &amp; password SIMRS yang sama</small></div></div>
      <div class="fi"><div class="fic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h9l3 3v15H6z"/><path d="M9 12h6M9 16h6"/></svg></div><div><h6>Berkas Klaim Siap Cetak</h6><small>Format form asli: SEP, resume, asesmen, CPPT, billing</small></div></div>
    </div>
  </div>
  <div class="right">
    <div class="badge">&#10003; Secure Login System</div>
    <div class="title">Silahkan Login</div>
    <div class="sub">Masukkan akun SIMRS Khanza Anda untuk melanjutkan ke modul klaim.</div>
    <?php if ($error): ?><noscript><div class="alert"><?= h($error) ?></div></noscript><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="t" value="<?= h($_SESSION['lcsrf']) ?>">
      <div class="fg"><label for="u">Username</label><div class="ig"><input id="u" name="user" type="text" placeholder="Username" required autofocus>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/></svg></div></div>
      <div class="fg"><label for="p">Password</label><div class="ig"><input id="p" name="pass" type="password" placeholder="Password" required>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg></div></div>
      <button class="btn" type="submit">Login</button>
    </form>
    <div class="foot">&copy; <?= date('Y') ?> <?= h($rsNama) ?></div>
  </div>
</div>
<script src="notify.js"></script>
<?php if ($error): ?><script>Notify.error([<?= json_encode($error, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>], 'Login gagal');</script><?php endif; ?>
</body>
</html>
