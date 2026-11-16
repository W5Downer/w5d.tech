<?php
declare(strict_types=1);

namespace W5D;

/** Pre-escaped HTML fragment (slots, rich text). */
final class Html implements \Stringable
{
    public function __construct(private string $html) {}
    public function __toString(): string { return $this->html; }
    public function isEmpty(): bool { return trim($this->html) === ''; }
}
