/* Halaman klaim meniru aplikasi E-Klaim: data dari Khanza, langkah sama dengan E-Klaim iDRG -> INACBG.
   Memakai fungsi global dari index.php: $, el, api, CSRF, load */
const KL = (() => {
  const S = {no:null, row:null, f:null, opts:{}, warn:[], st:{steps:{}, resp:{}}, claim:null, lists:null, tab:'idrg', drag:null, dirty:{inacbg:false}};
  const TARIF = [['prosedur_non_bedah','Prosedur Non Bedah'],['prosedur_bedah','Prosedur Bedah'],['konsultasi','Konsultasi'],['tenaga_ahli','Tenaga Ahli'],['keperawatan','Keperawatan'],['penunjang','Penunjang'],['radiologi','Radiologi'],['laboratorium','Laboratorium'],['pelayanan_darah','Pelayanan Darah'],['rehabilitasi','Rehabilitasi'],['kamar','Kamar / Akomodasi'],['rawat_intensif','Rawat Intensif'],['obat','Obat'],['obat_kronis','Obat Kronis'],['obat_kemoterapi','Obat Kemoterapi'],['alkes','Alkes'],['bmhp','BMHP'],['sewa_alat','Sewa Alat']];
  const JENIS = [['1','Rawat Inap'],['2','Rawat Jalan'],['3','Rawat Jalan (IGD)']];
  const KELAS = [['1','Kelas 1'],['2','Kelas 2'],['3','Kelas 3']];
  const CARA = [['gp','Rujukan FKTP'],['hosp-trans','Rujukan FKRTL'],['mp','Rujukan Spesialis'],['outp','Dari Rawat Jalan'],['inp','Dari Rawat Inap'],['emd','Dari Rawat Darurat'],['born','Lahir di RS'],['nursing','Rujukan Panti Jompo'],['psych','Rujukan RS Jiwa'],['rehab','Rujukan Fasilitas Rehab'],['other','Lain-lain']];
  const PULANG = [['1','Atas Persetujuan Dokter'],['2','Dirujuk'],['3','Atas Permintaan Sendiri'],['4','Meninggal'],['5','Lain-lain']];
  const NAIK = [['vvip','VVIP'],['vip','VIP'],['kelas_1','Kelas 1'],['kelas_2','Kelas 2']];
  const BLN = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

  const fmt = n => Number(n || 0).toLocaleString('en-US');
  const toNum = s => parseInt(String(s || '').replace(/[^\d]/g, ''), 10) || 0;
  const done = k => !!(S.st.steps[k] && (S.st.steps[k].status === 'ok' || S.st.steps[k].status === 'skip'));
  const okk = k => !!(S.st.steps[k] && S.st.steps[k].status === 'ok');
  const label = (arr, v) => { const m = arr.find(x => x[0] === String(v)); return m ? m[1] : (v || '-'); };
  const fmtDT = s => { const m = String(s || '').match(/^(\d{4})-(\d\d)-(\d\d)[ T](\d\d):(\d\d)/); return m ? (+m[3]) + ' ' + BLN[+m[2] - 1] + ' ' + m[1] + ' ' + m[4] + ':' + m[5] : (s || '-'); };
  const fmtD = s => { const m = String(s || '').match(/^(\d{4})-(\d\d)-(\d\d)/); return m ? (+m[3]) + ' ' + BLN[+m[2] - 1] + ' ' + m[1] : (s || '-'); };
  const claimFinal = () => okk('claim_final');
  const lockedForm = () => okk('idrg_final') || claimFinal();          // kriteria #10: form & coding iDRG read-only setelah iDRG final
  const lockedIdrg = () => okk('idrg_final') || claimFinal();
  const lockedInacbg = () => okk('inacbg_final') || claimFinal();

  /** cari nilai pada kunci bernama X di mana pun dalam objek (respons E-Klaim bisa berbeda antar versi) */
  function find(o, names) {
    const q = [o];
    while (q.length) {
      const x = q.shift();
      if (x && typeof x === 'object') {
        if (!Array.isArray(x)) for (const n of names) if (x[n] !== undefined && x[n] !== null && x[n] !== '') return x[n];
        for (const v of Object.values(x)) if (v && typeof v === 'object') q.push(v);
      }
    }
    return undefined;
  }
  const dash = v => (v === undefined || v === null || v === '' ? '-' : String(v));

  /** Semua notifikasi berupa pop up: err/w = dialog yang harus dikonfirmasi, ok = toast. */
  function msg(list, cls) {
    if (!list || !list.length) return;
    if (cls === 'err') Notify.error(list); else if (cls === 'ok') Notify.ok(list); else Notify.warn(list);
  }

  /* ---------------------------------------------------------- pemuatan */
  async function open(row) {
    S.row = row; S.no = row.no_rawat; S.claim = null;
    const box = $('klaim'); box.replaceChildren(el('div', {class: 'muted', style: 'padding:30px;text-align:center'}, 'Memuat data dari Khanza…'));
    const d = await api('form', null, {no_rawat: S.no});
    if (!d.ok) { box.replaceChildren(el('div', {class: 'msg err'}, d.error || 'Gagal memuat')); if (!d.notified) Notify.error([d.error || 'Gagal memuat data klaim dari Khanza.'], 'Data klaim tidak dapat dimuat'); return; }
    S.f = d.form; S.opts = d.opts; S.warn = d.warnings; S.st = {steps: d.state.steps, resp: d.state.resp};
    S.lists = {dx_idrg: d.form.diagnosa_idrg.slice(), px_idrg: d.form.prosedur_idrg.slice(), dx_inacbg: d.form.diagnosa_inacbg.slice(), px_inacbg: d.form.prosedur_inacbg.slice()};
    S.dirty = {inacbg: false}; syncInacbgFromImport();
    S.tab = okk('inacbg_group1') || okk('idrg_final') ? 'inacbg' : 'idrg';
    renderAll(true);
    msg(S.warn, 'w');
    refreshClaim();
  }
  async function refresh() {
    const d = await api('state', null, {no_rawat: S.no});
    if (d.ok) S.st = {steps: d.steps, resp: d.resp};
    if (okk('import') && S.lists.src !== 'import') syncInacbgFromImport();
    renderAll(false);
    if (typeof load === 'function') load();
  }
  async function refreshClaim() {
    const d = await api('claim_status', null, {no_rawat: S.no});
    S.claim = d.ok ? d.data : null; renderStatus();
    if (!d.ok && !d.error_no && !d.notified) msg(['Status klaim dari E-Klaim tidak dapat dibaca: ' + d.error], 'w');   // galat koneksi/format, bukan "klaim belum ada"
  }

  /* ---------------------------------------------------------- kerangka */
  function renderAll(full) {
    if (full) {
      const box = $('klaim'); box.replaceChildren(
        el('h2', {class: 'kh'}, 'KLAIM'), el('div', {id: 'kl-top'}), el('div', {id: 'kl-msg'}), el('div', {id: 'kl-form'}),
        el('div', {id: 'kl-tabs'}), el('div', {id: 'kl-panes'}), el('div', {id: 'kl-status'}),
        el('details', {style: 'margin:6px 0 60px'}, el('summary', {class: 'muted'}, 'Respons E-Klaim terakhir'), el('pre', {id: 'kl-last'}, 'Belum ada.')),
        el('div', {id: 'kl-bottom', class: 'kl-bottom'}));
      renderForm();
    }
    renderTop(); renderTabs(); renderPanes(); renderStatus(); renderBottom(); applyLocks();
  }

  function statusText() {
    if (done('send')) return 'Terkirim'; if (okk('claim_final')) return 'Final'; if (okk('inacbg_final')) return 'Final INACBG';
    if (okk('inacbg_group1')) return 'Grouping INACBG'; if (okk('idrg_final')) return 'Final iDRG'; if (okk('idrg_group1')) return 'Grouping iDRG';
    if (okk('set_claim_data')) return 'Tersimpan'; return 'Baru';
  }
  function cbgCode() {
    const r = S.st.resp.inacbg_group2 || S.st.resp.inacbg_group1; return r && r.response_inacbg && r.response_inacbg.cbg ? r.response_inacbg.cbg.code : '-';
  }
  function renderTop() {
    const f = S.f;
    const t = el('table', {class: 'kl-top'}, el('thead', {}, el('tr', {}, ...['Tanggal Masuk','Tanggal Pulang','Jaminan','No. SEP','Tipe','CBG','Status','Petugas'].map(x => el('th', {}, x)))),
      el('tbody', {}, el('tr', {}, el('td', {}, fmtD(f.tgl_masuk)), el('td', {}, fmtD(f.tgl_pulang)), el('td', {}, f.jaminan), el('td', {}, f.nomor_sep), el('td', {}, f.tipe),
        el('td', {}, dash(cbgCode())), el('td', {}, statusText()), el('td', {}, f.petugas || '-'))));
    $('kl-top').replaceChildren(t);
  }

  /* ---------------------------------------------------------- formulir */
  const sel = (id, arr, v) => { const s = el('select', {id}); arr.forEach(([k, l]) => s.append(el('option', {value: k}, l))); s.value = v; return s; };
  const dtv = s => String(s || '').replace(' ', 'T').slice(0, 19);
  function tr(...cells) { return el('tr', {}, ...cells); }

  function renderForm() {
    const f = S.f, o = S.opts.kode_tarif || {};
    const kt = el('select', {id: 'f_kt'}); Object.entries(o).forEach(([k, l]) => kt.append(el('option', {value: k}, l))); kt.value = f.kode_tarif;
    const tb = el('input', {id: 'f_tb', type: 'checkbox'}); tb.checked = !!f.pasien_tb;
    const sitb = el('input', {id: 'f_sitb', placeholder: 'No. register SITB', value: f.nomor_register_sitb || '', style: tb.checked ? '' : 'display:none'});
    tb.onchange = () => { sitb.style.display = tb.checked ? '' : 'none'; };
    const los = el('span', {id: 'f_los'});
    const upSel = sel('f_up', [['0','Tidak'],['1','Ya']], f.upgrade_class_ind);
    const form = el('div', {},
      el('div', {class: 'kl-peserta'}, el('div', {}, el('label', {}, 'Jaminan / Cara Bayar'), el('b', {}, f.jaminan)),
        el('div', {}, el('label', {}, 'No. Peserta'), el('input', {value: f.nomor_kartu, readonly: ''})),
        el('div', {}, el('label', {}, 'No. SEP'), el('input', {value: f.nomor_sep, readonly: ''}))),
      el('table', {class: 'klt'}, el('tbody', {},
        tr(el('th', {}, 'Jenis Rawat'), el('td', {}, sel('f_jenis', JENIS, f.jenis_rawat)), el('th', {class: 'r2'}, 'Kelas Hak'), el('td', {class: 'r2'}, sel('f_kelas', KELAS, f.kelas_rawat))),
        tr(el('th', {}, 'Tanggal Rawat'), el('td', {}, 'Masuk : ', el('input', {id: 'f_masuk', type: 'datetime-local', step: 1, value: dtv(f.tgl_masuk)}), '  Pulang : ', el('input', {id: 'f_pulang', type: 'datetime-local', step: 1, value: dtv(f.tgl_pulang)})),
           el('th', {class: 'r2'}, 'Umur'), el('td', {class: 'r2'}, f.umur_txt)),
        tr(el('th', {}, 'Cara Masuk'), el('td', {colspan: 3}, sel('f_cara', CARA, f.cara_masuk))),
        tr(el('th', {}, 'LOS'), el('td', {}, los), el('th', {class: 'r2'}, 'Berat Lahir (gram)'), el('td', {class: 'r2'}, el('input', {id: 'f_bb', value: f.birth_weight, class: 'sm'}))),
        tr(el('th', {}, 'ADL Score'), el('td', {}, 'Sub Acute : ', el('input', {id: 'f_adl1', class: 'sm', value: f.adl_sub_acute, placeholder: '-'}), '   Chronic : ', el('input', {id: 'f_adl2', class: 'sm', value: f.adl_chronic, placeholder: '-'})),
           el('th', {class: 'r2'}, 'Cara Pulang'), el('td', {class: 'r2'}, sel('f_disc', PULANG, f.discharge_status))),
        tr(el('th', {}, 'DPJP'), el('td', {}, el('input', {id: 'f_dpjp', value: f.nama_dokter, style: 'width:100%'})), el('th', {class: 'r2'}, 'Jenis Tarif'), el('td', {class: 'r2'}, kt)),
        tr(el('th', {}, 'Pasien TB'), el('td', {colspan: 3}, el('label', {}, tb, ' Ya'), ' ', sitb)))),
      el('div', {class: 'kl-tarifh'}, el('span', {class: 'qm', title: 'Tarif diambil dari billing Khanza. Arahkan kursor ke tanda ? di tiap komponen untuk melihat sumbernya. Dapat diubah sebelum disimpan.'}, '?'), ' ', el('i', {}, 'Tarif Rumah Sakit : '), 'Rp ', el('b', {id: 'f_ttl'}, '0')),
      el('div', {class: 'kl-tg'}, ...TARIF.map(([k, l]) => {
        const inp = el('input', {id: 't_' + k, inputmode: 'numeric', value: fmt(f.tarif_rs[k])});
        inp.oninput = () => { const v = toNum(inp.value); inp.value = v ? fmt(v) : '0'; ttl(); };
        inp.onfocus = () => inp.select();
        const src = (f.tarif_sumber && f.tarif_sumber[k]) ? 'Sumber Khanza: ' + f.tarif_sumber[k].join('; ') : 'Tidak ada data billing Khanza untuk komponen ini';
        return el('label', {}, el('span', {class: 'qm', title: src}, '?'), l, inp);
      })),
      el('div', {class: 'kl-ack'}, el('label', {}, el('input', {id: 'f_ack', type: 'checkbox'}), ' Menyatakan benar bahwa data tarif yang tersebut di atas adalah benar sesuai dengan kondisi yang sesungguhnya.')),
      el('div', {class: 'kl-sec'}, 'Data Klinis'),
      el('table', {class: 'klt'}, el('tbody', {},
        tr(el('th', {}, 'Tekanan Darah'), el('td', {}, 'Sistole ', el('input', {id: 'f_sis', class: 'sm', value: f.sistole}), '  Diastole ', el('input', {id: 'f_dia', class: 'sm', value: f.diastole}), ' mmHg'),
           el('th', {class: 'r2'}, 'ICU'), el('td', {class: 'r2'}, sel('f_icu', [['0','Tidak'],['1','Ya']], f.icu_indikator), ' LOS ', el('input', {id: 'f_icul', class: 'sm', value: f.icu_los}), ' hari  Ventilator ', el('input', {id: 'f_vent', class: 'sm', value: f.ventilator_hour, placeholder: 'jam'}))),
        tr(el('th', {}, 'Naik Kelas'), el('td', {colspan: 3}, upSel, ' ', sel('f_upc', NAIK, f.upgrade_class_class || 'kelas_1'), ' LOS ', el('input', {id: 'f_upl', class: 'sm', value: f.upgrade_class_los}), ' hari  Tambahan bayar ', el('input', {id: 'f_upp', class: 'sm', value: f.add_payment_pct}), ' %')))),
      el('div', {class: 'kl-save'}, el('button', {id: 'b_save', class: 'btn pri', onclick: e => act(e.target, save)}, 'Simpan Data Klaim')));
    $('kl-form').replaceChildren(form);
    ['f_masuk', 'f_pulang'].forEach(i => $(i).addEventListener('input', calcLos));
    calcLos(); ttl();
  }
  function ttl() { $('f_ttl').textContent = fmt(TARIF.reduce((a, [k]) => a + toNum($('t_' + k).value), 0)); }
  function calcLos() {
    const a = new Date($('f_masuk').value), b = new Date($('f_pulang').value), s = $('f_los');
    if (isNaN(a) || isNaN(b)) { s.textContent = '-'; return; }
    const d = Math.round((Date.UTC(b.getFullYear(), b.getMonth(), b.getDate()) - Date.UTC(a.getFullYear(), a.getMonth(), a.getDate())) / 864e5);
    const ms = Math.max(0, b - a), hh = String(Math.floor(ms / 36e5) % 24).padStart(2, '0'), mm = String(Math.floor(ms / 6e4) % 60).padStart(2, '0');
    s.replaceChildren(el('b', {}, String(Math.max(1, d))), ' hari ', el('span', {class: 'muted'}, '( ' + hh + ':' + mm + ' jam )'));
  }
  function collect() {
    const g = id => $(id).value, f = {};
    Object.assign(f, {jenis_rawat: g('f_jenis'), kelas_rawat: g('f_kelas'), tgl_masuk: g('f_masuk'), tgl_pulang: g('f_pulang'), cara_masuk: g('f_cara'),
      birth_weight: g('f_bb'), adl_sub_acute: g('f_adl1'), adl_chronic: g('f_adl2'), discharge_status: g('f_disc'), nama_dokter: g('f_dpjp'), kode_tarif: g('f_kt'),
      pasien_tb: $('f_tb').checked ? 1 : 0, nomor_register_sitb: g('f_sitb'), sistole: g('f_sis'), diastole: g('f_dia'),
      icu_indikator: g('f_icu'), icu_los: g('f_icul'), ventilator_hour: g('f_vent'),
      upgrade_class_ind: g('f_up'), upgrade_class_class: g('f_upc'), upgrade_class_los: g('f_upl'), add_payment_pct: g('f_upp'), tarif_rs: {}});
    TARIF.forEach(([k]) => f.tarif_rs[k] = String(toNum($('t_' + k).value)));
    return f;
  }
  function applyLocks() {
    const lk = lockedForm();
    document.querySelectorAll('#kl-form input,#kl-form select').forEach(e => { if (!e.hasAttribute('readonly')) e.disabled = lk; });
    const b = $('b_save'); if (b) b.disabled = lk;
  }

  /* ---------------------------------------------------------- tab & pane */
  function renderTabs() {
    $('kl-tabs').replaceChildren(el('div', {class: 'kl-tabs'}, ...[['idrg', 'iDRG'], ['inacbg', 'INACBG']].map(([k, l]) =>
      el('button', {class: S.tab === k ? 'on' : '', onclick: () => { S.tab = k; renderTabs(); renderPanes(); }}, l))));
  }
  function renderPanes() { $('kl-panes').replaceChildren(S.tab === 'idrg' ? paneIdrg() : paneInacbg()); }

  /** Validitas per kode dari respons E-Klaim (validcode "0" = tidak berlaku, mis. IM di INACBG). */
  function respCoding(step) { const r = S.st.resp[step]; return r && r._coding ? r._coding : null; }
  function validityMap() {
    const m = {idrg: {}, inacbg: {}}, put = (t, a) => (a || []).forEach(i => { t[i.code] = i; });
    put(m.idrg, respCoding('idrg_dx')); put(m.idrg, respCoding('idrg_px'));
    const imp = respCoding('import'); if (imp) { put(m.inacbg, imp.dx); put(m.inacbg, imp.px); }
    put(m.inacbg, respCoding('inacbg_dx')); put(m.inacbg, respCoding('inacbg_px'));   // hasil simpan terakhir menimpa import
    return m;
  }
  /** Daftar INACBG diisi dari hasil import E-Klaim (kriteria #13), bukan dari Khanza. */
  function syncInacbgFromImport() {
    const imp = respCoding('import'); if (!imp || S.dirty.inacbg) return;
    const conv = a => a.map(i => ({code: i.code, name: String(i.name || '').replace(/\s*\(IM\)\s*$/, ''), im: i.im, qty: 1, valid: i.valid, msg: i.msg}));
    if (imp.dx && imp.dx.length) S.lists.dx_inacbg = conv(imp.dx);
    if (imp.px && imp.px.length) S.lists.px_inacbg = conv(imp.px);
    S.lists.src = 'import';
  }
  const touched = key => { if (key.endsWith('_inacbg')) S.dirty.inacbg = true; };

  function adder(kind, nonIm, pane, onAdd) {
    const inp = el('input', {class: 'kl-add', placeholder: (kind === 'icd9' ? 'Tambah prosedur ICD-9-CM' : 'Tambah diagnosa ICD-10') + ' (kode / nama) · kamus ' + (pane === 'inacbg' ? 'INA Grouper' : 'iDRG') + '…'});
    const dd = el('div', {class: 'kl-dd'}); dd.hidden = true; let t;
    inp.oninput = () => { clearTimeout(t); t = setTimeout(async () => {
      const q = inp.value.trim(); if (q.length < 2) { dd.hidden = true; return; }
      const d = await api('icd', null, {type: kind, q, nonim: nonIm ? '1' : '0', pane});
      dd.replaceChildren(); (d.rows || []).forEach(r => dd.append(el('div', {class: 'kl-opt', onclick: () => { onAdd(r); inp.value = ''; dd.hidden = true; }}, el('b', {}, r.code), '  ', r.name, r.im === '1' ? el('small', {class: 'im'}, '  IM') : null)));
      dd.hidden = !(d.rows && d.rows.length); }, 250); };
    document.addEventListener('click', e => { if (e.target !== inp && !dd.contains(e.target)) dd.hidden = true; });
    return el('div', {class: 'kl-addwrap'}, inp, dd);
  }
  function listEditor(key, kind, locked, nonIm) {
    const arr = S.lists[key], ul = el('ul', {class: 'kl-list'}), pane = key.endsWith('inacbg') ? 'inacbg' : 'idrg', vm = validityMap()[pane];
    if (!arr.length) ul.append(el('li', {class: 'kl-item muted'}, 'Belum ada data.'));
    arr.forEach((it, i) => {
      const v = vm[it.code], bad = it.valid === false || (v && v.valid === false), why = (v && v.msg) || it.msg || 'IM tidak berlaku';
      const li = el('li', {class: 'kl-item' + (bad ? ' bad' : ''), title: bad ? why : ''});
      if (!locked) {
        li.draggable = true;
        li.ondragstart = e => { S.drag = {key, i}; e.dataTransfer.effectAllowed = 'move'; };
        li.ondragover = e => e.preventDefault();
        li.ondrop = e => { e.preventDefault(); if (S.drag && S.drag.key === key) { const m = arr.splice(S.drag.i, 1)[0]; arr.splice(i, 0, m); S.drag = null; touched(key); renderPanes(); } };
      }
      li.append(el('span', {class: 'grip'}, locked ? '' : '⋮⋮'), el('span', {class: 'nm'}, it.name || '(tidak ada di kamus)'),
        el('span', {class: 'code'}, it.code + (it.im === '1' ? ' IM' : '')));
      if (bad) li.append(el('span', {class: 'inv'}, 'tidak berlaku'));
      if (kind === 'icd10') li.append(el('span', {class: 'role'}, i === 0 ? 'Primary' : 'Secondary'));
      else if (key === 'px_idrg') li.append(el('input', {class: 'qty', type: 'number', min: 1, value: it.qty || 1, disabled: locked, title: 'Multiplicity (kode sama di baris lain = setting berbeda)', onchange: e => { it.qty = Math.max(1, +e.target.value || 1); }}));
      if (!locked) li.append(el('button', {class: 'x', title: 'Hapus', onclick: () => { arr.splice(i, 1); touched(key); renderPanes(); }}, '✕'));
      ul.append(li);
    });
    const wrap = el('div', {}, ul);
    if (!locked) wrap.append(adder(kind, nonIm, pane, r => {
      if (key === 'px_idrg' || !arr.some(x => x.code === r.code)) { arr.push({code: r.code, name: r.name, im: r.im, qty: 1}); touched(key); renderPanes(); }   // iDRG: kode berulang = setting berbeda
    }));
    return wrap;
  }

  function debugLink(resp) {
    const pre = el('pre', {style: 'display:none;max-height:260px'}, JSON.stringify(resp, null, 2));
    return [el('a', {onclick: () => { pre.style.display = pre.style.display === 'none' ? '' : 'none'; }}, '[ debug ]'), pre];
  }
  const resRow = (l, ...cells) => el('tr', {}, el('th', {}, l), ...cells);

  const idrgLatest = () => S.st.resp.idrg_group2 || S.st.resp.idrg_group1;
  const idrgUngroupable = () => { const r = idrgLatest(); return !!r && String(find(r.response_idrg || r, ['mdc_number'])) === '36'; };
  function idrgResult() {
    const r = idrgLatest(); if (!r) return null;
    const x = r.response_idrg || r, fin = okk('idrg_final');
    const mdcN = find(x, ['mdc_number']), drgC = find(x, ['drg_code']), bad = String(mdcN) === '36';
    const nbr = find(x, ['nbr', 'nbr_value', 'base_rate']), tcw = find(x, ['total_cost_weight']);
    const total = Number(nbr) && Number(tcw) ? Math.round(Number(nbr) * Number(tcw)) : find(x, ['total_claim', 'total_klaim', 'claim_amount']);
    const at = (S.st.steps.idrg_group2 || S.st.steps.idrg_group1 || {}).at;
    return el('table', {class: 'kl-res'}, el('thead', {}, el('tr', {}, el('th', {colspan: 4}, 'Hasil Grouping iDRG - ' + (fin ? 'Final' : 'Belum Final')))),
      el('tbody', {},
        resRow('Info', el('td', {colspan: 3}, 'iDRG @ ' + fmtDT(at))),
        resRow('Jenis Rawat', el('td', {colspan: 3}, label(JENIS, S.f.jenis_rawat))),
        resRow('MDC', el('td', {class: bad ? 'bad' : ''}, bad ? 'Ungroupable or Unrelated' : dash(find(x, ['mdc_description']))), el('td', {colspan: 2, class: 'r'}, dash(mdcN))),
        resRow('DRG', el('td', {class: bad ? 'bad' : ''}, dash(find(x, ['drg_description']))), el('td', {}, dash(drgC)), el('td', {class: 'r'}, 'DRG Cost Weight: ' + dash(find(x, ['cost_weight', 'drg_cost_weight', 'cw'])) + '  ·  Total: ' + dash(tcw))),
        resRow('NBR', el('td', {colspan: 3, class: 'blue'}, '** ' + (Number(nbr) ? fmt(nbr) : dash(nbr)))),
        resRow('Total Klaim', el('td', {colspan: 3, class: 'r'}, 'Rp  ** ' + (Number(total) ? fmt(total) : dash(total)))),
        resRow('Status', el('td', {colspan: 3}, fin ? 'final' : dash(find(x, ['status_cd'])) === '-' ? 'normal' : find(x, ['status_cd']))),
        el('tr', {}, el('td', {colspan: 4, class: 'note'}, '** ) Catatan: Nilai belum final, sewaktu-waktu bisa berubah')),
        el('tr', {}, el('td', {colspan: 4}, ...debugLink(r)))));
  }

  function optionBox(title, opts, name) {
    if (!Array.isArray(opts) || !opts.length) return null;
    const box = el('div', {class: 'kl-opts', 'data-name': name}, el('b', {}, title));
    let lastType = null;
    opts.forEach(o => {
      const code = typeof o === 'object' ? (o.code || o.id) : o, desc = typeof o === 'object' ? (o.description || o.name || '') : '', ty = typeof o === 'object' ? o.type : null;
      if (ty && ty !== lastType) { box.append(el('h5', {}, ty)); lastType = ty; }
      box.append(el('label', {}, el('input', {type: 'checkbox', value: code}), ' ' + code + (desc ? ' · ' + desc : '')));
    });
    return box;
  }
  const checked = name => [...document.querySelectorAll('[data-name="' + name + '"] input:checked')].map(i => i.value);

  function paneIdrg() {
    const lk = lockedIdrg(), r1 = S.st.resp.idrg_group1, p = el('div', {class: 'kl-pane'});
    p.append(el('h4', {}, 'Diagnosa ', el('small', {}, '(ICD-10)'), ':'), listEditor('dx_idrg', 'icd10', lk, false),
      el('h4', {}, 'Prosedur ', el('small', {}, '(ICD-9-CM)'), ':'), listEditor('px_idrg', 'icd9', lk, false));
    if (!lk) p.append(el('div', {class: 'kl-act'}, el('button', {class: 'btn pri', onclick: e => act(e.target, groupIdrg)}, 'Simpan & Grouping iDRG')));
    const res = idrgResult(); if (res) p.append(res);
    if (idrgUngroupable() && !okk('idrg_final')) p.append(el('div', {class: 'msg err'}, 'iDRG tidak dapat di-group (MDC 36 / Ungroupable). Perbaiki diagnosa/prosedur lalu grouping ulang; Final iDRG tidak tersedia.'));
    if (r1 && !okk('idrg_final')) {
      const tb = optionBox('Top-up tersedia (pilih untuk Stage 2)', find(r1, ['topup_options', 'topup_option']), 'topup');
      if (tb) p.append(tb, el('div', {class: 'kl-act'}, el('button', {class: 'btn', onclick: e => act(e.target, () => run('idrg_group2', {topup_codes: checked('topup').join('#')}))}, 'Grouping Tahap 2 (Top-up)')));
    }
    const a = el('div', {class: 'kl-act'});
    if (r1 && !okk('idrg_final') && !idrgUngroupable()) a.append(el('button', {class: 'btn danger', onclick: e => act(e.target, () => run('idrg_final', {}, 'Final iDRG? Setelah final diagnosa/prosedur iDRG terkunci.'))}, 'Final iDRG'));
    if (okk('idrg_final') && !claimFinal()) a.append(el('button', {class: 'btn', onclick: e => act(e.target, () => run('reedit_idrg', {}, 'Edit ulang iDRG? Hasil final iDRG & INACBG akan dibuka kembali.'))}, 'Edit Ulang iDRG'));
    p.append(a);
    return p;
  }

  function cmgLine(l, o) {
    const has = o && (o.code || o.description);
    return resRow(l, el('td', {}, has ? dash(o.description) : '-'), el('td', {}, has ? dash(o.code) : '-'), el('td', {class: 'r'}, 'Rp  ' + fmt(has ? o.tariff : 0)));
  }
  const inacbgLatest = () => S.st.resp.inacbg_group2 || S.st.resp.inacbg_group1;
  const inacbgError = () => { const r = inacbgLatest(); if (!r) return false; const c = r.response_inacbg && r.response_inacbg.cbg ? String(r.response_inacbg.cbg.code || '') : ''; return /^X/i.test(c); };
  function inacbgResult() {
    const r = inacbgLatest(); if (!r) return null;
    const x = r.response_inacbg || r, fin = okk('inacbg_final'), cbg = x.cbg || {};
    const sp = t => (Array.isArray(x.special_cmg) ? x.special_cmg.find(v => String(v.type || '').toLowerCase() === t.toLowerCase()) : null) || x[t.toLowerCase().replace(/ /g, '_')];
    const parts = [cbg, x.sub_acute, x.chronic, sp('Special Procedure'), sp('Special Prosthesis'), sp('Special Investigation'), sp('Special Drug')];
    const total = parts.reduce((a, o) => a + (o && Number(o.tariff) || 0), 0) + (Number(x.add_payment_amt) || 0);
    const at = (S.st.steps.inacbg_group2 || S.st.steps.inacbg_group1 || {}).at;
    const kt = (S.opts.kode_tarif || {})[S.f.kode_tarif] || S.f.kode_tarif;
    const tbl = el('table', {class: 'kl-res'}, el('thead', {}, el('tr', {}, el('th', {colspan: 4}, 'Hasil Grouping INACBG - ' + (fin ? 'Final' : 'Belum Final')))),
      el('tbody', {},
        resRow('Info', el('td', {colspan: 3}, 'INACBG @ ' + fmtDT(at) + ' •• Kelas ' + String(S.f.kode_tarif || '').charAt(0) + ' •• Tarif : ' + kt)),
        resRow('Jenis Rawat', el('td', {colspan: 3}, label(JENIS, S.f.jenis_rawat) + ' Regular')),
        cmgLine('Group', cbg), cmgLine('Sub Acute', x.sub_acute), cmgLine('Chronic', x.chronic),
        cmgLine('Special Procedure', sp('Special Procedure')), cmgLine('Special Prosthesis', sp('Special Prosthesis')),
        cmgLine('Special Investigation', sp('Special Investigation')), cmgLine('Special Drug', sp('Special Drug')),
        resRow('Total Klaim', el('td', {colspan: 3, class: 'r'}, el('b', {}, 'Rp  ' + fmt(total)))),
        resRow('Status', el('td', {colspan: 3}, fin ? 'final' : 'normal')),
        el('tr', {}, el('td', {colspan: 4}, ...debugLink(r)))));
    if (!inacbgError()) return tbl;
    const why = (r._warnings && r._warnings[0]) || ('INA Grouper gagal: ' + cbg.code + ' (' + (cbg.description || '') + '). Tarif Rp 0 karena tidak ada hasil klaim.');
    return el('div', {}, el('div', {class: 'msg err'}, why), tbl);
  }
  function paneInacbg() {
    const p = el('div', {class: 'kl-pane'}), lk = lockedInacbg();
    if (!okk('idrg_final')) { p.append(el('div', {class: 'msg w'}, 'Selesaikan Grouping dan Final iDRG terlebih dahulu, lalu impor ke INACBG.')); return p; }
    if (!okk('import')) {
      p.append(el('p', {}, 'Hasil iDRG sudah final. Impor seluruh coding iDRG ke INACBG terlebih dahulu; kode yang tidak berlaku di INACBG (IM) akan ditandai dan tidak disimpan.'),
        el('div', {class: 'kl-act'}, el('button', {class: 'btn pri', onclick: e => act(e.target, () => run('import'))}, 'Import dari iDRG ke INACBG')));
      return p;
    }
    if (S.lists.src === 'import') p.append(el('div', {class: 'msg ok'}, 'Daftar diisi dari hasil import E-Klaim. Kode bertanda "tidak berlaku" (IM) tidak disimpan oleh INA Grouper: hapus atau ganti dengan kode yang berlaku sebelum grouping.'));
    p.append(el('h4', {}, 'Diagnosa ', el('small', {}, '(ICD-10)'), ':'), listEditor('dx_inacbg', 'icd10', lk, true),
      el('h4', {}, 'Prosedur ', el('small', {}, '(ICD-9-CM)'), ':'), listEditor('px_inacbg', 'icd9', lk, true));
    if (!lk) p.append(el('div', {class: 'kl-act'}, el('button', {class: 'btn pri', onclick: e => act(e.target, groupInacbg)}, 'Simpan & Grouping INACBG')));
    const res = inacbgResult(); if (res) p.append(res);
    const r1 = S.st.resp.inacbg_group1, bad = inacbgError();
    if (r1 && !okk('inacbg_final') && !bad) {
      const cb = optionBox('Special CMG tersedia (pilih untuk Stage 2)', find(r1, ['special_cmg_option']), 'cmg');
      if (cb) p.append(cb, el('div', {class: 'kl-act'}, el('button', {class: 'btn', onclick: e => act(e.target, () => run('inacbg_group2', {special_cmg: checked('cmg').join('#')}))}, 'Grouping Tahap 2 (Special CMG)')));
    }
    const a = el('div', {class: 'kl-act'});
    if (r1 && !okk('inacbg_final') && !bad) a.append(el('button', {class: 'btn danger', onclick: e => act(e.target, () => run('inacbg_final', {}, 'Final INACBG?'))}, 'Final INACBG'));   // tidak muncul bila hasil error grouper (kriteria #16/#17)
    if (okk('inacbg_final') && !claimFinal()) a.append(el('button', {class: 'btn', onclick: e => act(e.target, () => run('reedit_inacbg', {}, 'Edit ulang INACBG?'))}, 'Edit Ulang INACBG'));
    p.append(a);
    return p;
  }

  /* ---------------------------------------------------- status & bar bawah */
  function renderStatus() {
    const c = S.claim || {};
    const ks = find(c, ['klaim_status_cd']), km = find(c, ['kemkes_dc_status_cd']), bp = find(c, ['bpjs_dc_status_cd']);
    const kl = ks ? ({final: 'Final', normal: 'Normal', sent: 'Terkirim'}[ks] || ks) : (okk('claim_final') ? 'Final' : 'Normal');
    const dc = (v, local) => v ? (v === 'sent' ? 'Terkirim' : 'Belum Terkirim') : (local ? 'Terkirim' : 'Belum Terkirim');
    const row = (l, v) => el('div', {class: 'r'}, el('div', {class: 'l'}, l), el('div', {class: 'v'}, v));
    $('kl-status').replaceChildren(el('div', {class: 'kl-statusbox'}, el('div', {class: 't'}, 'Status Klaim'),
      row('Status Klaim', kl), row('Status DC Kemkes', dc(km, done('send'))), row('Status DC BPJS Kesehatan', dc(bp, false))));
  }
  function renderBottom() {
    const fin = claimFinal(), inFin = okk('inacbg_final');
    const b = (txt, fn, cls) => el('button', {class: 'btn ' + (cls || ''), onclick: e => act(e.target, fn)}, txt);
    const left = el('div', {class: 'kl-act', style: 'margin:0'});
    // Kriteria #20-#23: Final Klaim hanya setelah INACBG final; Kirim & Cetak hanya setelah Klaim final.
    if (fin) left.append(b('Cetak Klaim', () => window.open('api.php?action=print&no_rawat=' + encodeURIComponent(S.no))), b('Kirim Klaim Online', () => run('send', {}, 'Kirim klaim ke Data Center Kemkes?', true)));
    else if (inFin) left.append(b('Final Klaim', () => run('claim_final', {}, 'Finalisasi klaim? Data klaim akan terkunci.'), 'danger'));
    else left.append(el('span', {class: 'muted'}, 'Final Klaim tersedia setelah INACBG final.'));
    $('kl-bottom').replaceChildren(left, fin ? b('Edit Ulang Klaim', () => run('reedit_claim', {}, 'Buka kembali klaim untuk diedit?'), 'dark') : el('span'));
  }

  /* ------------------------------------------------------------- aksi */
  async function act(btn, fn) {
    document.querySelectorAll('#klaim button').forEach(b => b.dataset.was = b.disabled ? '1' : '0');
    document.querySelectorAll('#klaim button').forEach(b => b.disabled = true);
    $('kl-last').textContent = 'Memproses…';
    try { await fn(); } finally {
      document.querySelectorAll('#klaim button').forEach(b => { b.disabled = b.dataset.was === '1'; });
      await refresh();
    }
  }
  function report(d) {
    $('kl-last').textContent = JSON.stringify(d, null, 2);
    if (d.warnings && d.warnings.length) msg(d.warnings, 'w');
    if (d.result && d.result._warnings) msg(d.result._warnings, 'w');
    if (!d.ok) msg(['Gagal' + (d.failed_step ? ' di langkah "' + d.failed_step + '"' : '') + ': ' + d.error + (d.error_no ? ' [' + d.error_no + ']' : '')], 'err');
    return !!d.ok;
  }
  async function save() {
    $('kl-msg').replaceChildren();
    if (!$('f_ack').checked) { msg(['Centang pernyataan kebenaran data tarif terlebih dahulu.'], 'w'); return; }
    if (report(await api('save_claim', {no_rawat: S.no, form: collect()}))) msg(['Data klaim tersimpan di E-Klaim.'], 'ok');
  }
  async function groupIdrg() {
    $('kl-msg').replaceChildren();
    if (!okk('set_claim_data')) { msg(['Simpan Data Klaim terlebih dahulu.'], 'w'); return; }
    if (!S.lists.dx_idrg.length) { msg(['Isi minimal satu diagnosa iDRG.'], 'w'); return; }
    // tiap record prosedur dikirim apa adanya: kode berulang = setting berbeda, qty = multiplicity
    report(await api('group_idrg', {no_rawat: S.no, dx: S.lists.dx_idrg.map(x => x.code), px: S.lists.px_idrg.map(x => ({code: x.code, qty: x.qty || 1}))}));
  }
  async function groupInacbg() {
    $('kl-msg').replaceChildren();
    const V = validityMap().inacbg, ok = x => x.valid !== false && !(V[x.code] && V[x.code].valid === false);
    const dx = S.lists.dx_inacbg.filter(ok), skipped = S.lists.dx_inacbg.filter(x => !ok(x));
    if (!dx.length) {
      msg(['Tidak ada diagnosa INACBG yang berlaku. Kode bertanda "tidak berlaku" (IM) tidak disimpan oleh E-Klaim sehingga INA Grouper menghasilkan X-0-99-X (EMPTY RESULT). Ganti dengan kode yang berlaku (pakai kolom pencarian di bawah daftar), lalu grouping lagi.'], 'err');
      return;
    }
    if (skipped.length) msg(['Tidak dikirim (tidak berlaku di INACBG): ' + skipped.map(x => x.code).join(', ')], 'w');
    report(await api('group_inacbg', {no_rawat: S.no, dx: dx.map(x => x.code), px: S.lists.px_inacbg.filter(ok).map(x => ({code: x.code}))}));
  }
  async function run(step, params, confirmText, refreshStatus) {
    if (confirmText && !confirm(confirmText)) return;
    $('kl-msg').replaceChildren();
    const ok = report(await api('run', {no_rawat: S.no, step, params: params || {}}));
    if (ok && /final|send|reedit/.test(step)) refreshClaim();
  }

  return {open, get no() { return S.no; }, reset() { S.no = null; }};
})();
