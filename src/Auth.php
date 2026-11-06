<?php
declare(strict_types=1);
namespace Idrg;

/** Login memakai akun SIMRS Khanza (tabel user + pegawai, kolom terenkripsi AES). */
final class Auth
{
    private static function ensure(\PDO $pdo): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $pdo->exec('CREATE TABLE IF NOT EXISTS idrg_login_fail (id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(45) NOT NULL,
                    at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY (ip)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('DELETE FROM idrg_login_fail WHERE at < (NOW() - INTERVAL 1 DAY)');
    }

    /** @return array{ok:bool,error?:string,nik?:string,nama?:string,ktp?:string} */
    public static function attempt(\PDO $pdo, array $cfg, string $user, string $pass, string $ip): array
    {
        $user = trim($user);
        if ($user === '' || $pass === '') return ['ok' => false, 'error' => 'Username dan password wajib diisi.'];
        self::ensure($pdo);

        $st = $pdo->prepare('SELECT COUNT(*) FROM idrg_login_fail WHERE ip = ? AND at > (NOW() - INTERVAL 10 MINUTE)');
        $st->execute([$ip]);
        if ((int)$st->fetchColumn() >= 5) return ['ok' => false, 'error' => 'Terlalu banyak percobaan gagal. Coba lagi dalam 10 menit.'];

        $ku = (string)($cfg['auth']['key_user'] ?? 'nur');
        $kp = (string)($cfg['auth']['key_pass'] ?? 'windi');
        $from = 'FROM `user` u JOIN pegawai p ON AES_DECRYPT(u.id_user, ?) = p.nik
                 WHERE AES_DECRYPT(u.id_user, ?) = ? AND AES_DECRYPT(u.password, ?) = ? LIMIT 1';
        try {
            $q = $pdo->prepare('SELECT p.nama, p.no_ktp ' . $from);
            $q->execute([$ku, $ku, $user, $kp, $pass]);
        } catch (\Throwable $e) { // versi Khanza tanpa pegawai.no_ktp
            $q = $pdo->prepare("SELECT p.nama, '' AS no_ktp " . $from);
            $q->execute([$ku, $ku, $user, $kp, $pass]);
        }
        $row = $q->fetch();

        $allowed = $cfg['auth']['allowed_users'] ?? [];
        if ($row && $allowed && !in_array($user, array_map('strval', $allowed), true)) {
            return ['ok' => false, 'error' => 'Akun ini tidak diizinkan mengakses modul klaim.'];
        }
        if (!$row) {
            $pdo->prepare('INSERT INTO idrg_login_fail (ip) VALUES (?)')->execute([$ip]);
            usleep(400000);
            return ['ok' => false, 'error' => 'Username atau password salah.'];
        }
        $pdo->prepare('DELETE FROM idrg_login_fail WHERE ip = ?')->execute([$ip]);
        return ['ok' => true, 'nik' => $user, 'nama' => (string)$row['nama'], 'ktp' => trim((string)$row['no_ktp'])];
    }
}
