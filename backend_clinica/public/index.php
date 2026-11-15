<?php
declare(strict_types=1);

date_default_timezone_set('America/Lima');

$baseDir = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($baseDir): void {
    if ($class === 'App\\Config\\Database') {
        $file = $baseDir . '/config/database.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }

    if (str_starts_with($class, 'App\\Config\\')) {
        $rel = substr($class, strlen('App\\Config\\'));
        $file = $baseDir . '/config/' . strtolower(str_replace('\\', '/', $rel)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }

    if (str_starts_with($class, 'App\\')) {
        $rel = substr($class, strlen('App\\'));
        $file = $baseDir . '/src/' . str_replace('\\', '/', $rel) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

use App\Core\Request;
use App\Core\Response;
use App\Router;

try {
    (new Router())->dispatch(new Request());
} catch (Throwable $e) {
    Response::error('Error interno del servidor: ' . $e->getMessage(), 500);
}
