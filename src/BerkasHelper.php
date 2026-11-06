<?php
declare(strict_types=1);
namespace Idrg;

final class BerkasHelper
{
    /** @return array{0:string,1:int} [teks umur, tahun] */
    public static function umur(string $lahir, string $ref): array
    {
        try {
            $d = (new \DateTime(substr($lahir, 0, 10)))->diff(new \DateTime(substr($ref, 0, 10)));
            return [$d->y . ' th ' . $d->m . ' bln', (int)$d->y];
        } catch (\Throwable $e) {
            return ['-', 0];
        }
    }

    public static function rupiah(float $v): string
    {
        return 'Rp ' . number_format($v, 0, ',', '.');
    }

    public static function size(int $b): string
    {
        if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
        if ($b >= 1024) return round($b / 1024) . ' KB';
        return $b . ' B';
    }

    /** Nilai rujukan: pakai yang tersimpan saat pemeriksaan, jika kosong pakai template menurut JK & umur. */
    public static function labRef(array $row, string $jk, int $tahun): string
    {
        $v = trim((string)($row['nilai_rujukan'] ?? ''));
        if ($v !== '') return $v;
        $dewasa = $tahun >= 18;
        $col = $jk === 'L' ? ($dewasa ? 'ld' : 'la') : ($dewasa ? 'pd' : 'pa');
        return trim((string)($row['nilai_rujukan_' . $col] ?? ''));
    }

    private static function num(string $s): ?float
    {
        $s = trim(str_replace(',', '.', $s), " .");
        return is_numeric($s) ? (float)$s : null;
    }

    /** 'H' (tinggi), 'L' (rendah) atau '' (normal / tidak dapat dinilai). Heuristik sederhana. */
    public static function labFlag(string $nilai, string $rujukan): string
    {
        $n = self::num($nilai);
        if ($n === null) return '';
        $r = trim($rujukan);
        if (preg_match('/^(<=|>=|<|>)\s*([\d.,]+)/', $r, $m)) {
            $x = self::num($m[2]);
            if ($x === null) return '';
            switch ($m[1]) {
                case '<':  return $n >= $x ? 'H' : '';
                case '<=': return $n > $x ? 'H' : '';
                case '>':  return $n <= $x ? 'L' : '';
                default:   return $n < $x ? 'L' : '';
            }
        }
        if (preg_match('/^([\d.,]+)\s*[-–]\s*([\d.,]+)/', $r, $m)) {
            $lo = self::num($m[1]);
            $hi = self::num($m[2]);
            if ($lo === null || $hi === null) return '';
            return $n < $lo ? 'L' : ($n > $hi ? 'H' : '');
        }
        return '';
    }
}
