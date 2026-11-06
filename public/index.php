<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';
guard_ip();
start_session();
if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: login.php'); exit; }
require_login(false);
$nama = (string)($_SESSION['nama'] ?? '');
$nik = (string)($_SESSION['user'] ?? '');
$rsNama = (string)(cfg()['rs']['nama'] ?? '');
if ($rsNama === '') {
    try { $rsNama = (string)pdo()->query('SELECT nama_instansi FROM setting LIMIT 1')->fetchColumn(); } catch (\Throwable $e) { $rsNama = ''; }
}
$logo = is_file(__DIR__ . '/assets/logo.png') ? 'assets/logo.png' : '';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Klaim E-Klaim iDRG · <?= h($rsNama) ?></title>
<style>
:root{--bg:#f1f5f9;--fg:#0f172a;--mut:#64748b;--card:#fff;--bd:#e2e8f0;--pri:#0e7490;--pri2:#164e63;--ok:#15803d;--err:#b91c1c;--warn:#b45309;--soft:#f8fafc;--sh:0 1px 3px #0f172a14}
@media (prefers-color-scheme:dark){:root{--bg:#0b1118;--fg:#e6edf3;--mut:#8b9bb0;--card:#131b25;--bd:#243040;--pri:#22b8d6;--pri2:#7dd3e8;--ok:#3ccf84;--err:#ff7a7a;--warn:#f0b24c;--soft:#0f1620;--sh:none}}
*{box-sizing:border-box}body{margin:0;font:14px/1.5 "Segoe UI",system-ui,Roboto,sans-serif;background:var(--bg);color:var(--fg)}
a{color:var(--pri)}
.brand b{display:block;line-height:1.1}.brand small{opacity:.8}
main{max-width:1320px;margin:0 auto;padding:18px 22px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:12px;box-shadow:var(--sh)}
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:14px}
.stat{padding:12px 14px;border-left:4px solid var(--bd)}.stat b{font-size:22px;display:block;line-height:1.1}.stat span{font-size:12px;color:var(--mut)}
.stat.s1{border-color:var(--mut)}.stat.s2{border-color:var(--warn)}.stat.s3{border-color:var(--pri)}.stat.s4{border-color:var(--ok)}.stat.s5{border-color:var(--err)}
.filters{display:flex;gap:8px;flex-wrap:wrap;padding:12px;margin-bottom:14px;align-items:center}
input,select,textarea{font:inherit;color:inherit;background:var(--card);border:1px solid var(--bd);border-radius:8px;padding:7px 10px}
input:focus,select:focus{outline:2px solid var(--pri);outline-offset:-1px}
.btn{font:inherit;cursor:pointer;border:1px solid var(--bd);background:var(--card);color:var(--fg);border-radius:8px;padding:7px 13px;transition:.12s}
.btn:hover{border-color:var(--pri);color:var(--pri)}.btn.pri{background:var(--pri);border-color:var(--pri);color:#fff}.btn.pri:hover{filter:brightness(1.1);color:#fff}
.btn.danger{border-color:var(--warn);color:var(--warn)}.btn.sm{padding:4px 10px;font-size:13px}.btn:disabled{opacity:.5;cursor:wait}.btn.ghost{border-color:transparent;background:transparent}
.tw{overflow-x:auto}table{border-collapse:collapse;width:100%}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid var(--bd);vertical-align:middle}
th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--mut);background:var(--soft);white-space:nowrap}
tbody tr:hover{background:var(--soft)}td small{display:block;color:var(--mut)}
.chip{display:inline-block;font-size:11px;font-weight:600;padding:1px 9px;border-radius:999px;background:var(--soft);border:1px solid var(--bd);color:var(--mut)}
.chip.ranap{color:#7c3aed;border-color:#7c3aed55}.chip.ralan{color:var(--pri);border-color:var(--pri)}
.ph{display:flex;gap:4px;flex-wrap:nowrap}.ph span{font-size:11px;padding:1px 8px;border-radius:999px;border:1px solid var(--bd);color:var(--mut);white-space:nowrap}
.ph span:before{content:"";display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--bd);margin-right:5px}
.ph .ok{color:var(--ok);border-color:var(--ok)}.ph .ok:before{background:var(--ok)}.ph .err{color:var(--err);border-color:var(--err)}.ph .err:before{background:var(--err)}.ph .part{color:var(--warn);border-color:var(--warn)}.ph .part:before{background:var(--warn)}
.acts{display:flex;gap:6px}.chip.st-baru{color:var(--mut)}.chip.st-proses{color:var(--warn);border-color:var(--warn)}.chip.st-final{color:var(--pri);border-color:var(--pri)}.chip.st-kirim{color:var(--ok);border-color:var(--ok)}
dialog{width:min(1240px,97vw);height:min(92vh,920px);border:1px solid var(--bd);border-radius:14px;background:var(--card);color:var(--fg);padding:0;overflow:hidden}
dialog[open]{display:flex;flex-direction:column}dialog::backdrop{background:#020b1399}
.dh{display:flex;justify-content:space-between;align-items:flex-start;padding:12px 18px;border-bottom:1px solid var(--bd)}.dh b{font-size:16px}.dh .sub{color:var(--mut);font-size:12px}
.tabs{display:flex;gap:2px;padding:0 14px;border-bottom:1px solid var(--bd);background:var(--soft)}
.tabs button{font:inherit;border:0;background:none;color:var(--mut);padding:10px 16px;cursor:pointer;border-bottom:3px solid transparent;font-weight:600}
.tabs button.on{color:var(--pri);border-color:var(--pri)}
.db{flex:1;min-height:0;overflow:auto;padding:16px 18px}.db.flush{padding:0;overflow:hidden}
.split{display:grid;grid-template-columns:1fr 330px;height:100%}@media(max-width:980px){.split{grid-template-columns:1fr;grid-template-rows:60% 40%}}
.pv{display:flex;flex-direction:column;min-width:0;min-height:0;border-right:1px solid var(--bd)}
.pvbar{display:flex;gap:6px;padding:8px 10px;border-bottom:1px solid var(--bd);background:var(--soft);flex-wrap:wrap}
.pv iframe{flex:1;width:100%;border:0;background:#cbd5e1}
.up{padding:14px;overflow:auto}.up h4{margin:0 0 8px}
.drop{border:2px dashed var(--bd);border-radius:10px;padding:14px;text-align:center;color:var(--mut);cursor:pointer;transition:.12s}.drop.over{border-color:var(--pri);background:var(--soft);color:var(--pri)}
.up input[type=text]{width:100%;margin:8px 0}progress{width:100%;height:8px;margin-top:6px}
.fl{list-style:none;margin:10px 0 0;padding:0}.fl li{display:flex;gap:8px;align-items:center;padding:7px 0;border-bottom:1px solid var(--bd)}
.fl .nm{flex:1;min-width:0}.fl .nm b{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.fl small{color:var(--mut)}
.ty{flex:0 0 34px;height:34px;border-radius:8px;background:var(--soft);border:1px solid var(--bd);display:grid;place-items:center;font-size:10px;font-weight:700;color:var(--pri)}
@media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr)}}

.app{display:grid;grid-template-columns:262px 1fr;min-height:100vh}
.side{position:sticky;top:0;height:100vh;display:flex;flex-direction:column;background:linear-gradient(185deg,#0b2733,#164e63);color:#d3e4eb;z-index:30}
.sb-brand{display:flex;gap:11px;align-items:center;padding:18px 18px 14px;border-bottom:1px solid #ffffff1c}
.sb-logo{width:40px;height:40px;border-radius:11px;background:#ffffff22;display:grid;place-items:center;font-weight:800;color:#fff;overflow:hidden;flex:none}.sb-logo img{width:30px;height:30px;object-fit:contain}
.sb-brand b{display:block;color:#fff;line-height:1.15;font-size:14px}.sb-brand small{opacity:.7;font-size:11px}
.side nav{flex:1;overflow:auto;padding:10px 10px 16px}
.side .grp{font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;opacity:.55;margin:16px 10px 6px}
.side a.l{display:flex;align-items:center;justify-content:space-between;gap:8px;color:#d3e4eb;text-decoration:none;padding:8px 12px;border-radius:9px;font-size:13.5px}
.side a.l:hover{background:#ffffff14}.side a.l.on{background:#ffffff24;color:#fff;font-weight:600}
.side a.l.sub{padding-left:30px;font-size:13px;opacity:.9}
.bd{background:#f59e0b;color:#1c1300;font-size:11px;font-weight:700;border-radius:999px;padding:0 7px;min-width:20px;text-align:center}.bd:empty{display:none}
.usr{display:flex;gap:10px;align-items:center;padding:12px 14px;border-top:1px solid #ffffff1c}
.av{width:36px;height:36px;border-radius:50%;background:#0e7490;color:#fff;display:grid;place-items:center;font-weight:700;flex:none}
.usr b{display:block;color:#fff;font-size:13px;line-height:1.2}.usr small{opacity:.65;font-size:11px}.usr a{margin-left:auto;color:#fbbf24;font-size:12px;text-decoration:none}
.main{min-width:0}
.tbar{display:flex;align-items:center;gap:12px;padding:12px 24px;background:var(--card);border-bottom:1px solid var(--bd);position:sticky;top:0;z-index:20}
.tbar h1{font-size:17px;margin:0;font-weight:700}.tbar small{color:var(--mut)}#burger{display:none}
.stats .stat{cursor:pointer;transition:.12s}.stats .stat:hover{transform:translateY(-2px)}.stats .stat.act{outline:2px solid var(--pri)}
.hm{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}@media(max-width:1000px){.hm{grid-template-columns:1fr}}
.hcard{padding:18px}.hcard h3{margin:0 0 4px;font-size:16px}.hcard .sub{color:var(--mut);font-size:12.5px;margin-bottom:14px}
.hn{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}.hn a{text-decoration:none;color:inherit;border:1px solid var(--bd);border-radius:10px;padding:10px;text-align:center;background:var(--soft)}
.hn a:hover{border-color:var(--pri)}.hn b{display:block;font-size:22px;line-height:1.1}.hn span{font-size:11.5px;color:var(--mut)}
.hn a.warn b{color:var(--warn)}.hn a.bad b{color:var(--err)}.hn a.good b{color:var(--ok)}
@media(max-width:900px){.app{grid-template-columns:1fr}.side{position:fixed;left:0;top:0;width:262px;transform:translateX(-100%);transition:.2s}.side.open{transform:none;box-shadow:0 0 0 100vmax #0007}#burger{display:inline-block}.hn{grid-template-columns:repeat(3,1fr)}}
main#content{max-width:1380px;margin:0 auto;padding:18px 24px}

</style>
<link rel="stylesheet" href="klaim.css">
<script src="notify.js"></script>
</head>
<body>
<div class="app">
<aside class="side" id="side">
  <div class="sb-brand"><div class="sb-logo"><?php if ($logo): ?><img src="<?= h($logo) ?>" alt=""><?php else: ?>iD<?php endif; ?></div>
    <div><b><?= h($rsNama) ?></b><small>Klaim E-Klaim iDRG · INA-CBG</small></div></div>
  <nav>
    <a class="l" data-r="beranda" href="#/beranda">Beranda</a>
    <div class="grp">Rawat Jalan</div>
    <a class="l" data-r="rj" href="#/rj">Klaim Rawat Jalan <span class="bd" id="bd-rj"></span></a>
    <a class="l sub" data-r="rj/baru" href="#/rj/baru">Belum diproses</a>
    <a class="l sub" data-r="rj/proses" href="#/rj/proses">Dalam proses</a>
    <a class="l sub" data-r="rj/final" href="#/rj/final">Final</a>
    <a class="l sub" data-r="rj/kirim" href="#/rj/kirim">Terkirim</a>
    <div class="grp">Rawat Inap</div>
    <a class="l" data-r="ri" href="#/ri">Klaim Rawat Inap <span class="bd" id="bd-ri"></span></a>
    <a class="l sub" data-r="ri/baru" href="#/ri/baru">Belum diproses</a>
    <a class="l sub" data-r="ri/proses" href="#/ri/proses">Dalam proses</a>
    <a class="l sub" data-r="ri/final" href="#/ri/final">Final</a>
    <a class="l sub" data-r="ri/kirim" href="#/ri/kirim">Terkirim</a>
    <div class="grp">Lainnya</div>
    <a class="l" data-r="log" href="#/log">Log E-Klaim</a>
  </nav>
  <div class="usr"><div class="av"><?= h(mb_strtoupper(mb_substr($nama !== '' ? $nama : $nik, 0, 1))) ?></div>
    <div><b><?= h($nama !== '' ? $nama : $nik) ?></b><small>NIK <?= h($nik) ?></small></div><a href="?logout=1">Keluar</a></div>
</aside>

<div class="main">
  <div class="tbar"><button class="btn sm" id="burger" aria-label="Menu">☰</button><h1 id="ptitle">Beranda</h1><small id="psub"></small></div>
  <main id="content">

    <section id="v-beranda">
      <div class="card filters"><span class="muted">Periode ringkasan:</span><input type="date" id="sfrom"><input type="date" id="sto"><select id="sbayar" title="Status bayar"><option value="Sudah">Sudah bayar</option><option value="Belum">Belum bayar</option><option value="Semua">Semua status bayar</option></select><button class="btn pri" id="sgo">Tampilkan</button></div>
      <div class="hm" id="hm"></div>
    </section>

    <section id="v-list" hidden>
      <div class="stats" id="stats"></div>
      <div class="card filters">
        <input type="date" id="from"><input type="date" id="to">
        <select id="fbayar" title="Status bayar"><option value="Sudah">Sudah bayar</option><option value="Belum">Belum bayar</option><option value="Semua">Semua status bayar</option></select>
        <input id="q" placeholder="Cari nama / no rawat / RM / SEP / no. kartu" size="36">
        <button class="btn pri" id="go">Tampilkan</button>
      </div>
      <div class="card tw"><table><thead><tr id="th"></tr></thead><tbody id="tb"></tbody></table></div>
    </section>

    <section id="v-log" hidden>
      <div class="card tw"><table><thead><tr><th>Waktu</th><th>Method</th><th>No. SEP</th><th>Kode</th><th>Pesan</th><th>ms</th></tr></thead><tbody id="lb"></tbody></table></div>
      <pre id="ld" hidden style="margin-top:12px"></pre>
    </section>
  </main>
</div>
</div>

<dialog id="dlg">
  <div class="dh"><div><b id="dt"></b><div class="sub" id="ds"></div></div><button class="btn ghost" id="dx" title="Tutup">✕</button></div>
  <nav class="tabs"><button class="on" data-tab="proses">Proses klaim</button><button data-tab="berkas">Berkas klaim &amp; lampiran</button></nav>

  <div class="db" id="tab-proses"><div id="klaim"></div></div>

  <div class="db flush" id="tab-berkas" hidden>
    <div class="split">
      <div class="pv">
        <div class="pvbar">
          <button class="btn pri sm" id="bPdf">⬇ Unduh PDF</button>
          <button class="btn sm" id="bReload">Muat ulang</button>
          <button class="btn sm" id="bTab">Buka di tab baru</button>
        </div>
        <iframe id="bf" title="Preview berkas klaim"></iframe>
      </div>
      <aside class="up">
        <h4>Lampiran berkas digital tambahan</h4>
        <div class="drop" id="drop">Seret file ke sini atau klik untuk memilih<br><small id="lim"></small></div>
        <input type="file" id="ufile" multiple hidden accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
        <input type="text" id="ujudul" placeholder="Judul lampiran (mis. Surat rujukan, Hasil EKG)">
        <div id="picked" style="font-size:12px;color:var(--mut)"></div>
        <button class="btn pri" id="bUp" style="width:100%;margin-top:6px">Unggah</button>
        <progress id="upg" max="100" value="0" hidden></progress>
        <ul class="fl" id="flist"></ul>
      </aside>
    </div>
  </div>
</dialog>
<script>
const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
const $ = id => document.getElementById(id);
let ROWS = [], cur = null;

function el(t, a = {}, ...k){ const e = document.createElement(t);
  for (const [x, v] of Object.entries(a)) { if (v === false || v === null || v === undefined) continue;
    if (x === 'class') e.className = v; else if (x.startsWith('on')) e[x] = v; else e.setAttribute(x, v === true ? '' : v); }
  e.append(...k.flat().filter(v => v !== null && v !== undefined && v !== false)); return e; }
async function api(action, body, query){
  const qs = query ? '&' + new URLSearchParams(query) : '';
  const o = body ? {method:'POST', headers:{'Content-Type':'application/json','X-CSRF':CSRF}, body:JSON.stringify(body)} : {};
  let r, txt;
  try { r = await fetch('api.php?action=' + action + qs, o); txt = await r.text(); }
  catch (e) { Notify.error(['Tidak dapat menghubungi server aplikasi. Periksa jaringan atau layanan web server. (' + e.message + ')'], 'Koneksi gagal'); return {ok:false, error:'Koneksi gagal', notified:true}; }
  if (r.status === 401) { Notify.error(['Sesi berakhir. Silakan login ulang.'], 'Sesi berakhir', {onClose: () => { location.href = 'login.php'; }}); return {ok:false, error:'Sesi berakhir', notified:true}; }
  try { return JSON.parse(txt); }
  catch (e) { Notify.error(['Server mengirim respons yang tidak valid (HTTP ' + r.status + '): ' + txt.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220)], 'Respons server tidak valid'); return {ok:false, error:'Respons tidak valid', notified:true}; }
}
const fail = d => { if (!d.notified) Notify.error([d.error || 'Terjadi kesalahan.']); };
const isDone = s => s && (s.status === 'ok' || s.status === 'skip');
const BLN = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
const fmtD = s => { const m = String(s || '').match(/^(\d{4})-(\d\d)-(\d\d)/); return m ? (+m[3]) + ' ' + BLN[+m[2]-1] + ' ' + m[1] : (s || '-'); };
const iso = d => d.toISOString().slice(0, 10);
function rowState(r){
  const s = r.steps || {};
  if (isDone(s.send)) return ['kirim', 'Terkirim'];
  if (isDone(s.claim_final)) return ['final', 'Final'];
  if (isDone(s.inacbg_final)) return ['proses', 'Final INACBG'];
  if (isDone(s.inacbg_group1)) return ['proses', 'Grouping INACBG'];
  if (isDone(s.idrg_final)) return ['proses', 'Final iDRG'];
  if (isDone(s.idrg_group1)) return ['proses', 'Grouping iDRG'];
  if (isDone(s.set_claim_data)) return ['proses', 'Tersimpan'];
  return ['baru', 'Belum diproses'];
}

/* ---------- modul & rute (hash) ---------- */
const MODS = {
  rj: {lanjut:'Ralan', title:'Klaim Rawat Jalan', days:7,  cols:['Pasien','Tgl SEP','Poli','Dokter','No. SEP','Bayar','CBG','Status','']},
  ri: {lanjut:'Ranap', title:'Klaim Rawat Inap',  days:30, cols:['Pasien','Tgl Masuk','Tgl Pulang','Dokter','No. SEP','Bayar','CBG','Status','']}
};
const FIL = {};
Object.keys(MODS).forEach(k => FIL[k] = {from: iso(new Date(Date.now() - MODS[k].days * 864e5)), to: iso(new Date()), q: '', stat: '', bayar: 'Sudah'});
const STAT_LABEL = {baru:'Belum diproses', proses:'Dalam proses', final:'Final', kirim:'Terkirim'};
let ROUTE = {v:'beranda', f:''}, SUM = null;

function parseHash(){ const p = location.hash.replace(/^#\/?/, '').split('/'); return {v: ['beranda','rj','ri','log'].includes(p[0]) ? p[0] : 'beranda', f: STAT_LABEL[p[1]] ? p[1] : ''}; }
function route(){
  ROUTE = parseHash(); $('side').classList.remove('open');
  const key = ROUTE.v + (ROUTE.f ? '/' + ROUTE.f : '');
  document.querySelectorAll('.side a[data-r]').forEach(a => a.classList.toggle('on', a.dataset.r === key));
  ['beranda','list','log'].forEach(v => $('v-' + v).hidden = (v === 'list' ? !MODS[ROUTE.v] : v !== ROUTE.v));
  if (MODS[ROUTE.v]) {
    const m = MODS[ROUTE.v], F = FIL[ROUTE.v]; F.stat = ROUTE.f;
    $('ptitle').textContent = m.title; $('psub').textContent = ROUTE.f ? '· ' + STAT_LABEL[ROUTE.f] : '';
    $('from').value = F.from; $('to').value = F.to; $('q').value = F.q; $('fbayar').value = F.bayar;
    $('th').replaceChildren(...m.cols.map(c => el('th', {}, c)));
    load();
  } else if (ROUTE.v === 'log') { $('ptitle').textContent = 'Log E-Klaim'; $('psub').textContent = '200 aktivitas terakhir'; loadLog(); }
  else { $('ptitle').textContent = 'Beranda'; $('psub').textContent = ''; loadSummary(); }
}

/* ---------- ringkasan (beranda + lencana sidebar) ---------- */
async function loadSummary(){
  const d = await api('summary', null, {from:$('sfrom').value, to:$('sto').value, bayar:$('sbayar').value});
  if (!d.ok) return; SUM = d.data;
  Object.keys(MODS).forEach(k => $('bd-' + k).textContent = SUM[k].baru || '');
  if (ROUTE.v !== 'beranda') return;
  $('hm').replaceChildren(...Object.keys(MODS).map(k => {
    const c = SUM[k], n = (st, l, cls) => el('a', {href:'#/' + k + (st ? '/' + st : ''), class:cls || ''}, el('b', {}, String(c[st || 'all'])), el('span', {}, l));
    return el('div', {class:'card hcard'}, el('h3', {}, MODS[k].title), el('div', {class:'sub'}, c.all + ' SEP pada periode ini' + (c.error ? ' · ' + c.error + ' ada error langkah' : '')),
      el('div', {class:'hn'}, n('', 'Semua'), n('baru', 'Belum diproses', 'warn'), n('proses', 'Dalam proses'), n('final', 'Final', 'good'), n('kirim', 'Terkirim', 'good')));
  }));
}

/* ---------- daftar modul ---------- */
function renderStats(){
  const c = {all:ROWS.length, baru:0, proses:0, final:0, kirim:0};
  ROWS.forEach(r => c[rowState(r)[0]]++);
  const mk = (st, n, l, cls) => el('div', {class:'card stat ' + cls + (ROUTE.f === st ? ' act' : ''), onclick:() => { location.hash = '#/' + ROUTE.v + (st ? '/' + st : ''); }}, el('b', {}, String(n)), el('span', {}, l));
  $('stats').replaceChildren(mk('', c.all, 'Semua SEP', 's1'), mk('baru', c.baru, 'Belum diproses', 's2'), mk('proses', c.proses, 'Dalam proses', 's3'), mk('final', c.final, 'Final', 's3'), mk('kirim', c.kirim, 'Terkirim', 's4'));
}
function renderTable(){
  const tb = $('tb'); tb.replaceChildren();
  const f = ROUTE.f, ri = ROUTE.v === 'ri', rows = ROWS.filter(r => !f || rowState(r)[0] === f);
  if (!rows.length) return tb.append(el('tr', {}, el('td', {colspan:9, style:'text-align:center;color:var(--mut);padding:30px'}, 'Tidak ada data pada rentang/filter ini. Periksa filter status bayar (default: Sudah bayar).')));
  rows.forEach(r => {
    const [k, txt] = rowState(r), err = Object.values(r.steps || {}).some(x => x.status === 'error');
    tb.append(el('tr', {},
      el('td', {}, el('b', {}, r.nm_pasien), el('small', {}, r.no_rawat + ' · RM ' + r.no_rkm_medis)),
      el('td', {}, fmtD(r.tglsep)),
      ri ? el('td', {}, fmtD(r.tglpulang)) : el('td', {}, r.nmpolitujuan || '-'),
      el('td', {}, r.nm_dokter || '-'),
      el('td', {}, r.no_sep, r.sep_tipe === 'internal' ? el('small', {}, 'SEP internal') : null),
      el('td', {}, el('span', {class:'chip ' + (r.status_bayar === 'Sudah Bayar' ? 'st-kirim' : 'st-proses')}, r.status_bayar === 'Sudah Bayar' ? 'Sudah bayar' : (r.status_bayar ? 'Belum bayar' : '-'))),
      el('td', {}, r.cbg || '-', r.drg ? el('small', {}, 'iDRG ' + r.drg) : null),
      el('td', {}, el('span', {class:'chip st-' + k}, txt), err ? el('small', {style:'color:var(--err)'}, 'ada error') : null, r.khanza_kirim ? el('small', {}, 'terkirim via Khanza') : null),
      el('td', {}, el('div', {class:'acts'}, el('button', {class:'btn sm pri', onclick:() => openDlg(r, 'proses')}, 'Proses'), el('button', {class:'btn sm', onclick:() => openDlg(r, 'berkas')}, 'Berkas')))));
  });
}
async function load(){
  const m = MODS[ROUTE.v]; if (!m) return;
  const F = FIL[ROUTE.v]; F.from = $('from').value; F.to = $('to').value; F.q = $('q').value; F.bayar = $('fbayar').value;
  if (!$('dlg').open) $('tb').replaceChildren(el('tr', {}, el('td', {colspan:9, style:'padding:24px;color:var(--mut)'}, 'Memuat…')));
  const d = await api('list', null, {from:F.from, to:F.to, lanjut:m.lanjut, q:F.q, bayar:F.bayar});
  if (!d.ok) { $('tb').replaceChildren(el('tr', {}, el('td', {colspan:9, style:'color:var(--err);padding:20px'}, d.error))); fail(d); return; }
  ROWS = d.rows; renderStats(); renderTable();
  if (cur) cur = ROWS.find(r => r.no_rawat === cur.no_rawat) || cur;
  loadSummary();
}

/* ---------- log ---------- */
async function loadLog(){
  const d = await api('log'); const tb = $('lb'); tb.replaceChildren(); $('ld').hidden = true;
  if (!d.ok) { fail(d); return tb.append(el('tr', {}, el('td', {colspan:6, style:'color:var(--err);padding:16px'}, d.error))); }
  d.rows.forEach(r => tb.append(el('tr', {style:'cursor:pointer', onclick:async () => { const x = await api('log_detail', null, {id:r.id}); $('ld').hidden = false; const pj = t => { try { return JSON.parse(t); } catch (_) { return t; } };   // respons non-JSON ditampilkan apa adanya
      $('ld').hidden = false; $('ld').textContent = x.row ? JSON.stringify({request: pj(x.row.request_json || 'null'), response: pj(x.row.response_json || 'null')}, null, 2) : 'Tidak ada detail.'; }},
    el('td', {}, r.created_at), el('td', {}, r.method), el('td', {}, r.nomor_sep || '-'),
    el('td', {style:'color:' + (Number(r.code) === 200 ? 'var(--ok)' : 'var(--err)')}, (r.code ? r.code : 'HTTP ' + (r.http_code || '-')) + (r.error_no ? ' ' + r.error_no : '')),
    el('td', {style:'white-space:normal'}, r.message || ''), el('td', {}, String(r.ms || '')))));
}

/* ---------- dialog ---------- */
function setTab(t){
  document.querySelectorAll('.tabs button').forEach(b => b.classList.toggle('on', b.dataset.tab === t));
  $('tab-proses').hidden = t !== 'proses'; $('tab-berkas').hidden = t !== 'berkas';
  if (t === 'proses' && KL.no !== cur.no_rawat) KL.open(cur);
  if (t === 'berkas') { loadBerkas(); loadLampiran(); }
}
function openDlg(r, tab){
  cur = r;
  $('dt').textContent = r.nm_pasien + ' · RM ' + r.no_rkm_medis;
  $('ds').textContent = r.no_rawat + ' · SEP ' + r.no_sep + ' · ' + (String(r.jnspelayanan).charAt(0) === '1' ? 'Rawat Inap' : 'Rawat Jalan');
  $('bf').removeAttribute('src'); $('picked').textContent = ''; $('ufile').value = ''; $('ujudul').value = '';
  if (!$('dlg').open) $('dlg').showModal();
  setTab(tab);
}

/* ---------- berkas klaim & lampiran ---------- */
const bUrl = extra => 'berkas.php?embed=1&no_rawat=' + encodeURIComponent(cur.no_rawat) + (extra || '');
function loadBerkas(){ $('bf').src = bUrl('&t=' + Date.now()); }
$('bPdf').onclick = () => {
  const t = document.title; document.title = 'Berkas_Klaim_' + cur.no_rawat.replace(/\W/g, '_');   // nama file PDF default
  const restore = () => { document.title = t; };
  try { $('bf').contentWindow.addEventListener('afterprint', restore, {once:true}); $('bf').contentWindow.print(); }
  catch (e) { restore(); window.open(bUrl('&print=1')); }
};
$('bReload').onclick = loadBerkas;
$('bTab').onclick = () => window.open(bUrl(''));

async function filesApi(path, body){
  const o = body ? {method:'POST', headers:{'Content-Type':'application/json','X-CSRF':CSRF}, body:JSON.stringify(body)} : {};
  let r, txt;
  try { r = await fetch('files.php?action=' + path, o); txt = await r.text(); }
  catch (e) { Notify.error(['Tidak dapat menghubungi server aplikasi. (' + e.message + ')'], 'Koneksi gagal'); return {ok:false, error:'Koneksi gagal', notified:true}; }
  if (r.status === 401) { Notify.error(['Sesi berakhir. Silakan login ulang.'], 'Sesi berakhir', {onClose: () => { location.href = 'login.php'; }}); return {ok:false, error:'Sesi berakhir', notified:true}; }
  try { return JSON.parse(txt); } catch (e) { Notify.error(['Respons server tidak valid (HTTP ' + r.status + '): ' + txt.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200)], 'Respons server tidak valid'); return {ok:false, error:'Respons tidak valid', notified:true}; }
}
async function loadLampiran(){
  const d = await filesApi('list&no_rawat=' + encodeURIComponent(cur.no_rawat));
  const ul = $('flist'); ul.replaceChildren();
  if (!d.ok) { fail(d); return ul.append(el('li', {style:'color:var(--err)'}, d.error)); }
  $('lim').textContent = 'Maks. ' + d.limits.max + ' per file · ' + d.limits.allowed.join(', ').toUpperCase();
  if (!d.rows.length) ul.append(el('li', {style:'color:var(--mut)'}, 'Belum ada lampiran tambahan.'));
  d.rows.forEach(f => ul.append(el('li', {},
    el('div', {class:'ty'}, f.mime === 'application/pdf' ? 'PDF' : 'IMG'),
    el('div', {class:'nm'}, el('b', {title:f.judul}, f.judul), el('small', {}, f.nama_asli + ' · ' + f.ukuran_txt)),
    el('a', {href:'files.php?action=get&id=' + f.id, target:'_blank', class:'btn sm'}, 'Lihat'),
    el('button', {class:'btn sm danger', onclick:() => delLampiran(f)}, 'Hapus'))));
}
async function delLampiran(f){
  if (!confirm('Hapus lampiran "' + f.judul + '"?')) return;
  const d = await filesApi('delete', {id:f.id}); if (!d.ok) fail(d); else Notify.ok(['Lampiran dihapus.']);
  loadLampiran(); loadBerkas();
}
function pick(){ const n = $('ufile').files.length; $('picked').textContent = n ? n + ' file dipilih: ' + [...$('ufile').files].map(f => f.name).join(', ') : ''; }
$('drop').onclick = () => $('ufile').click();
$('ufile').onchange = pick;
['dragenter','dragover'].forEach(ev => $('drop').addEventListener(ev, e => { e.preventDefault(); $('drop').classList.add('over'); }));
['dragleave','drop'].forEach(ev => $('drop').addEventListener(ev, e => { e.preventDefault(); $('drop').classList.remove('over'); }));
$('drop').addEventListener('drop', e => { $('ufile').files = e.dataTransfer.files; pick(); });
$('bUp').onclick = () => {
  const files = $('ufile').files; if (!files.length) { Notify.warn(['Pilih file terlebih dahulu.'], 'File belum dipilih'); return; }
  const fd = new FormData(); fd.append('no_rawat', cur.no_rawat); fd.append('judul', $('ujudul').value);
  [...files].forEach(f => fd.append('files[]', f));
  const x = new XMLHttpRequest(); x.open('POST', 'files.php?action=upload'); x.setRequestHeader('X-CSRF', CSRF);
  $('upg').hidden = false; $('upg').value = 0; $('bUp').disabled = true;
  x.upload.onprogress = e => { if (e.lengthComputable) $('upg').value = e.loaded / e.total * 100; };
  x.onload = () => {
    let d; try { d = JSON.parse(x.responseText); } catch (_) { d = {ok:false, error:'Respons tidak valid (HTTP ' + x.status + '). Cek batas upload php.ini.'}; }
    if (x.status === 401) { Notify.error(['Sesi berakhir. Silakan login ulang.'], 'Sesi berakhir', {onClose: () => { location.href = 'login.php'; }}); return; }
    if (d.saved && d.saved.length) Notify.ok([d.saved.length + ' file berhasil diunggah.']);
    const errs = d.errors || (d.error ? [d.error] : []); if (errs.length) Notify.error(errs, 'Unggah gagal');
    if (d.saved && d.saved.length) { $('ufile').value = ''; $('ujudul').value = ''; pick(); loadLampiran(); loadBerkas(); }
  };
  x.onerror = () => Notify.error(['Koneksi terputus saat mengunggah. Periksa jaringan lalu ulangi.'], 'Unggah gagal');
  x.onloadend = () => { $('upg').hidden = true; $('bUp').disabled = false; };
  x.send(fd);
};

document.querySelectorAll('.tabs button').forEach(b => b.onclick = () => setTab(b.dataset.tab));
$('dx').onclick = () => $('dlg').close();
$('dlg').addEventListener('close', () => { $('bf').removeAttribute('src'); KL.reset(); load(); });
$('go').onclick = load;
$('fbayar').onchange = load;
$('sbayar').onchange = loadSummary;
$('q').onkeydown = e => { if (e.key === 'Enter') load(); };
$('sgo').onclick = loadSummary;
$('burger').onclick = () => $('side').classList.toggle('open');
$('sfrom').value = iso(new Date(Date.now() - 30 * 864e5)); $('sto').value = iso(new Date());
window.addEventListener('hashchange', route);
route();
</script>
<script src="klaim.js"></script>
</body>
</html>
