<?php
declare(strict_types=1);

namespace W5D;

final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {}

    public static function html(string $html, int $status = 200): self { return new self($html, $status); }

    public static function json(mixed $data, int $status = 200, array $extra = []): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'Access-Control-Allow-Origin' => '*'] + $extra
        );
    }

    public static function text(string $t, string $type = 'text/plain', int $status = 200): self
    {
        return new self($t, $status, ['Content-Type' => $type . '; charset=utf-8']);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    public function withHeaders(array $h): self { $this->headers = $h + $this->headers; return $this; }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) header("$k: $v");
        echo $this->body;
    }
}
