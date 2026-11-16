<?php
declare(strict_types=1);

namespace W5D;

final class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $post;
    public array $params = [];

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);
        $base = rtrim((string)Env::get('APP_BASE_PATH', ''), '/');
        if ($base !== '' && str_starts_with($uri, $base)) $uri = substr($uri, strlen($base)) ?: '/';
        $this->path = '/' . ltrim(preg_replace('#/+#', '/', $uri), '/');
        $this->query = $_GET;
        $this->post = $_POST;
        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $j = json_decode((string)file_get_contents('php://input'), true);
            if (is_array($j)) $this->post = $j;
        }
    }

    public function input(string $k, mixed $d = null): mixed { return $this->post[$k] ?? $this->query[$k] ?? $d; }
    public function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
    public function isPost(): bool { return $this->method === 'POST'; }
    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
