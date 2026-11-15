<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use App\Core\Response;

final class Database
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $envHost = getenv('DB_HOST') ?: '127.0.0.1';
        $envPort = getenv('DB_PORT') ?: '3306';
        $envName = getenv('DB_NAME') ?: 'test_clinica';
        $envUser = getenv('DB_USER') ?: '';
        $envPass = getenv('DB_PASS') ?: '';
        $envSocket = getenv('DB_SOCKET') ?: '/run/mysqld/mysqld.sock';

        $candidates = [];
        if (file_exists($envSocket)) {
            $candidates[] = ["mysql:unix_socket={$envSocket};dbname={$envName};charset=utf8mb4", $envUser, $envPass];
            $candidates[] = ["mysql:unix_socket={$envSocket};dbname={$envName};charset=utf8mb4", 'glimp', ''];
            $candidates[] = ["mysql:unix_socket={$envSocket};dbname={$envName};charset=utf8mb4", 'root', ''];
        }
        $candidates[] = ["mysql:host={$envHost};port={$envPort};dbname={$envName};charset=utf8mb4", $envUser, $envPass];
        $candidates[] = ["mysql:host=127.0.0.1;port=3306;dbname={$envName};charset=utf8mb4", '', ''];
        $candidates[] = ["mysql:host=172.17.0.1;port=3307;dbname={$envName};charset=utf8mb4", 'root', 'secret'];
        $candidates[] = ["mysql:host=172.17.0.1;port=3306;dbname={$envName};charset=utf8mb4", '', ''];
        $candidates[] = ["mysql:host=host.docker.internal;port=3307;dbname={$envName};charset=utf8mb4", 'root', 'secret'];
        $candidates[] = ["mysql:host=red_integridad_db;port=3306;dbname={$envName};charset=utf8mb4", 'root', 'secret'];

        $lastException = null;
        foreach ($candidates as [$dsn, $user, $pass]) {
            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                return self::$pdo;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        Response::json([
            'ok' => false,
            'error' => 'Error de conexión a la base de datos MariaDB',
            'detail' => $lastException ? $lastException->getMessage() : 'Sin conexión disponible',
        ], 500);
    }
}
