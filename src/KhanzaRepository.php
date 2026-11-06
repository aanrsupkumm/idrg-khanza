<?php
declare(strict_types=1);
namespace Idrg;

/**
 * Akses baca ke database SIMRS Khanza (schema `sik`).
 * Aturan pengambilan data mengikuti halaman "Klaim Baru Manual" bawaan Khanza.
 */
class KhanzaRepository
{
    /** @var \PDO */
    protected $pdo;
    /** @var array */
    protected $cfg;

    public function __construct(\PDO $pdo, array $cfg)
    {
        $this->pdo = $pdo;
        $this->cfg = $cfg;
    }

    private function one(string $sql, array $p = [])
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchColumn();
    }

    /** Gabungan bridging_sep + bridging_sep_internal (bila tabelnya ada). */
    private function sepSub(): string
    {
        static $has = null;
        if ($has === null) {
            $has = (bool)$this->pdo->query("SHOW TABLES LIKE 'bridging_sep_internal'")->fetchColumn();
        }
        $cols = 'no_rawat, no_sep, no_kartu, klsrawat, klsnaik, jnspelayanan, asal_rujukan, tglpulang, tglsep, nmpolitujuan, `user` AS petugas';
        $sql = "SELECT $cols, 'sep' AS sep_tipe FROM bridging_sep";
        if ($has) $sql .= " UNION ALL SELECT $cols, 'internal' AS sep_tipe FROM bridging_sep_internal";
        return "($sql)";
    }

    /** @param string $bayar 'Sudah' | 'Belum' | '' (semua) — filter reg_periksa.status_bayar */
    public function list(string $from, string $to, string $lanjut = '', string $q = '', string $bayar = ''): array
    {
        $sql = "SELECT r.no_rawat, r.no_rkm_medis, r.tgl_registrasi, r.jam_reg, r.status_lanjut, r.status_bayar, p.nm_pasien, d.nm_dokter,
                       s.no_sep, s.no_kartu, s.tglsep, s.tglpulang, s.jnspelayanan, s.nmpolitujuan, s.sep_tipe, s.petugas
                FROM reg_periksa r
                JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
                JOIN " . $this->sepSub() . " s ON s.no_rawat = r.no_rawat
                LEFT JOIN dokter d ON d.kd_dokter = r.kd_dokter
                WHERE DATE(s.tglsep) BETWEEN :f AND :t";
        $a = [':f' => $from, ':t' => $to];
        if (in_array($lanjut, ['Ralan', 'Ranap'], true)) {
            $sql .= ' AND r.status_lanjut = :l';
            $a[':l'] = $lanjut;
        }
        if ($bayar === 'Sudah' || $bayar === 'Belum') {
            $sql .= ' AND r.status_bayar = :b';
            $a[':b'] = $bayar . ' Bayar';
        }
        if ($q !== '') {
            $sql .= ' AND (s.no_sep LIKE :q1 OR r.no_rkm_medis LIKE :q2 OR p.nm_pasien LIKE :q3 OR r.no_rawat LIKE :q4 OR s.no_kartu LIKE :q5)';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $k) $a[":$k"] = "%$q%";
        }
        $sql .= ' ORDER BY s.tglsep DESC, r.jam_reg DESC LIMIT 500';
        $st = $this->pdo->prepare($sql);
        $st->execute($a);
        $rows = $st->fetchAll();

        $sent = $this->khanzaSent(array_column($rows, 'no_sep'));
        foreach ($rows as &$r) $r['khanza_kirim'] = isset($sent[$r['no_sep']]);
        return $rows;
    }

    /** No. SEP yang sudah tercatat terkirim oleh fitur klaim bawaan Khanza (read-only). */
    private function khanzaSent(array $seps): array
    {
        $o = [];
        if (!$seps) return $o;
        $in = implode(',', array_fill(0, count($seps), '?'));
        foreach (['inacbg_klaim_baru', 'inacbg_klaim_baru_internal'] as $t) {
            try {
                $st = $this->pdo->prepare("SELECT no_sep FROM $t WHERE no_sep IN ($in)");
                $st->execute(array_values($seps));
                foreach ($st->fetchAll() as $r) $o[$r['no_sep']] = true;
            } catch (\Throwable $e) { /* tabel tidak ada di versi ini */ }
        }
        return $o;
    }

    public function header(string $noRawat): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT r.no_rawat, r.no_rkm_medis, r.tgl_registrasi, r.jam_reg, r.status_lanjut, r.kd_poli, r.stts,
                    p.nm_pasien, p.jk, p.tgl_lahir, p.no_ktp, p.no_peserta, d.nm_dokter,
                    s.no_sep, s.no_kartu, s.klsrawat, s.klsnaik, s.jnspelayanan, s.asal_rujukan, s.tglpulang, s.tglsep,
                    s.nmpolitujuan, s.petugas
             FROM reg_periksa r
             JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
             LEFT JOIN dokter d ON d.kd_dokter = r.kd_dokter
             LEFT JOIN " . $this->sepSub() . " s ON s.no_rawat = r.no_rawat
             WHERE r.no_rawat = ? LIMIT 1"
        );
        $st->execute([$noRawat]);
        return $st->fetch() ?: null;
    }

    /** Kamar inap: masuk pertama, total lama, dan status pulang kamar terakhir. */
    public function ranap(string $noRawat): ?array
    {
        $r = $this->pdo->prepare("SELECT MIN(CONCAT(tgl_masuk,' ',jam_masuk)) masuk, SUM(lama) lama FROM kamar_inap WHERE no_rawat = ?");
        $r->execute([$noRawat]);
        $r = $r->fetch();
        if (!$r || !$r['masuk']) return null;
        $st = $this->pdo->prepare('SELECT tgl_keluar, jam_keluar, stts_pulang FROM kamar_inap WHERE no_rawat = ? ORDER BY tgl_masuk DESC, jam_masuk DESC LIMIT 1');
        $st->execute([$noRawat]);
        $last = $st->fetch();
        $r['stts_pulang'] = $last['stts_pulang'] ?? '-';
        $r['pulang'] = ($last && $last['stts_pulang'] !== '-') ? $last['tgl_keluar'] . ' ' . $last['jam_keluar'] : null;
        return $r;
    }

    public function icuLos(string $noRawat): int
    {
        $kw = $this->cfg['rs']['icu_keywords'] ?? [];
        if (!$kw) return 0;
        $or = implode(' OR ', array_fill(0, count($kw), 'b.nm_bangsal LIKE ?'));
        return (int)$this->one(
            "SELECT COALESCE(SUM(ki.lama),0) FROM kamar_inap ki
             JOIN kamar k ON k.kd_kamar = ki.kd_kamar JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
             WHERE ki.no_rawat = ? AND ($or)",
            array_merge([$noRawat], array_map(function ($k) { return "%$k%"; }, $kw))
        );
    }

    /** @return array{0:string,1:string}|null [sistole, diastole] dari pemeriksaan terakhir */
    public function tensi(string $noRawat, bool $ranap): ?array
    {
        $tbl = $ranap ? 'pemeriksaan_ranap' : 'pemeriksaan_ralan';
        $t = (string)$this->one("SELECT tensi FROM $tbl WHERE no_rawat = ? ORDER BY tgl_perawatan DESC, jam_rawat DESC LIMIT 1", [$noRawat]);
        $p = explode('/', $t);
        $s = trim($p[0] ?? '');
        $d = trim($p[1] ?? '');
        return ($s !== '' && $d !== '') ? [$s, $d] : null;
    }

    /** Status pulang -> kode E-Klaim, urutan prioritas sama dengan halaman klaim Khanza. */
    public function dischargeStatus(string $noRawat): string
    {
        $st = $this->pdo->prepare('SELECT DISTINCT stts_pulang FROM kamar_inap WHERE no_rawat = ?');
        $st->execute([$noRawat]);
        $have = array_map('strtolower', array_column($st->fetchAll(), 'stts_pulang'));
        $order = [['sembuh', '1'], ['sehat', '1'], ['rujuk', '2'], ['aps', '3'], ['pulang paksa', '3'], ['meninggal', '4'], ['+', '4'],
                  ['atas persetujuan dokter', '1'], ['atas permintaan sendiri', '3'], ['lain-lain', '5']];
        foreach ($order as $o) if (in_array($o[0], $have, true)) return $o[1];
        return '1';
    }

    /** DPJP: seluruh dokter di dpjp_ranap (dipisah #), jika kosong dokter registrasi. */
    public function dpjp(string $noRawat): string
    {
        try {
            $st = $this->pdo->prepare('SELECT d.nm_dokter FROM dpjp_ranap x JOIN dokter d ON x.kd_dokter = d.kd_dokter WHERE x.no_rawat = ?');
            $st->execute([$noRawat]);
            $n = array_column($st->fetchAll(), 'nm_dokter');
            if ($n) return implode('#', $n);
        } catch (\Throwable $e) { /* tabel dpjp_ranap tidak ada */ }
        return (string)$this->one('SELECT d.nm_dokter FROM reg_periksa r JOIN dokter d ON r.kd_dokter = d.kd_dokter WHERE r.no_rawat = ?', [$noRawat]);
    }

    public function beratBayi(string $noRm): int
    {
        try { return (int)$this->one('SELECT berat_badan FROM pasien_bayi WHERE no_rkm_medis = ? LIMIT 1', [$noRm]); }
        catch (\Throwable $e) { return 0; }
    }

    /** @return array<int,array{code:string,name:string,im:string}> urut prioritas */
    public function diagnosaDetail(string $noRawat): array
    {
        try {
            $st = $this->pdo->prepare('SELECT d.kd_penyakit code, p.nm_penyakit name, p.im FROM diagnosa_pasien d
                                       LEFT JOIN penyakit p ON d.kd_penyakit = p.kd_penyakit WHERE d.no_rawat = ? ORDER BY d.prioritas ASC');
            $st->execute([$noRawat]);
        } catch (\Throwable $e) { // versi Khanza tanpa kolom im
            $st = $this->pdo->prepare("SELECT d.kd_penyakit code, p.nm_penyakit name, '0' im FROM diagnosa_pasien d
                                       LEFT JOIN penyakit p ON d.kd_penyakit = p.kd_penyakit WHERE d.no_rawat = ? ORDER BY d.prioritas ASC");
            $st->execute([$noRawat]);
        }
        return $st->fetchAll();
    }

    /** @return array<int,array{code:string,name:string,qty:int,im:string}> urut prioritas */
    public function prosedurDetail(string $noRawat): array
    {
        try {
            $st = $this->pdo->prepare('SELECT d.kode code, i.deskripsi_panjang name, d.jumlah qty, i.im FROM prosedur_pasien d
                                       LEFT JOIN icd9 i ON d.kode = i.kode WHERE d.no_rawat = ? ORDER BY d.prioritas ASC');
            $st->execute([$noRawat]);
        } catch (\Throwable $e) {
            $st = $this->pdo->prepare("SELECT d.kode code, i.deskripsi_panjang name, 1 qty, '0' im FROM prosedur_pasien d
                                       LEFT JOIN icd9 i ON d.kode = i.kode WHERE d.no_rawat = ? ORDER BY d.prioritas ASC");
            $st->execute([$noRawat]);
        }
        return array_map(function ($r) { $r['qty'] = max(1, (int)$r['qty']); return $r; }, $st->fetchAll());
    }

    /** Pencarian master ICD Khanza. $nonIm=true: hanya kode non-IM (untuk INACBG). */
    public function searchIcd(string $type, string $q, bool $nonIm): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        $like = "%$q%";
        if ($type === 'icd9') {
            $sql = 'SELECT kode code, deskripsi_panjang name, im FROM icd9 WHERE (kode LIKE ? OR deskripsi_panjang LIKE ?)';
        } else {
            $sql = 'SELECT kd_penyakit code, nm_penyakit name, im FROM penyakit WHERE (kd_penyakit LIKE ? OR nm_penyakit LIKE ?)';
        }
        $sql .= ($nonIm ? " AND im = '0'" : '') . ' ORDER BY 1 LIMIT 20';
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute([$like, $like]);
        } catch (\Throwable $e) {
            $st = $this->pdo->prepare(str_replace([" AND im = '0'", ', im FROM'], ['', ", '0' im FROM"], $sql));
            $st->execute([$like, $like]);
        }
        return $st->fetchAll();
    }

    /** @return array<string,float> status billing => total */
    public function billing(string $noRawat): array
    {
        $st = $this->pdo->prepare('SELECT status, SUM(totalbiaya) total FROM billing WHERE no_rawat = ? GROUP BY status');
        $st->execute([$noRawat]);
        $o = [];
        foreach ($st->fetchAll() as $r) $o[$r['status']] = (float)$r['total'];
        return $o;
    }
}
