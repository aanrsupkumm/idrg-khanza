/* Notifikasi pop up: galat & peringatan = dialog yang harus dikonfirmasi, sukses/info = toast.
   Beberapa notifikasi yang datang bersamaan digabung dalam satu pop up. Tanpa dependensi. */
(function () {
  if (window.Notify) return;
  var css = '.nf{border:0;border-radius:16px;padding:0;width:min(560px,92vw);max-height:86vh;color:#0f172a;background:#fff;box-shadow:0 24px 70px #0008;overflow:hidden}' +
    '.nf::backdrop{background:#020b13b8}' +
    '.nf.shake{animation:nfshake .45s}@keyframes nfshake{0%,100%{transform:translateX(0)}20%{transform:translateX(-8px)}40%{transform:translateX(8px)}60%{transform:translateX(-5px)}80%{transform:translateX(5px)}}' +
    '.nf-h{display:flex;align-items:center;gap:12px;padding:16px 20px;color:#fff;background:#b91c1c}.nf-h.warn{background:#b45309}.nf-h.info{background:#0e7490}' +
    '.nf-i{width:30px;height:30px;border-radius:50%;background:#ffffff33;display:grid;place-items:center;font-weight:800;font-size:17px;flex:none}' +
    '.nf-h b{font-size:16px}.nf-b{padding:16px 20px;max-height:50vh;overflow:auto;font:14px/1.55 "Segoe UI",system-ui,sans-serif}' +
    '.nf-b ul{list-style:none;margin:0;padding:0}.nf-b li{display:flex;gap:10px;padding:8px 0;border-bottom:1px solid #e2e8f0;word-break:break-word}.nf-b li:last-child{border:0}' +
    '.nf-b li:before{content:"";flex:none;width:9px;height:9px;border-radius:50%;margin-top:7px;background:#b91c1c}.nf-b li.warn:before{background:#d97706}.nf-b li.info:before{background:#0e7490}' +
    '.nf-f{display:flex;justify-content:flex-end;padding:12px 20px 16px}.nf-ok{font:600 14px "Segoe UI",system-ui,sans-serif;border:0;border-radius:10px;padding:9px 26px;background:#0f172a;color:#fff;cursor:pointer}' +
    '.nf-ok:focus{outline:3px solid #06b6d466}' +
    '.nf-toasts{position:fixed;top:16px;right:16px;z-index:2147483647;display:flex;flex-direction:column;gap:8px;max-width:min(420px,92vw)}' +
    '.nf-t{background:#14532d;color:#fff;border-radius:12px;padding:12px 16px;box-shadow:0 10px 30px #0006;font:14px/1.45 "Segoe UI",system-ui,sans-serif;animation:nfin .2s}.nf-t.info{background:#164e63}' +
    '@keyframes nfin{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}' +
    '@media (prefers-color-scheme:dark){.nf{background:#131b25;color:#e6edf3}.nf-b li{border-color:#243040}.nf-ok{background:#e6edf3;color:#0f172a}}';
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  var RANK = {error: 3, warn: 2, info: 1}, TITLE = {error: 'Terjadi kesalahan', warn: 'Perhatian', info: 'Informasi'}, ICON = {error: '!', warn: '!', info: 'i'};
  var queue = [], timer = null, cbs = [], cur = null, titleOverride = {};

  function list(m) { return (Array.isArray(m) ? m : [m]).map(function (x) { return String(x == null ? '' : x).trim(); }).filter(Boolean); }

  function add(type, msgs, title, opts) {
    list(msgs).forEach(function (t) { queue.push({type: type, text: t}); });
    if (title) titleOverride[type] = title;
    if (opts && opts.onClose) cbs.push(opts.onClose);
    clearTimeout(timer); timer = setTimeout(flush, 80);   // gabungkan notifikasi dalam satu aksi
  }

  function header(items) {
    var top = items.reduce(function (a, i) { return RANK[i.type] > RANK[a] ? i.type : a; }, 'info');
    return {top: top, title: titleOverride[top] || TITLE[top]};
  }

  function fill(d, items) {
    var h = header(items), seen = {}, ul = d.querySelector('ul');
    d.querySelector('.nf-h').className = 'nf-h ' + h.top;
    d.querySelector('.nf-i').textContent = ICON[h.top];
    d.querySelector('.nf-h b').textContent = h.title;
    ul.textContent = '';
    items.sort(function (a, b) { return RANK[b.type] - RANK[a.type]; }).forEach(function (i) {
      var k = i.type + '|' + i.text; if (seen[k]) return; seen[k] = 1;
      var li = document.createElement('li'); li.className = i.type; li.textContent = i.text; ul.appendChild(li);
    });
  }

  function flush() {
    if (!queue.length) return;
    var items = queue.splice(0);
    if (cur) { cur.items = cur.items.concat(items); cur.cbs = cur.cbs.concat(cbs); cbs = []; fill(cur.el, cur.items); return; }   // sudah ada pop up terbuka: tambahkan
    var d = document.createElement('dialog');
    d.className = 'nf'; d.setAttribute('role', 'alertdialog'); d.setAttribute('aria-modal', 'true');
    d.innerHTML = '<div class="nf-h"><span class="nf-i"></span><b></b></div><div class="nf-b"><ul></ul></div><div class="nf-f"><button type="button" class="nf-ok">Mengerti</button></div>';
    document.body.appendChild(d);
    cur = {el: d, items: items, cbs: cbs}; cbs = []; fill(d, items); titleOverride = {};
    d.querySelector('.nf-ok').onclick = function () { d.close(); };
    d.addEventListener('close', function () {
      var done = cur ? cur.cbs : []; cur = null; d.remove(); done.forEach(function (f) { try { f(); } catch (e) {} });
      if (queue.length) flush();
    });
    d.showModal();
    if (header(items).top === 'error') { d.classList.add('shake'); }
    var ok = d.querySelector('.nf-ok'); if (ok.focus) ok.focus();
  }

  function toast(msgs, type) {
    var box = document.querySelector('.nf-toasts');
    if (!box) { box = document.createElement('div'); box.className = 'nf-toasts'; box.setAttribute('role', 'status'); document.body.appendChild(box); }
    list(msgs).forEach(function (t) {
      var e = document.createElement('div'); e.className = 'nf-t' + (type === 'info' ? ' info' : ''); e.textContent = (type === 'info' ? '' : '✓ ') + t; box.appendChild(e);
      setTimeout(function () { e.remove(); }, 4500);
    });
  }

  window.Notify = {
    error: function (m, title, o) { add('error', m, title, o); },
    warn: function (m, title, o) { add('warn', m, title, o); },
    info: function (m) { toast(m, 'info'); },
    ok: function (m) { toast(m, 'ok'); },
    _flush: flush
  };
})();
