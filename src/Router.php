<?php
declare(strict_types=1);

namespace W5D;

final class Router
{
    /** @var array<int,array{0:string,1:string,2:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $h): void
    {
        $re = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', rtrim($pattern, '/'));
        $this->routes[] = [$method, '#^' . $re . '/?$#u', $h];
    }
    public function get(string $p, callable $h): void { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void { $this->add('POST', $p, $h); }
    public function any(string $p, callable $h): void { $this->add('GET', $p, $h); $this->add('POST', $p, $h); }

    public function dispatch(Request $req): Response
    {
        // Path traversal prevention
        if (str_contains($req->path, '..') || str_contains($req->path, "\0")) return Response::html('Bad request', 400);

        // Canonical trailing slash for page routes (not files, not API)
        if ($req->method === 'GET' && $req->path !== '/' && !str_ends_with($req->path, '/')
            && !preg_match('#\.[a-z0-9]{2,5}$#i', $req->path) && !str_starts_with($req->path, '/api/')) {
            $q = $_SERVER['QUERY_STRING'] ?? '';
            return Response::redirect(url($req->path . '/') . ($q ? '?' . $q : ''), 301);
        }

        $allowed = false;
        foreach ($this->routes as [$m, $re, $h]) {
            if (!preg_match($re, $req->path, $mm)) continue;
            $allowed = true;
            if ($m !== $req->method && !($m === 'GET' && $req->method === 'HEAD')) continue;
            $req->params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
            $out = $h($req);
            if ($out instanceof Response) return $out;
            if (is_array($out)) return Response::json($out);
            return Response::html((string)$out);
        }
        if ($allowed) return Response::html('Method not allowed', 405);
        return not_found();
    }
}
