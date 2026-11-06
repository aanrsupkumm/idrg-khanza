<?php
declare(strict_types=1);
namespace Idrg;

/** Lampiran berkas digital tambahan per kunjungan (disimpan di luar webroot, disajikan lewat files.php). */
final class BerkasStore
{
    private const MIME = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                          'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
    /** @var \PDO */
    private $pdo;
    /** @var array */
    private $cfg;

    public function __construct(\PDO $pdo, array $cfg)
    {
        $this->pdo = $pdo;
        $this->cfg = $cfg['upload'] ?? [];
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS idrg_berkas (
               id INT AUTO_INCREMENT PRIMARY KEY, no_rawat VARCHAR(25) NOT NULL, judul VARCHAR(150) NOT NULL,
               nama_asli VARCHAR(255) NOT NULL, nama_simpan VARCHAR(64) NOT NULL, mime VARCHAR(60) NOT NULL,
               ukuran INT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY (no_rawat)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function allowed(): array
    {
        $a = $this->cfg['allowed'] ?? array_keys(self::MIME);
        return array_values(array_intersect(array_map('strtolower', $a), array_keys(self::MIME)));
    }

    private static function iniBytes(string $v): int
    {
        $v = trim($v);
        $n = (int)$v;
        switch (strtolower(substr($v, -1))) {
            case 'g': $n *= 1024;
            // no break
            case 'm': $n *= 1024;
            // no break
            case 'k': $n *= 1024;
        }
        return $n;
    }

    public function maxBytes(): int
    {
        $cfg = (int)(((float)($this->cfg['max_mb'] ?? 10)) * 1048576);
        $ini = [self::iniBytes((string)ini_get('upload_max_filesize')), self::iniBytes((string)ini_get('post_max_size'))];
        return max(1, min(array_filter(array_merge([$cfg], $ini))));
    }

    public function dir(): string
    {
        $d = $this->cfg['dir'] ?? dirname(__DIR__) . '/storage/uploads';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
            @file_put_contents($d . '/.htaccess', "Require all denied\n");
            @file_put_contents($d . '/index.html', '');
        }
        return $d;
    }

    public function list(string $no): array
    {
        $st = $this->pdo->prepare('SELECT id, judul, nama_asli, mime, ukuran, created_at FROM idrg_berkas WHERE no_rawat=? ORDER BY id');
        $st->execute([$no]);
        return $st->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM idrg_berkas WHERE id=?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function path(array $row): string
    {
        return $this->dir() . '/' . basename((string)$row['nama_simpan']);
    }

    public function dataUri(array $row): string
    {
        $f = $this->find((int)$row['id']);
        if (!$f || !is_file($this->path($f)) || filesize($this->path($f)) > 4 * 1048576) return '';
        return 'data:' . $f['mime'] . ';base64,' . base64_encode((string)file_get_contents($this->path($f)));
    }

    /** Validasi isi file (bukan hanya ekstensi). */
    private function detect(string $tmp, string $ext): string
    {
        if ($ext === 'pdf') {
            if (@file_get_contents($tmp, false, null, 0, 5) !== '%PDF-') throw new \RuntimeException('Isi file bukan PDF yang valid.');
            return 'application/pdf';
        }
        $i = @getimagesize($tmp);
        if (!$i) throw new \RuntimeException('Isi file bukan gambar yang valid.');
        if (($i['mime'] ?? '') !== self::MIME[$ext]) throw new \RuntimeException('Isi file tidak sesuai ekstensinya.');
        return self::MIME[$ext];
    }

    private static function uploadError(int $c): string
    {
        $m = [UPLOAD_ERR_INI_SIZE => 'Melebihi upload_max_filesize di php.ini', UPLOAD_ERR_FORM_SIZE => 'Melebihi batas ukuran form',
              UPLOAD_ERR_PARTIAL => 'Upload terputus, ulangi', UPLOAD_ERR_NO_FILE => 'Tidak ada file',
              UPLOAD_ERR_NO_TMP_DIR => 'Folder temp PHP tidak ada', UPLOAD_ERR_CANT_WRITE => 'Gagal menulis ke disk'];
        return $m[$c] ?? 'Upload gagal (kode ' . $c . ')';
    }

    /** @param array $f satu entri $_FILES (name,tmp_name,size,error) */
    public function save(string $no, string $judul, array $f): array
    {
        if (!preg_match('~^[0-9/]{8,25}$~', $no)) throw new \RuntimeException('no_rawat tidak valid.');
        $st = $this->pdo->prepare('SELECT 1 FROM reg_periksa WHERE no_rawat=?');
        $st->execute([$no]);
        if (!$st->fetchColumn()) throw new \RuntimeException('Kunjungan tidak ditemukan.');
        if ((int)$f['error'] !== UPLOAD_ERR_OK) throw new \RuntimeException($f['name'] . ': ' . self::uploadError((int)$f['error']));
        if (!is_uploaded_file($f['tmp_name'])) throw new \RuntimeException('Bukan file hasil upload.');

        $asli = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', (string)$f['name']));
        $ext = strtolower(pathinfo($asli, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowed(), true)) throw new \RuntimeException("$asli: jenis file tidak diizinkan (boleh: " . implode(', ', $this->allowed()) . ').');
        if ((int)$f['size'] > $this->maxBytes()) throw new \RuntimeException("$asli: melebihi " . BerkasHelper::size($this->maxBytes()) . '.');

        $cnt = $this->pdo->prepare('SELECT COUNT(*) FROM idrg_berkas WHERE no_rawat=?');
        $cnt->execute([$no]);
        if ((int)$cnt->fetchColumn() >= (int)($this->cfg['max_files'] ?? 50)) throw new \RuntimeException('Jumlah lampiran per kunjungan sudah mencapai batas.');

        $mime = $this->detect($f['tmp_name'], $ext);
        $simpan = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $this->dir() . '/' . $simpan)) throw new \RuntimeException('Gagal menyimpan file ke storage/uploads (cek izin tulis).');

        $judul = mb_substr(trim($judul) !== '' ? trim($judul) : pathinfo($asli, PATHINFO_FILENAME), 0, 150);
        $ins = $this->pdo->prepare('INSERT INTO idrg_berkas (no_rawat,judul,nama_asli,nama_simpan,mime,ukuran) VALUES (?,?,?,?,?,?)');
        $ins->execute([$no, $judul, mb_substr($asli, 0, 255), $simpan, $mime, (int)$f['size']]);
        return ['id' => (int)$this->pdo->lastInsertId(), 'judul' => $judul];
    }

    public function delete(int $id): bool
    {
        $r = $this->find($id);
        if (!$r) return false;
        @unlink($this->path($r));
        $this->pdo->prepare('DELETE FROM idrg_berkas WHERE id=?')->execute([$id]);
        return true;
    }
}
