<?php
declare(strict_types=1);
namespace Idrg;

/**
 * Alur klaim iDRG -> INA-CBG (meniru langkah aplikasi E-Klaim) dengan data dari SIMRS Khanza.
 * Finalisasi selalu manual: coder meninjau hasil grouping.
 */
final class ClaimService
{
    public const STEPS = [
        'new_claim', 'set_claim_data', 'idrg_dx', 'idrg_px', 'idrg_group1', 'idrg_group2', 'idrg_final',
        'import', 'inacbg_dx', 'inacbg_px', 'inacbg_group1', 'inacbg_group2', 'inacbg_final', 'claim_final', 'send',
    ];
    public const EXTRA = ['reedit_idrg', 'reedit_inacbg', 'reedit_claim', 'get_claim'];

    public const TARIF_KEYS = [
        'prosedur_non_bedah', 'prosedur_bedah', 'konsultasi', 'tenaga_ahli', 'keperawatan', 'penunjang',
        'radiologi', 'laboratorium', 'pelayanan_darah', 'rehabilitasi', 'kamar', 'rawat_intensif',
        'obat', 'obat_kronis', 'obat_kemoterapi', 'alkes', 'bmhp', 'sewa_alat',
    ];
    public const KODE_TARIF = [
        'AP' => 'TARIF RS KELAS A PEMERINTAH', 'AS' => 'TARIF RS KELAS A SWASTA', 'BP' => 'TARIF RS KELAS B PEMERINTAH',
        'BS' => 'TARIF RS KELAS B SWASTA', 'CP' => 'TARIF RS KELAS C PEMERINTAH', 'CS' => 'TARIF RS KELAS C SWASTA',
        'DP' => 'TARIF RS KELAS D PEMERINTAH', 'DS' => 'TARIF RS KELAS D SWASTA',
    ];
    private const CARA_MASUK = ['gp', 'hosp-trans', 'mp', 'outp', 'inp', 'emd', 'born', 'nursing', 'psych', 'rehab', 'other'];

    /** @var KhanzaRepository */
    private $kz;
    /** @var EklaimClient */
    private $api;
    /** @var \PDO */
    private $pdo;
    /** @var array */
    private $cfg;

    public function __construct(KhanzaRepository $kz, EklaimClient $api, \PDO $pdo, array $cfg)
    {
        $this->kz = $kz;
        $this->api = $api;
        $this->pdo = $pdo;
        $this->cfg = $cfg;
    }

    /* ============================================================ daftar */

    public function list(string $from, string $to, string $lanjut, string $q, string $bayar = 'Sudah'): array
    {
        $rows = $this->kz->list($from, $to, $lanjut, $q, $bayar);
        $nos = array_column($rows, 'no_rawat');
        $prog = $this->progress($nos);
        $cbg = $this->latestCodes($nos);
        foreach ($rows as &$r) {
            $r['steps'] = $prog[$r['no_rawat']] ?? [];
            $r['cbg'] = $cbg[$r['no_rawat']]['cbg'] ?? '';
            $r['drg'] = $cbg[$r['no_rawat']]['drg'] ?? '';
        }
        return $rows;
    }

    /** baru | proses | final | kirim — sama dengan logika tampilan daftar. */
    public static function rowState(array $steps): string
    {
        $d = function ($k) use ($steps) { return isset($steps[$k]) && in_array($steps[$k]['status'], ['ok', 'skip'], true); };
        if ($d('send')) return 'kirim';
        if ($d('claim_final')) return 'final';
        foreach (['set_claim_data', 'idrg_group1', 'idrg_final', 'inacbg_group1', 'inacbg_final'] as $k) if ($d($k)) return 'proses';
        return 'baru';
    }

    /** Hitungan per modul (rawat jalan / rawat inap) untuk beranda & lencana sidebar. */
    public function summary(string $from, string $to, string $bayar = 'Sudah'): array
    {
        $o = [];
        foreach (['ralan' => 'rj', 'ranap' => 'ri'] as $lanjut => $k) {
            $c = ['all' => 0, 'baru' => 0, 'proses' => 0, 'final' => 0, 'kirim' => 0, 'error' => 0];
            foreach ($this->list($from, $to, $lanjut, '', $bayar) as $r) {
                $c['all']++;
                $c[self::rowState($r['steps'])]++;
                foreach ($r['steps'] as $st) if ($st['status'] === 'error') { $c['error']++; break; }
            }
            $o[$k] = $c;
        }
        return $o;
    }

    private function latestCodes(array $nos): array
    {
        $o = [];
        if (!$nos) return $o;
        $in = implode(',', array_fill(0, count($nos), '?'));
        $st = $this->pdo->prepare("SELECT no_rawat, step, response_json FROM idrg_step
                                   WHERE no_rawat IN ($in) AND status='ok' AND step IN ('idrg_group1','idrg_group2','inacbg_group1','inacbg_group2')
                                   ORDER BY updated_at ASC");
        $st->execute(array_values($nos));
        foreach ($st->fetchAll() as $r) {
            $j = json_decode((string)$r['response_json'], true);
            if (!is_array($j)) continue;
            if (strpos($r['step'], 'inacbg') === 0 && !empty($j['response_inacbg']['cbg']['code'])) $o[$r['no_rawat']]['cbg'] = (string)$j['response_inacbg']['cbg']['code'];
            if (strpos($r['step'], 'idrg') === 0 && !empty($j['_summary']['drg'])) $o[$r['no_rawat']]['drg'] = (string)$j['_summary']['drg'];
        }
        return $o;
    }

    /* ============================================== formulir (data Khanza) */

    /** Data awal halaman klaim: dibentuk dari Khanza, dapat diubah coder sebelum disimpan. */
    public function form(string $noRawat): array
    {
        $h = $this->context($noRawat);
        $w = [];
        $f = $this->buildForm($h, $w);
        $opts = self::KODE_TARIF;
        $kt = (string)($this->cfg['rs']['kode_tarif'] ?? '');
        if ($kt !== '' && !isset($opts[$kt])) $opts[$kt] = 'TARIF ' . $kt;
        return ['form' => $f, 'warnings' => $w, 'opts' => ['kode_tarif' => $opts], 'state' => $this->state($noRawat)];
    }

    public function buildForm(array $h, array &$w): array
    {
        $no = $h['no_rawat'];
        $rs = $this->cfg['rs'];

        $jenis = substr(trim((string)$h['jnspelayanan']), 0, 1);
        if (!in_array($jenis, ['1', '2'], true)) $jenis = $h['status_lanjut'] === 'Ranap' ? '1' : '2';
        if ($jenis === '2' && in_array((string)($h['kd_poli'] ?? ''), $rs['kd_poli_igd'] ?? [], true)) $jenis = '3';
        $ranap = $jenis === '1';

        $tglsep = substr((string)$h['tglsep'], 0, 10);
        $masuk = ($tglsep !== '' ? $tglsep : $h['tgl_registrasi']) . ' ' . $h['jam_reg'];
        $pulang = $this->tglPulang($h, $ranap, $masuk, $w);
        $kamar = $ranap ? $this->kz->ranap($no) : null;
        $lama = $kamar ? max(1, (int)$kamar['lama']) : 1;

        list($tarif, $tw, $src) = $this->tarif($no);
        $w = array_merge($w, $tw);
        if (array_sum(array_map('intval', $tarif)) <= 0) $w[] = 'Total tarif RS = 0. Simpan/cek billing di Khanza, atau isi tarif secara manual.';

        $tensi = $this->kz->tensi($no, $ranap);
        if (!$tensi) { $tensi = ['120', '90']; $w[] = 'Tekanan darah tidak ada di pemeriksaan: memakai default 120/90 (sama seperti Khanza).'; }

        $asal = (string)$h['asal_rujukan'];
        $cara = strpos($asal, '1. Faskes 1') === 0 ? 'gp' : (strpos($asal, '2. Faskes 2') === 0 ? 'hosp-trans' : 'other');

        $up = ['ind' => '0', 'class' => '', 'los' => '', 'pct' => ''];
        if (preg_match('/^\s*(\d)/', (string)$h['klsnaik'], $m)) {
            $map = ['1' => 'vvip', '2' => 'vip', '3' => 'kelas_1', '4' => 'kelas_2'];
            if (isset($map[$m[1]])) {
                $up = ['ind' => '1', 'class' => $map[$m[1]], 'los' => (string)$lama, 'pct' => (string)($rs['add_payment_pct'] ?? 0)];
                $w[] = 'Naik kelas terdeteksi: periksa kelas, lama naik kelas, dan persentase tambahan bayar.';
            }
        }

        $icu = $ranap ? $this->kz->icuLos($no) : 0;
        $dx = $this->kz->diagnosaDetail($no);
        $px = $this->kz->prosedurDetail($no);
        $nonIm = function ($r) { return (string)($r['im'] ?? '0') === '0'; };
        $kelas = substr(trim((string)$h['klsrawat']), 0, 1);

        list($umurTxt) = BerkasHelper::umur((string)$h['tgl_lahir'], $masuk);
        $umurTxt = preg_replace('/^(\d+) th (\d+) bln$/', '$1 tahun', $umurTxt);

        return [
            'no_rawat' => $no, 'nomor_sep' => $h['no_sep'], 'nomor_kartu' => (string)($h['no_kartu'] ?: $h['no_peserta']),
            'nm_pasien' => $h['nm_pasien'], 'nomr' => $h['no_rkm_medis'], 'jaminan' => 'JKN', 'tipe' => $ranap ? 'RI' : 'RJ',
            'petugas' => (string)$h['petugas'], 'umur_txt' => $umurTxt,
            'jenis_rawat' => $jenis, 'kelas_rawat' => in_array($kelas, ['1', '2', '3'], true) ? $kelas : '3',
            'tgl_masuk' => $masuk, 'tgl_pulang' => $pulang, 'cara_masuk' => $cara,
            'birth_weight' => $this->kz->beratBayi((string)$h['no_rkm_medis']),
            'adl_sub_acute' => '', 'adl_chronic' => '',
            'discharge_status' => $this->kz->dischargeStatus($no), 'nama_dokter' => $this->kz->dpjp($no),
            'kode_tarif' => (string)$rs['kode_tarif'], 'pasien_tb' => 0, 'nomor_register_sitb' => '',
            'icu_indikator' => $icu > 0 ? '1' : '0', 'icu_los' => (string)$icu, 'ventilator_hour' => '',
            'upgrade_class_ind' => $up['ind'], 'upgrade_class_class' => $up['class'], 'upgrade_class_los' => $up['los'], 'add_payment_pct' => $up['pct'],
            'sistole' => $tensi[0], 'diastole' => $tensi[1],
            'tarif_rs' => $tarif, 'tarif_sumber' => $src,
            'diagnosa_idrg' => array_map(function ($r) { return ['code' => $r['code'], 'name' => (string)$r['name'], 'im' => (string)$r['im']]; }, $dx),
            'prosedur_idrg' => array_map(function ($r) { return ['code' => $r['code'], 'name' => (string)$r['name'], 'qty' => $r['qty'], 'im' => (string)$r['im']]; }, $px),
            'diagnosa_inacbg' => array_values(array_map(function ($r) { return ['code' => $r['code'], 'name' => (string)$r['name'], 'im' => '0']; }, array_filter($dx, $nonIm))),
            'prosedur_inacbg' => array_values(array_map(function ($r) { return ['code' => $r['code'], 'name' => (string)$r['name'], 'qty' => $r['qty'], 'im' => '0']; }, array_filter($px, $nonIm))),
        ];
    }

    private function tglPulang(array $h, bool $ranap, string $masuk, array &$w): string
    {
        $tp = trim((string)$h['tglpulang']);
        if ($tp !== '' && strncmp($tp, '0000', 4) !== 0 && strtotime($tp) !== false) {
            $tp = strlen($tp) <= 10 ? $tp . ' 23:59:59' : str_replace('00:00:00', '23:59:59', $tp);
            return date('Y-m-d H:i:s', (int)strtotime($tp));
        }
        if ($ranap) {
            $k = $this->kz->ranap($h['no_rawat']);
            if ($k && $k['pulang']) return date('Y-m-d H:i:s', (int)strtotime($k['pulang']));
            $w[] = 'Pasien rawat inap belum pulang (tanggal pulang kosong). Isi manual bila perlu.';
        }
        return $masuk;
    }

    /** @return array{0:array<string,string>,1:string[],2:array<string,string[]>} */
    private function tarif(string $noRawat): array
    {
        $map = $this->cfg['billing_map'];
        $t = array_fill_keys(self::TARIF_KEYS, 0.0);
        $warn = []; $ignored = []; $src = []; $khanzaTotal = null;
        foreach ($this->kz->billing($noRawat) as $status => $total) {
            if (strcasecmp($status, 'Total') === 0) { $khanzaTotal = $total; continue; }
            if (isset($map[$status]) && isset($t[$map[$status]])) {
                $t[$map[$status]] += $total;
                $src[$map[$status]][] = $status . ': ' . number_format($total, 0, ',', '.');
            } elseif (stripos($status, 'Ttl') !== 0 && abs($total) > 0) {
                $ignored[$status] = $total;
            }
        }
        $out = array_map(function ($v) { return (string)max(0, (int)round($v)); }, $t);
        if ($ignored) {
            $warn[] = 'Status billing tidak terpetakan (diabaikan): ' . implode(', ', array_map(
                function ($k, $v) { return "$k=" . number_format($v, 0, ',', '.'); }, array_keys($ignored), $ignored));
        }
        if ($khanzaTotal !== null) {
            $warn[] = sprintf('Cek silang: total billing Khanza %s, total tarif terpetakan %s',
                number_format($khanzaTotal, 0, ',', '.'), number_format(array_sum($t), 0, ',', '.'));
        }
        return [$out, $warn, $src];
    }

    /* ================================================= validasi & payload */

    private function dt($v): string
    {
        $v = str_replace('T', ' ', trim((string)$v));
        $ts = strtotime($v);
        if ($v === '' || $ts === false) throw new \RuntimeException('Format tanggal/jam tidak valid: ' . $v);
        return date('Y-m-d H:i:s', $ts);
    }

    private function int0($v, int $max = PHP_INT_MAX): int
    {
        return max(0, min($max, (int)preg_replace('/[^\d]/', '', (string)$v)));
    }

    /** Bentuk data set_claim_data dari form (default Khanza atau hasil edit coder). */
    public function payloadFromForm(array $f, array $h): array
    {
        $tm = $this->dt($f['tgl_masuk'] ?? '');
        $tp = $this->dt($f['tgl_pulang'] ?? '');
        if (strtotime($tp) < strtotime($tm)) throw new \RuntimeException('Tanggal pulang lebih awal dari tanggal masuk.');
        $jenis = (string)($f['jenis_rawat'] ?? '');
        if (!in_array($jenis, ['1', '2', '3'], true)) throw new \RuntimeException('Jenis rawat tidak valid.');
        $kelas = (string)($f['kelas_rawat'] ?? '');
        if (!in_array($kelas, ['1', '2', '3'], true)) throw new \RuntimeException('Kelas rawat tidak valid.');
        $disc = (string)($f['discharge_status'] ?? '');
        if (!in_array($disc, ['1', '2', '3', '4', '5'], true)) throw new \RuntimeException('Cara pulang tidak valid.');
        $cara = (string)($f['cara_masuk'] ?? 'other');
        if (!in_array($cara, self::CARA_MASUK, true)) throw new \RuntimeException('Cara masuk tidak valid.');
        $kt = (string)($f['kode_tarif'] ?? '');
        if (!preg_match('/^[A-Z]{2,5}$/', $kt)) throw new \RuntimeException('Jenis tarif tidak valid.');

        $tarif = [];
        foreach (self::TARIF_KEYS as $k) $tarif[$k] = (string)$this->int0($f['tarif_rs'][$k] ?? 0);
        $rs = $this->cfg['rs'];

        $p = [
            'nomor_sep' => $h['no_sep'],
            'nomor_kartu' => (string)($h['no_kartu'] ?: $h['no_peserta']),
            'tgl_masuk' => $tm, 'tgl_pulang' => $tp, 'cara_masuk' => $cara,
            'jenis_rawat' => $jenis, 'kelas_rawat' => $kelas,
            'icu_indikator' => !empty($f['icu_indikator']) && (string)$f['icu_indikator'] === '1' ? '1' : '0',
            'birth_weight' => (string)$this->int0($f['birth_weight'] ?? 0),
            'sistole' => $this->int0($f['sistole'] ?? 120, 300), 'diastole' => $this->int0($f['diastole'] ?? 90, 300),
            'discharge_status' => $disc, 'tarif_rs' => $tarif,
            'nama_dokter' => mb_substr(trim((string)($f['nama_dokter'] ?? '')), 0, 250),
            'kode_tarif' => $kt,
        ];
        if ($p['icu_indikator'] === '1') {
            $p['icu_los'] = (string)$this->int0($f['icu_los'] ?? 0);
            if (trim((string)($f['ventilator_hour'] ?? '')) !== '') $p['ventilator_hour'] = (string)$this->int0($f['ventilator_hour']);
        }
        foreach (['adl_sub_acute', 'adl_chronic'] as $k) if (trim((string)($f[$k] ?? '')) !== '') $p[$k] = (string)$this->int0($f[$k], 60);
        if ((string)($f['upgrade_class_ind'] ?? '0') === '1') {
            $p['upgrade_class_ind'] = '1';
            $p['upgrade_class_class'] = (string)$f['upgrade_class_class'];
            $p['upgrade_class_los'] = (string)$this->int0($f['upgrade_class_los'] ?? 0);
            $p['add_payment_pct'] = (string)$this->int0($f['add_payment_pct'] ?? 0, 100);
        } else {
            $p['upgrade_class_ind'] = '0';
        }
        if (!empty($f['pasien_tb'])) {
            $p['pasien_tb'] = '1';
            if (trim((string)($f['nomor_register_sitb'] ?? '')) !== '') $p['nomor_register_sitb'] = trim((string)$f['nomor_register_sitb']);
        }
        if (!empty($rs['payor_id'])) $p['payor_id'] = (string)$rs['payor_id'];
        if (!empty($rs['payor_cd'])) $p['payor_cd'] = (string)$rs['payor_cd'];
        if (isset($rs['cob_cd']) && $rs['cob_cd'] !== '') $p['cob_cd'] = (string)$rs['cob_cd'];
        $nik = (string)($this->cfg['eklaim']['coder_nik'] ?? '');
        if ($nik !== '') $p['coder_nik'] = $nik;
        return $p;
    }

    private function newClaimData(array $h): array
    {
        return [
            'nomor_kartu' => (string)($h['no_kartu'] ?: $h['no_peserta']), 'nomor_sep' => $h['no_sep'],
            'nomor_rm' => $h['no_rkm_medis'], 'nama_pasien' => $h['nm_pasien'],
            'tgl_lahir' => substr((string)$h['tgl_lahir'], 0, 10) . ' 00:00:00', 'gender' => $h['jk'] === 'L' ? '1' : '2',
        ];
    }

    /** Daftar kode dari parameter UI (array string / objek {code}) atau, bila kosong, dari Khanza. */
    private function codes($v, array $fallback): array
    {
        if (!is_array($v)) return $fallback;
        $o = [];
        foreach ($v as $x) {
            $c = trim((string)(is_array($x) ? ($x['code'] ?? '') : $x));
            if ($c !== '' && preg_match('/^[A-Za-z0-9.\-+]{1,20}$/', $c)) $o[$c] = $c;
        }
        return array_values($o);
    }

    /**
     * Prosedur iDRG: tiap record dipertahankan (kode berulang = setting berbeda), multiplicity ditulis KODE+n.
     * Contoh: "86.22+2#86.22" = dua setting, yang pertama multiplicity 2.
     */
    private function procs($v, array $fallback): array
    {
        $rows = is_array($v) ? $v : $fallback;
        $r = [];
        foreach ($rows as $x) {
            $c = trim((string)(is_array($x) ? ($x['code'] ?? '') : $x));
            if ($c === '' || !preg_match('/^[A-Za-z0-9.\-]{1,20}$/', $c)) continue;
            $q = is_array($x) ? max(1, (int)($x['qty'] ?? 1)) : 1;
            $r[] = $q > 1 ? "$c+$q" : $c;
        }
        return $r;
    }

    /** Prosedur INA Grouper: hanya kode (tanpa +n), unik. */
    private function procsInacbg($v, array $fallback): array
    {
        $o = [];
        foreach ($this->procs($v, $fallback) as $x) $o[preg_replace('/\+\d+$/', '', $x)] = 1;
        return array_keys($o);
    }

    private function khCodes(array $rows, bool $nonImOnly): array
    {
        $o = [];
        foreach ($rows as $r) if (!$nonImOnly || (string)$r['im'] === '0') $o[] = $r['code'];
        return $o;
    }

    /* ===================================================== preview/status */

    public function preview(string $noRawat): array
    {
        $h = $this->context($noRawat);
        $w = [];
        $f = $this->buildForm($h, $w);
        return [
            'new_claim' => $this->newClaimData($h), 'claim_data' => $this->payloadFromForm($f, $h),
            'idrg' => ['diagnosa' => array_column($f['diagnosa_idrg'], 'code'), 'prosedur' => $this->procs($f['prosedur_idrg'], [])],
            'inacbg' => ['diagnosa' => array_column($f['diagnosa_inacbg'], 'code'), 'prosedur' => $this->procs($f['prosedur_inacbg'], [])],
            'warnings' => $w,
        ];
    }

    /** Status langkah + hasil grouping tersimpan (agar halaman bisa dibuka ulang kapan saja). */
    public function state(string $noRawat): array
    {
        $st = $this->pdo->prepare('SELECT step, status, message, updated_at, response_json FROM idrg_step WHERE no_rawat=?');
        $st->execute([$noRawat]);
        $steps = []; $resp = [];
        foreach ($st->fetchAll() as $r) {
            $steps[$r['step']] = ['status' => $r['status'], 'message' => $r['message'], 'at' => $r['updated_at']];
            if ($r['status'] === 'ok' && in_array($r['step'], ['idrg_dx', 'idrg_px', 'idrg_group1', 'idrg_group2', 'import', 'inacbg_dx', 'inacbg_px', 'inacbg_group1', 'inacbg_group2'], true)) {
                $resp[$r['step']] = json_decode((string)$r['response_json'], true);
            }
        }
        return ['steps' => $steps, 'resp' => $resp];
    }

    /** Status klaim di E-Klaim (final / terkirim ke DC). Tidak fatal bila klaim belum dibuat. */
    public function claimStatus(string $noRawat): array
    {
        $h = $this->context($noRawat);
        try {
            $r = $this->api->call('get_claim_data', ['nomor_sep' => $h['no_sep']]);
            return ['ok' => true, 'data' => $r['data'] ?? $r];
        } catch (EklaimException $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'error_no' => $e->errorNo];
        }
    }

    /**
     * Pencarian ICD. Utama: kamus E-Klaim (search_diagnosis[_inagrouper] / search_procedures[_inagrouper]) agar kode yang dipilih
     * dijamin ada di kamus pane terkait (iDRG = ICD IM, INACBG = kamus INA Grouper). Cadangan: master ICD Khanza.
     */
    public function searchIcd(string $type, string $q, bool $nonIm, string $pane = ''): array
    {
        $type = $type === 'icd9' ? 'icd9' : 'icd10';
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        if ($pane === 'idrg' || $pane === 'inacbg') {
            $inacbg = $pane === 'inacbg';
            $method = $type === 'icd9' ? ($inacbg ? 'search_procedures' : 'search_procedures_inagrouper') : ($inacbg ? 'search_diagnosis' : 'search_diagnosis_inagrouper');
            try {
                $res = $this->api->call($method, ['keyword' => $q]);
                $rows = [];
                foreach (($res['response']['data'] ?? []) as $x) {
                    if (!is_array($x)) continue;
                    if (isset($x['code'])) $rows[] = ['code' => (string)$x['code'], 'name' => (string)($x['description'] ?? ''), 'im' => (string)($x['im'] ?? '0')];
                    elseif (isset($x[1])) $rows[] = ['code' => (string)$x[1], 'name' => (string)$x[0], 'im' => '0'];
                }
                if ($rows) return array_slice($rows, 0, 20);
            } catch (\Throwable $e) { /* pakai master Khanza */ }
        }
        return $this->kz->searchIcd($type, $q, $nonIm);
    }

    /* =========================================================== eksekusi */

    public function run(string $noRawat, string $step, array $p = []): array
    {
        if (!in_array($step, array_merge(self::STEPS, self::EXTRA), true)) throw new \InvalidArgumentException("Langkah tidak dikenal: $step");
        $h = $this->context($noRawat);
        $tracked = in_array($step, self::STEPS, true);
        try {
            $res = $this->exec($step, $h, $p);
        } catch (EklaimException $e) {
            if ($tracked) $this->saveStep($noRawat, $h['no_sep'], $step, 'error', $e->getMessage(), $e->payload);
            throw $e;
        } catch (\RuntimeException $e) {
            if ($tracked) $this->saveStep($noRawat, $h['no_sep'], $step, 'error', $e->getMessage(), []);
            throw $e;
        }
        if ($tracked) $this->saveStep($noRawat, $h['no_sep'], $step, !empty($res['_skip']) ? 'skip' : 'ok', '', $res);
        return ['ok' => true, 'step' => $step, 'result' => $res];
    }

    /** Beberapa langkah berurutan; berhenti pada kesalahan pertama. */
    private function chain(string $no, array $plan): array
    {
        $out = [];
        $warn = [];
        foreach ($plan as $item) {
            list($s, $pp) = $item;
            try {
                $out[$s] = $this->run($no, $s, $pp)['result'];
                $warn = array_merge($warn, $out[$s]['_warnings'] ?? []);
            } catch (\Throwable $e) {
                return ['ok' => false, 'failed_step' => $s, 'error' => $e->getMessage(), 'warnings' => $warn,
                        'error_no' => $e instanceof EklaimException ? $e->errorNo : null, 'done' => array_keys($out)];
            }
        }
        return ['ok' => true, 'done' => array_keys($out), 'warnings' => $warn, 'result' => end($out)];
    }

    /** "Simpan" pada halaman klaim: klaim baru (bila belum) lalu data klaim. */
    public function saveClaim(string $no, array $form): array
    {
        return $this->chain($no, [['new_claim', []], ['set_claim_data', ['form' => $form]]]);
    }

    public function groupIdrg(string $no, ?array $dx, ?array $px): array
    {
        return $this->chain($no, [['idrg_dx', ['dx' => $dx]], ['idrg_px', ['px' => $px]], ['idrg_group1', []]]);
    }

    public function groupInacbg(string $no, ?array $dx, ?array $px): array
    {
        return $this->chain($no, [['inacbg_dx', ['dx' => $dx]], ['inacbg_px', ['px' => $px]], ['inacbg_group1', []]]);
    }

    public function printPdf(string $noRawat): string
    {
        $h = $this->context($noRawat);
        $res = $this->api->call('claim_print', ['nomor_sep' => $h['no_sep']]);
        $b64 = $res['data'] ?? '';
        if (!is_string($b64) || $b64 === '') throw new \RuntimeException('E-Klaim tidak mengembalikan PDF.');
        return $b64;
    }

    private function exec(string $step, array $h, array $p): array
    {
        $sep = $h['no_sep'];
        $no = $h['no_rawat'];
        $m = ['nomor_sep' => $sep];

        switch ($step) {
            case 'new_claim':
                try {
                    return $this->api->call('new_claim', $this->newClaimData($h));
                } catch (EklaimException $e) {
                    if ($e->errorNo === 'E2007') return ['duplicate' => true, 'note' => 'SEP sudah terdaftar di E-Klaim, dilanjutkan.'];
                    throw $e;
                }

            case 'set_claim_data':
                $w = [];
                $form = isset($p['form']) && is_array($p['form']) ? $p['form'] : $this->buildForm($h, $w);
                $res = $this->api->call('set_claim_data', $this->payloadFromForm($form, $h), $m);
                $res['_warnings'] = $w;
                return $res;

            case 'idrg_dx':
                $codes = $this->codes($p['dx'] ?? null, $this->khCodes($this->kz->diagnosaDetail($no), false));
                if (!$codes) throw new \RuntimeException('Diagnosa iDRG kosong. Isi minimal satu diagnosa.');
                $res = $this->api->call('idrg_diagnosa_set', ['diagnosa' => implode('#', $codes)], $m);
                $res['_coding'] = $this->expandedItems($res['data']['expanded'] ?? []);
                $res['_warnings'] = $this->invalidCodes($res, 'iDRG');
                return $res;

            case 'idrg_px':
                $codes = $this->procs($p['px'] ?? null, $this->kz->prosedurDetail($no));
                if (!$codes) {
                    // Daftar dikosongkan coder setelah pernah tersimpan: hapus semua prosedur ("#"); bila memang tak ada prosedur, lewati.
                    if (isset($p['px']) && $this->stepStatus($no, 'idrg_px') === 'ok') $codes = ['#'];
                    else return ['_skip' => true, 'note' => 'Tidak ada prosedur'];
                }
                $res = $this->api->call('idrg_procedure_set', ['procedure' => implode('#', $codes)], $m);
                $res['_coding'] = $this->expandedItems($res['data']['expanded'] ?? []);
                $res['_warnings'] = $this->invalidCodes($res, 'iDRG');
                return $res;

            case 'idrg_group1':
                $res = $this->api->call('grouper', $m, ['stage' => '1', 'grouper' => 'idrg']);
                $res['_summary'] = $this->summaryIdrg($res);
                return $res;

            case 'idrg_group2':
                $codes = $this->hashJoin($p['topup_codes'] ?? '');
                if ($codes === '') throw new \RuntimeException('Pilih minimal satu top-up.');
                $res = $this->api->call('grouper', $m + ['topup_codes' => $codes], ['stage' => '2', 'grouper' => 'idrg']);
                $res['_summary'] = $this->summaryIdrg($res);
                return $res;

            case 'idrg_final':
                $this->assertIdrgFinalizable($no);
                return $this->api->call('idrg_grouper_final', $m);

            case 'import':
                $res = $this->api->call('idrg_to_inacbg_import', $m);
                $res['_coding'] = $this->splitCoding($res);
                $res['_warnings'] = [];
                foreach (['dx' => 'diagnosa', 'px' => 'prosedur'] as $k => $lbl) {
                    foreach ($res['_coding'][$k] as $it) if (!$it['valid']) $res['_warnings'][] = "Import INACBG: $lbl {$it['code']} tidak berlaku" . ($it['msg'] !== '' ? " ({$it['msg']})" : '') . ' dan TIDAK disimpan di INACBG.';
                }
                return $res;

            case 'inacbg_dx':
                $codes = $this->codes($p['dx'] ?? null, $this->khCodes($this->kz->diagnosaDetail($no), true));
                if (!$codes) throw new \RuntimeException('Diagnosa INACBG kosong. Isi minimal satu diagnosa yang berlaku di INACBG.');
                $res = $this->api->call('inacbg_diagnosa_set', ['diagnosa' => implode('#', $codes)], $m);
                $res['_coding'] = $this->expandedItems($res['data']['expanded'] ?? []);
                $res['_warnings'] = $this->invalidCodes($res, 'INACBG');
                if ($res['_coding'] && !array_filter($res['_coding'], function ($i) { return $i['valid']; })) {
                    throw new \RuntimeException('Tidak ada diagnosa yang berlaku di INACBG (semua ditolak: ' . implode(', ', array_column($res['_coding'], 'code'))
                        . '). Kode IM tidak berlaku pada INA Grouper dan tidak disimpan; ganti dengan kode yang berlaku, lalu grouping lagi.');
                }
                return $res;

            case 'inacbg_px':
                $codes = $this->procsInacbg($p['px'] ?? null, array_values(array_filter($this->kz->prosedurDetail($no), function ($r) { return (string)$r['im'] === '0'; })));
                if (!$codes) {
                    if (isset($p['px']) && $this->stepStatus($no, 'inacbg_px') === 'ok') $codes = ['#'];
                    else return ['_skip' => true, 'note' => 'Tidak ada prosedur'];
                }
                $res = $this->api->call('inacbg_procedure_set', ['procedure' => implode('#', $codes)], $m);
                $res['_coding'] = $this->expandedItems($res['data']['expanded'] ?? []);
                $res['_warnings'] = $this->invalidCodes($res, 'INACBG');
                return $res;

            case 'inacbg_group1':
                $res = $this->api->call('grouper', $m, ['stage' => '1', 'grouper' => 'inacbg']);
                return $this->withGrouperSummary($res);

            case 'inacbg_group2':
                $codes = $this->hashJoin($p['special_cmg'] ?? '');
                if ($codes === '') throw new \RuntimeException('Pilih minimal satu Special CMG.');
                $res = $this->api->call('grouper', $m + ['special_cmg' => $codes], ['stage' => '2', 'grouper' => 'inacbg']);
                return $this->withGrouperSummary($res);

            case 'inacbg_final':
                $this->assertInacbgFinalizable($no);
                return $this->api->call('inacbg_grouper_final', $m);

            case 'claim_final':
                $nik = (string)($this->cfg['eklaim']['coder_nik'] ?? '');
                if ($nik === '') throw new \RuntimeException('eklaim.coder_nik belum diisi di config.');
                return $this->api->call('claim_final', $m + ['coder_nik' => $nik]);

            case 'send':
                return $this->api->call('send_claim_individual', $m);

            case 'reedit_idrg':
                $res = $this->api->call('idrg_grouper_reedit', $m);
                $this->clearFrom($no, 'idrg_final');
                return $res;

            case 'reedit_inacbg':
                $res = $this->api->call('inacbg_grouper_reedit', $m);
                $this->clearFrom($no, 'inacbg_final');
                return $res;

            case 'reedit_claim':
                $res = $this->api->call('reedit_claim', $m);
                $this->clearFrom($no, 'claim_final');
                return $res;

            case 'get_claim':
                return $this->api->call('get_claim_data', $m);
        }
        throw new \InvalidArgumentException("Langkah tidak dikenal: $step");
    }

    private function assertIdrgFinalizable(string $noRawat): void
    {
        $s = $this->state($noRawat)['resp'];
        $g1 = $s['idrg_group1'] ?? null;
        if (!$g1) throw new \RuntimeException('Jalankan Grouping iDRG terlebih dahulu.');
        $latest = $s['idrg_group2'] ?? $g1;
        if ((string)($latest['_summary']['mdc'] ?? '') === '36') throw new \RuntimeException('MDC 36 (Ungroupable or Unrelated): perbaiki coding sebelum final.');
        if (($g1['_summary']['topup_options'] ?? 0) > 0 && empty($s['idrg_group2'])) {
            throw new \RuntimeException('Ada pilihan top-up iDRG: jalankan Stage 2 sebelum Final (atau pilih tanpa top-up bila diizinkan).');
        }
    }

    /* ======================================================== util kecil */

    private function context(string $noRawat): array
    {
        $h = $this->kz->header($noRawat);
        if (!$h) throw new \RuntimeException("no_rawat $noRawat tidak ditemukan.");
        if (empty($h['no_sep'])) throw new \RuntimeException('Belum ada SEP (bridging_sep) untuk kunjungan ini.');
        return $h;
    }

    /** Peringatan per kode yang ditolak E-Klaim (validcode "0", mis. E2101 "IM tidak berlaku"). */
    private function invalidCodes(array $res, string $label): array
    {
        $w = [];
        foreach ($res['data']['expanded'] ?? [] as $e) {
            if ((string)($e['validcode'] ?? '1') !== '1') {
                $msg = (string)($e['metadata']['message'] ?? 'tidak berlaku');
                $w[] = "Kode {$e['code']} tidak berlaku di $label ($msg) dan tidak disimpan oleh E-Klaim.";
            }
        }
        return $w;
    }

    /** data.expanded[] E-Klaim -> daftar seragam {code,name,im,valid,qty,msg}. */
    private function expandedItems(array $exp): array
    {
        $o = [];
        foreach ($exp as $e) {
            if (!is_array($e)) continue;
            $name = (string)($e['display'] ?? '');
            $o[] = ['code' => (string)($e['code'] ?? ''), 'name' => $name, 'im' => preg_match('/\(IM\)\s*$/', $name) ? '1' : '0',
                    'valid' => (string)($e['validcode'] ?? '1') === '1', 'qty' => max(1, (int)($e['multiplicity'] ?? 1)),
                    'msg' => (string)($e['metadata']['message'] ?? '')];
        }
        return $o;
    }

    private function codingBlocks($node, string $path, array &$out): void
    {
        if (!is_array($node)) return;
        if (isset($node['expanded']) && is_array($node['expanded'])) $out[] = ['path' => strtolower($path), 'items' => $this->expandedItems($node['expanded'])];
        foreach ($node as $k => $v) if (is_array($v)) $this->codingBlocks($v, $path . '/' . $k, $out);
    }

    /** Respons idrg_to_inacbg_import -> ['dx'=>[...], 'px'=>[...]] (klasifikasi dari nama kunci). */
    private function splitCoding(array $res): array
    {
        $blocks = [];
        $this->codingBlocks($res['data'] ?? $res, 'data', $blocks);
        $out = ['dx' => [], 'px' => []];
        $rest = [];
        foreach ($blocks as $b) {
            if (strpos($b['path'], 'diagnos') !== false) $out['dx'] = array_merge($out['dx'], $b['items']);
            elseif (preg_match('/proc|prosedur|tindakan/', $b['path'])) $out['px'] = array_merge($out['px'], $b['items']);
            else $rest[] = $b;
        }
        if ($rest && !$out['dx']) $out['dx'] = array_shift($rest)['items'];
        if ($rest && !$out['px']) $out['px'] = array_shift($rest)['items'];
        return $out;
    }

    private function stepStatus(string $noRawat, string $step): string
    {
        $st = $this->pdo->prepare('SELECT status FROM idrg_step WHERE no_rawat=? AND step=?');
        $st->execute([$noRawat, $step]);
        return (string)$st->fetchColumn();
    }

    /** Kode CBG berawalan "X" = INA Grouper gagal (bukan hasil klaim), tarif otomatis 0. */
    public static function cbgIsError(?string $code): bool
    {
        return $code !== null && strncasecmp(trim($code), 'X', 1) === 0;
    }

    public static function explainGrouperError(string $code, string $desc): string
    {
        $base = "INA Grouper gagal: $code" . ($desc !== '' ? " ($desc)" : '') . '. Tarif Rp 0 karena tidak ada hasil klaim. ';
        if (stripos($code . $desc, 'EMPTY RESULT') !== false) {
            return $base . 'Penyebab umum: (1) tidak ada diagnosa INACBG yang berlaku (kode IM tidak disimpan saat import/penyimpanan), '
                . '(2) diagnosa utama tidak valid atau urutannya salah, (3) data klaim belum lengkap/tersimpan (jenis rawat, tanggal, cara pulang, kelas), '
                . '(4) INA Grouper/lisensi di server E-Klaim bermasalah. Periksa tanda "tidak berlaku" pada daftar diagnosa INACBG.';
        }
        return $base . 'Periksa kelengkapan data klaim (jenis kelamin, umur, tanggal, cara pulang) dan kode diagnosa/prosedur.';
    }

    private function withGrouperSummary(array $res): array
    {
        $cbg = $res['response_inacbg']['cbg'] ?? [];
        $code = (string)($cbg['code'] ?? '');
        $res['_summary'] = ['cbg' => $code, 'cbg_desc' => (string)($cbg['description'] ?? ''), 'error' => self::cbgIsError($code)];
        if ($res['_summary']['error']) $res['_warnings'] = [self::explainGrouperError($code, $res['_summary']['cbg_desc'])];
        return $res;
    }

    private function assertInacbgFinalizable(string $noRawat): void
    {
        $r = $this->state($noRawat)['resp'];
        $g = $r['inacbg_group2'] ?? ($r['inacbg_group1'] ?? null);
        if (!$g) throw new \RuntimeException('Jalankan Grouping INACBG terlebih dahulu.');
        $code = (string)($g['response_inacbg']['cbg']['code'] ?? '');
        if (self::cbgIsError($code)) throw new \RuntimeException("Hasil INACBG $code adalah error grouper (bukan CBG yang valid): perbaiki coding dan grouping ulang sebelum final.");
    }

    private function summaryIdrg(array $res): array
    {
        $r = $res['response_idrg'] ?? [];
        return [
            'mdc' => $r['mdc_number'] ?? null, 'drg' => $r['drg_code'] ?? null, 'drg_desc' => $r['drg_description'] ?? null,
            'topup_options' => is_array($r['topup_options'] ?? null) ? count($r['topup_options']) : 0,
        ];
    }

    private function hashJoin($v): string
    {
        if (is_array($v)) $v = implode('#', $v);
        return trim((string)preg_replace('/\s*#\s*/', '#', (string)$v), '# ');
    }

    private function saveStep(string $noRawat, string $sep, string $step, string $status, string $msg, array $resp): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO idrg_step (no_rawat,no_sep,step,status,message,response_json) VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE no_sep=VALUES(no_sep), status=VALUES(status),
                                     message=VALUES(message), response_json=VALUES(response_json)'
        );
        $st->execute([$noRawat, $sep, $step, $status, mb_substr($msg, 0, 500), json_encode($resp, JSON_UNESCAPED_UNICODE)]);
    }

    private function clearFrom(string $noRawat, string $fromStep): void
    {
        $idx = (int)array_search($fromStep, self::STEPS, true);
        $del = array_slice(self::STEPS, $idx);
        $in = implode(',', array_fill(0, count($del), '?'));
        $this->pdo->prepare("DELETE FROM idrg_step WHERE no_rawat=? AND step IN ($in)")->execute(array_merge([$noRawat], $del));
    }

    private function progress(array $noRawats): array
    {
        if (!$noRawats) return [];
        $in = implode(',', array_fill(0, count($noRawats), '?'));
        $st = $this->pdo->prepare("SELECT no_rawat, step, status, message FROM idrg_step WHERE no_rawat IN ($in)");
        $st->execute(array_values($noRawats));
        $o = [];
        foreach ($st->fetchAll() as $r) $o[$r['no_rawat']][$r['step']] = ['status' => $r['status'], 'message' => $r['message']];
        return $o;
    }
}
