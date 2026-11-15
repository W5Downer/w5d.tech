<?php
declare(strict_types=1);

namespace W5D;

/** Runtime state shared by every compiled view in one request ($__env). */
final class ViewEnv
{
    private array $sections = [];
    private array $sectionStack = [];
    private array $pushes = [];
    private array $pushStack = [];
    private array $components = [];
    private array $loops = [];
    private array $onceSeen = [];
    private static array $shared = [];

    /** Template roots, first match wins. */
    public function roots(): array
    {
        return [ROOT . '/views/templates/' . Config::template(), ROOT . '/views/_shared', ROOT . '/views/_blocks'];
    }

    public static function share(string $k, mixed $v): void { self::$shared[$k] = $v; }

    public function find(string $name): ?string
    {
        $rel = str_replace('.', '/', $name);
        $cands = [ROOT . '/views/templates/' . Config::template() . '/' . $rel . '.blade.php'];
        if (str_starts_with($name, 'blocks.')) $cands[] = ROOT . '/views/_blocks/' . substr($rel, 7) . '.blade.php';
        $cands[] = ROOT . '/views/_shared/' . $rel . '.blade.php';
        foreach ($cands as $c) if (is_file($c)) return $c;
        return null;
    }

    public function exists(string $name): bool { return $this->find($name) !== null; }

    public function make(string $name, array $data = []): View
    {
        $p = $this->find($name);
        if ($p === null) throw new \RuntimeException("View [$name] not found");
        return new View($this, $p, $data + self::$shared);
    }

    public function scope(array $vars): array
    {
        foreach (array_keys($vars) as $k) {
            if (str_starts_with((string)$k, '__') || $k === 'this' || $k === 'loop' || $k === 'slot' || $k === 'attributes') unset($vars[$k]);
        }
        return $vars;
    }

    /* ----- sections ----- */
    public function startSection(string $n): void { $this->sectionStack[] = $n; ob_start(); }
    public function inlineSection(string $n, mixed $v): void { $this->sections[$n] = (string)$v; }
    public function stopSection(bool $show = false): string
    {
        $n = array_pop($this->sectionStack);
        $c = (string)ob_get_clean();
        if (!isset($this->sections[$n])) $this->sections[$n] = $c;
        return $show ? $this->sections[$n] : '';
    }
    public function yieldContent(string $n, string $default = ''): string { return $this->sections[$n] ?? $default; }
    public function hasSection(string $n): bool { return isset($this->sections[$n]); }

    /* ----- stacks ----- */
    public function startPush(string $n): void { $this->pushStack[] = $n; ob_start(); }
    public function stopPush(): void
    {
        $n = array_pop($this->pushStack);
        $this->pushes[$n][] = (string)ob_get_clean();
    }
    public function yieldPushContent(string $n): string { return implode('', $this->pushes[$n] ?? []); }
    public function once(string $id): bool
    {
        if (isset($this->onceSeen[$id])) return true;
        $this->onceSeen[$id] = true;
        return false;
    }

    /* ----- components ----- */
    public function startComponent(string $name, array $attrs): void
    {
        $this->components[] = ['name' => $name, 'attrs' => $attrs, 'slots' => [], 'cur' => null];
        ob_start();
    }
    public function slot(string $name): void
    {
        $i = count($this->components) - 1;
        $this->components[$i]['cur'] = $name;
        ob_start();
    }
    public function endSlot(): void
    {
        $i = count($this->components) - 1;
        $n = $this->components[$i]['cur'];
        $this->components[$i]['slots'][$n] = new Html((string)ob_get_clean());
        $this->components[$i]['cur'] = null;
    }
    public function renderComponent(): string
    {
        $c = array_pop($this->components);
        $default = (string)ob_get_clean();
        $slots = $c['slots'];
        $slot = new Html(trim($default));
        $data = $c['attrs'];
        foreach ($slots as $k => $v) $data[$k] = $v;
        $data['slot'] = $slot;
        $data['slots'] = $slots;
        $data['__attrs'] = $c['attrs'];
        $data['attributes'] = new AttrBag($c['attrs']);
        return $this->make('components.' . $c['name'], $data)->render();
    }
    public function props(array $defs, array $vars): array
    {
        $attrs = $vars['__attrs'] ?? [];
        $out = [];
        $rest = $attrs;
        foreach ($defs as $k => $v) {
            $name = is_int($k) ? $v : $k;
            $def = is_int($k) ? null : $v;
            $kebab = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));
            if (array_key_exists($name, $attrs)) { $out[$name] = $attrs[$name]; unset($rest[$name]); }
            elseif (array_key_exists($kebab, $attrs)) { $out[$name] = $attrs[$kebab]; unset($rest[$kebab]); }
            else $out[$name] = $def;
        }
        $out['attributes'] = new AttrBag($rest);
        return $out;
    }

    /* ----- loops ----- */
    public function addLoop(mixed $data): void
    {
        $n = is_countable($data) ? count($data) : null;
        $this->loops[] = (object)[
            'iteration' => 0, 'index' => -1, 'remaining' => $n, 'count' => $n,
            'first' => true, 'last' => $n === 1, 'odd' => false, 'even' => true,
            'depth' => count($this->loops) + 1, 'parent' => $this->loops ? end($this->loops) : null,
        ];
    }
    public function incrementLoopIndices(): void
    {
        $l = end($this->loops);
        $l->iteration++; $l->index++;
        $l->first = $l->iteration === 1;
        $l->odd = $l->iteration % 2 === 1; $l->even = !$l->odd;
        if ($l->count !== null) { $l->remaining = $l->count - $l->iteration; $l->last = $l->iteration === $l->count; }
    }
    public function popLoop(): void { array_pop($this->loops); }
    public function getLastLoop(): ?object { return $this->loops ? end($this->loops) : null; }

    /* ----- widgets (blocks) ----- */
    public function widget(string $type, array $data = []): string
    {
        $name = 'blocks.' . str_replace('/', '.', $type);
        if (!$this->exists($name)) { $name = 'blocks.unknown'; $data['__type'] = $type; }
        return $this->make($name, ['d' => $data, 'data' => $data])->render();
    }
}
