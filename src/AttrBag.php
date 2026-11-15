<?php
declare(strict_types=1);

namespace W5D;

final class AttrBag implements \Stringable
{
    public function __construct(private array $a = []) {}
    public function get(string $k, mixed $d = null): mixed { return $this->a[$k] ?? $d; }
    public function merge(array $defaults): self
    {
        $a = $this->a;
        foreach ($defaults as $k => $v) {
            if ($k === 'class') $a['class'] = trim($v . ' ' . ($a['class'] ?? ''));
            elseif (!isset($a[$k])) $a[$k] = $v;
        }
        return new self($a);
    }
    public function only(array $keys): self { return new self(array_intersect_key($this->a, array_flip($keys))); }
    public function except(array $keys): self { return new self(array_diff_key($this->a, array_flip($keys))); }
    public function class(string $c): self { return $this->merge(['class' => $c]); }
    public function __toString(): string
    {
        $o = [];
        foreach ($this->a as $k => $v) {
            if ($v === false || $v === null || is_array($v) || is_object($v) && !$v instanceof \Stringable) continue;
            $o[] = $v === true ? e($k) : e($k) . '="' . e((string)$v) . '"';
        }
        return implode(' ', $o);
    }
}
