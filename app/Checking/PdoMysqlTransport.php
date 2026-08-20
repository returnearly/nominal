<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class PdoMysqlTransport implements MysqlTransport
{
    public function connect(
        DatabaseUrl $url,
        int $timeoutSeconds,
        IpFamily $family,
        bool $verifyTls,
        ?string $command = null,
        ?string $proxyUrl = null,
    ): SocketOutcome {
        $started = hrtime(true);

        try {
            $pdo = $this->pdo($url, $timeoutSeconds, $verifyTls);
            $payload = $this->inspect($pdo, $url, $command);
            $pdo = null;
        } catch (Throwable $exception) {
            return SocketOutcome::failed(
                (int) ((hrtime(true) - $started) / 1_000_000),
                $exception->getMessage(),
            );
        }

        return SocketOutcome::ok(
            (int) ((hrtime(true) - $started) / 1_000_000),
            self::resolvedIp($url->host),
            self::json($payload),
        );
    }

    private function pdo(DatabaseUrl $url, int $timeoutSeconds, bool $verifyTls): PDO
    {
        if (! in_array('mysql', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('The PDO MySQL driver is not installed.');
        }

        $dsn = "mysql:host={$url->host};port={$url->port};charset=utf8mb4";

        if ($url->database !== null) {
            $dsn .= ';dbname='.$url->database;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => $timeoutSeconds,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;
        }

        if ($url->usesTls()) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $verifyTls;
        }

        try {
            return new PDO($dsn, $url->user ?? '', $url->password ?? '', $options);
        } catch (PDOException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inspect(PDO $pdo, DatabaseUrl $url, ?string $command): array
    {
        if ($command !== null) {
            return ['result' => $this->run($pdo, $command)];
        }

        $payload = [
            'version' => $this->scalar($pdo, 'SELECT VERSION()'),
        ];

        if ($url->database !== null) {
            $payload['database'] = $url->database;
            $payload['tables'] = $pdo->query('SHOW TABLES')?->fetchAll(PDO::FETCH_COLUMN) ?? [];
        }

        return $payload;
    }

    private function run(PDO $pdo, string $sql): mixed
    {
        $statement = $pdo->query($sql);

        if ($statement === false) {
            return null;
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return match (count($rows)) {
            0 => ['rowCount' => $statement->rowCount()],
            1 => $rows[0],
            default => $rows,
        };
    }

    private function scalar(PDO $pdo, string $sql): ?string
    {
        $value = $pdo->query($sql)?->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }

    private static function resolvedIp(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        $ip = gethostbyname($host);

        return $ip === $host ? null : $ip;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
