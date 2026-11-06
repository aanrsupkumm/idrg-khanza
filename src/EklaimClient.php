<?php
declare(strict_types=1);
namespace Idrg;

final class EklaimException extends \RuntimeException
{
    /** @var string|null */
    public $errorNo;
    /** @var array */
    public $payload;

    public function __construct(string $message, ?string $errorNo = null, array $payload = [])
    {
        parent::__construct($message);
        $this->errorNo = $errorNo;
        $this->payload = $payload;
    }
}

/**
 * Klien Web Service E-Klaim (iDRG/INA-CBG).
 * Mode debug   : JSON polos ke ws.php?mode=debug
 * Mode produksi: payload dienkripsi AES-256-CBC + signature HMAC-SHA256 (10 byte).
 */
class EklaimClient
{
    /** @var array */
    private $cfg;
    /** @var Logger|null */
    private $log;

    public function __construct(array $cfg, ?Logger $log = null)
    {
        $this->cfg = $cfg;
        $this->log = $log;
    }

    public function call(string $method, array $data = [], array $meta = []): array
    {
        $body  = ['metadata' => array_merge(['method' => $method], $meta), 'data' => $data ?: new \stdClass()];
        $json  = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $debug = (bool)($this->cfg['debug_mode'] ?? false);
        $url   = rtrim($this->cfg['base_url'], '/') . $this->cfg['endpoint'] . ($debug ? '?mode=debug' : '');
        $sep   = $meta['nomor_sep'] ?? ($data['nomor_sep'] ?? null);

        $t0 = microtime(true);
        list($raw, $http, $err) = $this->post(
            $url,
            $debug ? $json : $this->encrypt($json),
            $debug ? 'application/json' : 'application/x-www-form-urlencoded',
            (int)($this->cfg['timeout'] ?? 30)
        );
        $ms  = (int)((microtime(true) - $t0) * 1000);
        $log = ['method' => $method, 'nomor_sep' => $sep, 'http' => $http, 'ms' => $ms, 'request' => $json];

        if ($raw === false) {
            $this->write($log + ['message' => "Koneksi: $err"]);
            throw new EklaimException("Tidak dapat terhubung ke server E-Klaim: $err");
        }
        $raw = (string)$raw;
        try {
            $res = $this->parse($raw, $debug);
        } catch (EklaimException $e) {
            $this->write($log + ['message' => $e->getMessage(), 'response' => mb_substr($raw, 0, 8000)]);
            throw $e;
        }
        if ($res === null) {
            $msg = $this->explain($raw, $http);
            $this->write($log + ['message' => $msg, 'response' => mb_substr($raw, 0, 8000)]);
            throw new EklaimException($msg);
        }

        $code = (int)($res['metadata']['code'] ?? 0);
        $this->write($log + [
            'code' => $code, 'error_no' => $res['metadata']['error_no'] ?? null,
            'message' => $res['metadata']['message'] ?? '', 'response' => json_encode($res, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        if ($code !== 200) {
            throw new EklaimException(
                (string)($res['metadata']['message'] ?? 'Error E-Klaim'),
                $res['metadata']['error_no'] ?? null,
                $res
            );
        }
        return $res;
    }

    /** Hapus BOM & spasi di tepi. */
    private function clean(string $s): string
    {
        return trim((string)preg_replace('/^\xEF\xBB\xBF/', '', $s));
    }

    private function decode(string $text): ?array
    {
        $t = $this->clean($text);
        $r = json_decode($t, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (is_array($r)) return $r;
        // JSON yang didahului/diikuti pesan PHP (warning/notice) atau HTML
        $a = strpos($t, '{');
        $b = strrpos($t, '}');
        if ($a !== false && $b !== false && $b > $a) {
            $r = json_decode(substr($t, $a, $b - $a + 1), true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_array($r) && isset($r['metadata'])) return $r;
        }
        return null;
    }

    /**
     * Respons -> array, toleran terhadap: mode debug/terenkripsi yang tidak sesuai, BOM, pesan PHP di depan JSON.
     * @return array|null null bila bukan JSON
     */
    private function parse(string $raw, bool $debug): ?array
    {
        $body = $this->clean($raw);
        $hasEnv = (bool)preg_match('/BEGIN ENCRYPTED DATA/i', $body);
        $b64 = !$debug && strlen($body) > 40 && (bool)preg_match('~^[A-Za-z0-9+/=\r\n]+$~', $body);
        $text = $body;
        if ($hasEnv || $b64) {
            if ((string)($this->cfg['key'] ?? '') === '') {
                throw new EklaimException('Server E-Klaim membalas data terenkripsi, tetapi eklaim.key belum diisi di config.');
            }
            $text = $this->decrypt($this->stripEnvelope($body));
        }
        return $this->decode($text);
    }

    /** Pesan galat yang menyebut isi respons sebenarnya agar mudah ditelusuri. */
    private function explain(string $raw, int $http): string
    {
        $t = trim($raw);
        if ($t === '') return "Respons kosong dari server E-Klaim (HTTP $http). Periksa base_url/endpoint dan apakah layanan ws.php berjalan.";
        $snip = mb_substr(trim((string)preg_replace('/\s+/', ' ', strip_tags($t))), 0, 150);
        $hint = '';
        if (preg_match('/<(!doctype|html|body|head)/i', $t)) {
            $hint = 'Server mengirim halaman HTML, bukan JSON: base_url/endpoint kemungkinan salah, layanan E-Klaim error, atau ada proxy/login di depannya.';
        } elseif (preg_match('/(fatal error|parse error|warning:|notice:)/i', $t)) {
            $hint = 'Server E-Klaim mencetak error PHP.';
        } elseif ($http >= 400) {
            $hint = 'Server membalas kode HTTP error.';
        } else {
            $hint = 'Isi respons rusak/tidak lengkap (mis. karakter tak valid atau terpotong).';
        }
        return "Respons E-Klaim bukan JSON (HTTP $http). $hint Isi: \"$snip\"";
    }

    private function write(array $r): void
    {
        if ($this->log) $this->log->write($r);
    }

    /** @return array{0:string|false,1:int,2:string} */
    protected function post(string $url, string $payload, string $ctype, int $timeout): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: ' . $ctype],
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => $timeout,
            ]);
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return [$raw, $http, $err];
        }
        // Fallback tanpa ekstensi cURL
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => "Content-Type: $ctype\r\n", 'content' => $payload,
            'timeout' => $timeout, 'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        $http = 0;
        if (!empty($http_response_header[0]) && preg_match('~\s(\d{3})\s~', $http_response_header[0], $m)) $http = (int)$m[1];
        $e = error_get_last();
        return [$raw, $http, $raw === false ? (string)($e['message'] ?? 'gagal terhubung') : ''];
    }

    private function key(): string
    {
        $k = @hex2bin((string)($this->cfg['key'] ?? ''));
        if ($k === false || strlen($k) !== 32) {
            throw new EklaimException('Kunci E-Klaim harus 64 karakter hex (256-bit). Cek pengaturan eklaim.key');
        }
        return $k;
    }

    private function encrypt(string $data): string
    {
        $key = $this->key();
        $iv  = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $enc = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        $sig = substr(hash_hmac('sha256', $enc, $key, true), 0, 10);
        return chunk_split(base64_encode($sig . $iv . $enc));
    }

    private function decrypt(string $str): string
    {
        $key = $this->key();
        $dec = base64_decode($str);
        $sig = substr($dec, 0, 10);
        $iv  = substr($dec, 10, 16);
        $enc = substr($dec, 26);
        if (!hash_equals($sig, substr(hash_hmac('sha256', $enc, $key, true), 0, 10))) {
            throw new EklaimException('Signature respons tidak cocok: kunci enkripsi salah atau respons rusak.');
        }
        $out = openssl_decrypt($enc, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $out === false ? '' : $out;
    }

    private function stripEnvelope(string $raw): string
    {
        return trim(preg_replace('/-{2,}\s*(BEGIN|END) ENCRYPTED DATA\s*-{2,}/', '', $raw));
    }
}
