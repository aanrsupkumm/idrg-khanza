<?php
declare(strict_types=1);
namespace Idrg;

class Logger
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

    public function write(array $r): void
    {
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO idrg_log (method,nomor_sep,http_code,code,error_no,message,request_json,response_json,ms)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $st->execute([
                $r['method'] ?? '', $r['nomor_sep'] ?? null, $r['http'] ?? null, $r['code'] ?? null,
                $r['error_no'] ?? null, mb_substr((string)($r['message'] ?? ''), 0, 500),
                $r['request'] ?? null, $r['response'] ?? null, $r['ms'] ?? null,
            ]);
        } catch (\Throwable $e) {
            error_log('idrg log gagal: ' . $e->getMessage());
        }
    }
}
