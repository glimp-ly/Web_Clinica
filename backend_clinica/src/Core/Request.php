<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public readonly string $method;
    public readonly string $route;
    /** @var list<string> */
    public readonly array $segments;
    private ?array $cachedBody = null;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);

        if (!empty($_GET['route'])) {
            $route = '/' . ltrim((string)$_GET['route'], '/');
        } else {
            $pos = strpos($uri, '/api');
            $route = $pos !== false ? substr($uri, $pos + 4) : $uri;
            if ($route === '' || $route === '/index.php') {
                $route = '/';
            }
        }

        $this->route = rtrim($route, '/') ?: '/';
        $this->segments = array_values(array_filter(explode('/', trim($this->route, '/')), 'strlen'));
    }

    public function body(): array
    {
        if ($this->cachedBody !== null) {
            return $this->cachedBody;
        }
        $raw = file_get_contents('php://input');
        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $this->cachedBody = $decoded;
                return $this->cachedBody;
            }
        }
        $this->cachedBody = $_POST ?: [];
        return $this->cachedBody;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}
