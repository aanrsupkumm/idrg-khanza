<?php
declare(strict_types=1);
namespace Idrg;

/** Mengumpulkan data riwayat perawatan satu kunjungan untuk berkas klaim. */
final class BerkasRepository
{
    /** @var \PDO */
    private $pdo;
    /** @var array */
    private $cfg;

    public function __construct(\PDO $pdo, array $cfg)
    {
        $this->pdo = $pdo;
        $this->cfg = $cfg;
    }

    /** Query aman: tabel/kolom yang tidak ada di versi Khanza tertentu tidak mematikan halaman. */
    private function q(string $sql, array $p, bool $one, array &$notes)
    {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($p);
            return $one ? ($st->fetch() ?: null) : $st->fetchAll();
        } catch (\Throwable $e) {
            $notes[] = 'Bagian dilewati: ' . mb_substr($e->getMessage(), 0, 160);
            return $one ? null : [];
        }
    }

    /** @var array<string,bool> */
    private $tblCache = [];

    private function tableExists(string $t): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $t)) return false;
        if (!isset($this->tblCache[$t])) {
            $st = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute([$t]);
            $this->tblCache[$t] = (bool)$st->fetchColumn();
        }
        return $this->tblCache[$t];
    }

    private function logo(): string
    {
        try {
            $b = $this->pdo->query('SELECT logo FROM setting LIMIT 1')->fetchColumn();
            if (!is_string($b) || $b === '' || strlen($b) > 400000) return '';
            $i = @getimagesizefromstring($b);
            return $i && strpos((string)$i['mime'], 'image/') === 0 ? 'data:' . $i['mime'] . ';base64,' . base64_encode($b) : '';
        } catch (\Throwable $e) { return ''; }
    }

    /** Form klinis per kelompok (resume, medis, perawat, operasi) yang tersedia untuk kunjungan ini. */
    private function forms(string $no, string $module, array &$notes): array
    {
        $out = ['resume' => [], 'medis' => [], 'perawat' => [], 'operasi' => []];
        $list = FormCatalog::FORMS;
        foreach (($this->cfg['berkas']['form_tambahan'] ?? []) as $x) { // [tabel, judul, kelompok]
            if (is_array($x) && count($x) >= 3 && isset($list[$x[2]])) $list[$x[2]][] = [$x[0], $x[1], 'generic', '*'];
        }
        $dok = []; $ptg = []; $n2 = [];
        foreach ($list as $grp => $items) {
            foreach ($items as $it) {
                list($tbl, $title, $kind, $mod) = $it;
                if ($mod !== '*' && $mod !== $module) continue;
                if (!$this->tableExists($tbl)) continue;
                foreach ($this->q("SELECT * FROM `$tbl` WHERE no_rawat=?", [$no], false, $n2) as $row) {
                    $kd = (string)($row['kd_dokter'] ?? '');
                    $nip = (string)($row['nip'] ?? ($row['kd_petugas'] ?? ''));
                    if ($kd !== '' && !isset($dok[$kd])) $dok[$kd] = (string)$this->scalar('SELECT nm_dokter FROM dokter WHERE kd_dokter=?', [$kd]);
                    if ($nip !== '' && !isset($ptg[$nip])) $ptg[$nip] = (string)$this->scalar('SELECT nama FROM petugas WHERE nip=?', [$nip]);
                    $out[$grp][] = ['table' => $tbl, 'title' => $title, 'kind' => $kind, 'row' => $row,
                        'dokter' => $dok[$kd] ?? '', 'petugas' => $ptg[$nip] ?? '', 'tanggal' => (string)($row['tanggal'] ?? '')];
                }
            }
        }
        return $out;
    }

    private function scalar(string $sql, array $p)
    {
        try { $st = $this->pdo->prepare($sql); $st->execute($p); return $st->fetchColumn(); }
        catch (\Throwable $e) { return ''; }
    }

    public function collect(string $no): array
    {
        $n = [];
        $n2 = [];   // catatan opsional (tidak ditampilkan)
        $a = [$no];

        $reg = $this->q(
            "SELECT rp.*, pol.nm_poli, d.nm_dokter, pj.png_jawab FROM reg_periksa rp
             LEFT JOIN poliklinik pol ON rp.kd_poli=pol.kd_poli
             LEFT JOIN dokter d ON rp.kd_dokter=d.kd_dokter
             LEFT JOIN penjab pj ON rp.kd_pj=pj.kd_pj WHERE rp.no_rawat=?", $a, true, $n);
        if (!$reg) return ['reg' => null];

        $soap = array_merge(
            array_map(function ($r) { $r['_jenis'] = 'Rawat Inap'; return $r; },
                $this->q('SELECT s.*, pt.nama AS _petugas FROM pemeriksaan_ranap s LEFT JOIN petugas pt ON s.nip=pt.nip WHERE s.no_rawat=? ORDER BY s.tgl_perawatan, s.jam_rawat', $a, false, $n)),
            array_map(function ($r) { $r['_jenis'] = 'Rawat Jalan'; return $r; },
                $this->q('SELECT s.*, pt.nama AS _petugas FROM pemeriksaan_ralan s LEFT JOIN petugas pt ON s.nip=pt.nip WHERE s.no_rawat=? ORDER BY s.tgl_perawatan, s.jam_rawat', $a, false, $n))
        );

        $resume = $this->q('SELECT * FROM resume_pasien_ranap WHERE no_rawat=?', $a, true, $n);
        if (!$resume) $resume = $this->q('SELECT * FROM resume_pasien WHERE no_rawat=?', $a, true, $n);

        // Tindakan: dokter (dr), petugas (pr), dokter+petugas (drpr), ralan & ranap
        $tindakan = [];
        $specs = [['Ralan', 'rawat_jl_dr', 'jns_perawatan', 'dr'], ['Ralan', 'rawat_jl_pr', 'jns_perawatan', 'pr'],
                  ['Ralan', 'rawat_jl_drpr', 'jns_perawatan', 'drpr'], ['Ranap', 'rawat_inap_dr', 'jns_perawatan_inap', 'dr'],
                  ['Ranap', 'rawat_inap_pr', 'jns_perawatan_inap', 'pr'], ['Ranap', 'rawat_inap_drpr', 'jns_perawatan_inap', 'drpr']];
        foreach ($specs as $s) {
            list($jenis, $tbl, $jns, $tipe) = $s;
            $pel = $tipe === 'dr' ? 'd.nm_dokter' : ($tipe === 'pr' ? 'pt.nama' : "CONCAT_WS(' & ', d.nm_dokter, pt.nama)");
            $join = ($tipe !== 'pr' ? ' LEFT JOIN dokter d ON r.kd_dokter=d.kd_dokter' : '')
                  . ($tipe !== 'dr' ? ' LEFT JOIN petugas pt ON r.nip=pt.nip' : '');
            foreach ($this->q("SELECT r.tgl_perawatan, r.jam_rawat, p.nm_perawatan, $pel AS pelaksana FROM $tbl r
                               JOIN $jns p ON r.kd_jenis_prw=p.kd_jenis_prw$join WHERE r.no_rawat=?", $a, false, $n) as $r) {
                $r['jenis'] = $jenis;
                $tindakan[] = $r;
            }
        }
        usort($tindakan, function ($x, $y) { return strcmp($x['tgl_perawatan'] . $x['jam_rawat'], $y['tgl_perawatan'] . $y['jam_rawat']); });

        $sep = $this->q('SELECT * FROM bridging_sep WHERE no_rawat=? LIMIT 1', $a, true, $n2);
        if (!$sep && $this->tableExists('bridging_sep_internal')) $sep = $this->q('SELECT * FROM bridging_sep_internal WHERE no_rawat=? LIMIT 1', $a, true, $n2);
        $jp = substr(trim((string)($sep['jnspelayanan'] ?? '')), 0, 1);
        $module = $jp === '1' ? 'ranap' : ($jp === '2' ? 'ralan' : ($reg['status_lanjut'] === 'Ranap' ? 'ranap' : 'ralan'));

        $labGroups = [];
        $labRows = $this->q('SELECT dpl.*, tpl.Pemeriksaan, tpl.satuan, tpl.nilai_rujukan_ld, tpl.nilai_rujukan_la, tpl.nilai_rujukan_pd, tpl.nilai_rujukan_pa
                             FROM detail_periksa_lab dpl JOIN template_laboratorium tpl ON dpl.id_template=tpl.id_template
                             WHERE dpl.no_rawat=? ORDER BY dpl.tgl_periksa, dpl.jam', $a, false, $n);
        $heads = [];
        foreach ($this->q('SELECT pl.kd_jenis_prw, pl.tgl_periksa, pl.jam, j.nm_perawatan, pt.nama AS petugas, d.nm_dokter AS perujuk FROM periksa_lab pl
                           JOIN jns_perawatan_lab j ON pl.kd_jenis_prw=j.kd_jenis_prw LEFT JOIN petugas pt ON pl.nip=pt.nip
                           LEFT JOIN dokter d ON pl.dokter_perujuk=d.kd_dokter WHERE pl.no_rawat=?', $a, false, $n2) as $h) {
            $heads[$h['kd_jenis_prw'] . '|' . $h['tgl_periksa'] . '|' . $h['jam']] = $h;
        }
        foreach ($labRows as $r) {
            $k = ($r['kd_jenis_prw'] ?? '') . '|' . $r['tgl_periksa'] . '|' . $r['jam'];
            if (!isset($labGroups[$k])) {
                $hd = $heads[$k] ?? [];
                $labGroups[$k] = ['nm' => (string)($hd['nm_perawatan'] ?? 'Pemeriksaan Laboratorium'), 'tgl' => $r['tgl_periksa'], 'jam' => $r['jam'],
                                  'perujuk' => (string)($hd['perujuk'] ?? ''), 'petugas' => (string)($hd['petugas'] ?? ''), 'items' => []];
            }
            $labGroups[$k]['items'][] = $r;
        }

        $lampiran = [];
        try { $lampiran = (new BerkasStore($this->pdo, $this->cfg))->list($no); }
        catch (\Throwable $e) { $n[] = 'Lampiran tambahan dilewati: ' . mb_substr($e->getMessage(), 0, 120); }

        return [
            'reg'       => $reg,
            'pasien'    => $this->q('SELECT * FROM pasien WHERE no_rkm_medis=?', [$reg['no_rkm_medis']], true, $n) ?: [],
            'instansi'  => $this->q('SELECT nama_instansi, alamat_instansi, kabupaten, propinsi, kontak FROM setting LIMIT 1', [], true, $n) ?: [],
            'sep'       => $sep,
            'module'    => $module,
            'logo'      => $this->logo(),
            'forms'     => $this->forms($no, $module, $n),
            'lab_groups' => array_values($labGroups),
            'diagnosa'  => $this->q('SELECT p.kd_penyakit, p.nm_penyakit, d.status FROM diagnosa_pasien d
                                     JOIN penyakit p ON d.kd_penyakit=p.kd_penyakit WHERE d.no_rawat=? ORDER BY d.prioritas', $a, false, $n),
            'prosedur'  => $this->q('SELECT p.kode, p.deskripsi_panjang, d.status FROM prosedur_pasien d
                                     JOIN icd9 p ON d.kode=p.kode WHERE d.no_rawat=? ORDER BY d.prioritas', $a, false, $n),
            'resume'    => $resume,
            'kamar'     => $this->q('SELECT ki.*, b.nm_bangsal FROM kamar_inap ki JOIN kamar k ON ki.kd_kamar=k.kd_kamar
                                     JOIN bangsal b ON k.kd_bangsal=b.kd_bangsal WHERE ki.no_rawat=? ORDER BY ki.tgl_masuk, ki.jam_masuk', $a, false, $n),
            'soap'      => $soap,
            'tindakan'  => $tindakan,
            'operasi'   => $this->q('SELECT o.tgl_operasi, p.nm_perawatan, o.jenis_anasthesi, d1.nm_dokter AS operator, da.nm_dokter AS anestesi
                                     FROM operasi o JOIN paket_operasi p ON o.kode_paket=p.kode_paket
                                     LEFT JOIN dokter d1 ON o.operator1=d1.kd_dokter LEFT JOIN dokter da ON o.dokter_anestesi=da.kd_dokter
                                     WHERE o.no_rawat=? ORDER BY o.tgl_operasi', $a, false, $n),
            'lab'       => $labRows,
            'periksa_rad' => $this->q('SELECT pr.tgl_periksa, pr.jam, jp.nm_perawatan, d.nm_dokter FROM periksa_radiologi pr
                                     JOIN jns_perawatan_radiologi jp ON pr.kd_jenis_prw=jp.kd_jenis_prw
                                     LEFT JOIN dokter d ON pr.kd_dokter=d.kd_dokter WHERE pr.no_rawat=? ORDER BY pr.tgl_periksa, pr.jam', $a, false, $n),
            'radiologi' => $this->q('SELECT * FROM hasil_radiologi WHERE no_rawat=? ORDER BY tgl_periksa, jam', $a, false, $n),
            'gambar_rad' => $this->q('SELECT * FROM gambar_radiologi WHERE no_rawat=?', $a, false, $n),
            'obat'      => $this->q('SELECT dpo.tgl_perawatan, dpo.jam, db.nama_brng, dpo.jml, db.kode_sat
                                     FROM detail_pemberian_obat dpo JOIN databarang db ON dpo.kode_brng=db.kode_brng
                                     WHERE dpo.no_rawat=? ORDER BY dpo.tgl_perawatan, dpo.jam', $a, false, $n),
            'resep'     => $this->q('SELECT rp.*, db.nama_brng, db.kode_sat FROM resep_pulang rp
                                     JOIN databarang db ON rp.kode_brng=db.kode_brng WHERE rp.no_rawat=?', $a, false, $n),
            'billing'   => $this->q('SELECT * FROM billing WHERE no_rawat=?', $a, false, $n),
            'berkas'    => $this->q('SELECT b.*, m.nama FROM berkas_digital_perawatan b
                                     LEFT JOIN master_berkas_digital m ON b.kode=m.kode WHERE b.no_rawat=?', $a, false, $n),
            'lampiran'  => $lampiran,
            'grouping'  => $this->q('SELECT step, status, message, response_json, updated_at FROM idrg_step WHERE no_rawat=?', $a, false, $n),
            'notes'     => $n,
        ];
    }
}
