<?php
declare(strict_types=1);

namespace W5D;

/**
 * Blade-compatible template compiler (pure PHP, no Composer, no framework).
 *
 * Supported syntax (a deliberate subset of Laravel Blade, so any *.blade.php
 * file in views/templates/w5d can also be dropped into a Laravel project):
 *   {{ }} {!! !!} {{-- --}}  @extends @section/@endsection/@show @yield @parent-less
 *   @include @includeIf @includeWhen @push/@endpush @stack @once
 *   @if/@elseif/@else/@endif @unless @isset @empty(expr) @auth-less
 *   @foreach (+$loop) @forelse/@empty/@endforelse @for @while @switch/@case
 *   @php/@endphp @php(expr) @break @continue @json @class @props @csrf
 *   @widget('type', $data)  @icon('name','class')  @t('key')
 *   <x-name attr="v" :bound="expr"> … <x-slot:name> … </x-name>  (anonymous components)
 */
final class Blade
{
    private int $forelse = 0;
    /** @var list<string> */
    private array $forelseStack = [];
    private bool $hasExtends = false;
    private array $footer = [];

    public function compile(string $src): string
    {
        $this->forelse = 0;
        $this->forelseStack = [];
        $this->hasExtends = false;
        $this->footer = [];

        // raw @php blocks first (protect their content)
        $raw = [];
        $src = preg_replace_callback('/(?<![\w@])@php\s*(?!\()(.*?)@endphp/s', function ($m) use (&$raw) {
            $k = "\x02RAW" . count($raw) . "\x03";
            $raw[$k] = '<?php ' . $m[1] . ' ?>';
            return $k;
        }, $src);
        // verbatim
        $src = preg_replace_callback('/@verbatim(.*?)@endverbatim/s', function ($m) use (&$raw) {
            $k = "\x02RAW" . count($raw) . "\x03";
            $raw[$k] = $m[1];
            return $k;
        }, $src);

        // comments
        $src = preg_replace('/\{\{--.*?--\}\}/s', '', $src);
        // escaped echo
        $src = preg_replace('/@\{\{/', '@@ESC_OPEN@@', $src);
        // raw + escaped echoes
        $src = preg_replace_callback('/\{!!\s*(.+?)\s*!!\}/s', fn($m) => '<?php echo ' . $m[1] . '; ?>', $src);
        $src = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', fn($m) => '<?php echo e(' . $m[1] . '); ?>', $src);
        $src = str_replace('@@ESC_OPEN@@', '{{', $src);

        $src = $this->compileComponents($src);
        $src = $this->compileDirectives($src);

        $out = $this->hasExtends
            ? '<?php ob_start(); ?>' . $src . '<?php ob_end_clean(); ?>' . implode('', $this->footer)
            : $src;
        return strtr($out, $raw);
    }

    /* ---------------------------- components ---------------------------- */

    private function compileComponents(string $src): string
    {
        // opening / self-closing tags
        $src = preg_replace_callback(
            '/<x-([\w.\-:]+)((?:[^>"\']|"[^"]*"|\'[^\']*\')*?)(\/?)>/s',
            function ($m) {
                $name = $m[1];
                $attrs = $this->attrs($m[2]);
                if ($name === 'slot' || str_starts_with($name, 'slot:')) {
                    $slot = $name === 'slot'
                        ? (preg_match('/name\s*=\s*["\']([\w\-]+)["\']/', $m[2], $nm) ? $nm[1] : 'default')
                        : substr($name, 5);
                    return "<?php \$__env->slot('{$slot}'); ?>";
                }
                $o = "<?php \$__env->startComponent('{$name}', {$attrs}); ?>";
                return $m[3] === '/' ? $o . '<?php echo $__env->renderComponent(); ?>' : $o;
            },
            $src
        );
        // closing tags
        return preg_replace_callback('/<\/x-([\w.\-:]+)\s*>/', function ($m) {
            return ($m[1] === 'slot' || str_starts_with($m[1], 'slot:'))
                ? '<?php $__env->endSlot(); ?>'
                : '<?php echo $__env->renderComponent(); ?>';
        }, $src);
    }

    private function attrs(string $s): string
    {
        $pairs = [];
        preg_match_all('/([:@\w\-.]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'))?/s', $s, $mm, PREG_SET_ORDER);
        foreach ($mm as $a) {
            $k = $a[1];
            if ($k === '/') continue;
            if (count($a) < 3) { $pairs[] = var_export($k, true) . ' => true'; continue; }
            $v = count($a) >= 4 ? $a[3] : $a[2];
            if ($k[0] === ':') {
                $pairs[] = var_export(substr($k, 1), true) . ' => (' . html_entity_decode($v) . ')';
            } else {
                $lit = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', fn($x) => "' . e({$x[1]}) . '", var_export($v, true));
                $pairs[] = var_export($k, true) . ' => ' . $lit;
            }
        }
        return '[' . implode(', ', $pairs) . ']';
    }

    /* ----------------------------- directives --------------------------- */

    private const NAMES = [
        'extends', 'section', 'endsection', 'show', 'yield', 'include', 'includeIf', 'includeWhen',
        'push', 'endpush', 'stack', 'once', 'endonce',
        'if', 'elseif', 'else', 'endif', 'unless', 'endunless', 'isset', 'endisset', 'empty', 'endempty',
        'foreach', 'endforeach', 'forelse', 'endforelse', 'for', 'endfor', 'while', 'endwhile',
        'switch', 'case', 'default', 'endswitch', 'break', 'continue', 'php', 'json', 'class', 'props',
        'csrf', 'widget', 'icon', 'lang', 'auth', 'endauth', 'guest', 'endguest',
    ];

    private function compileDirectives(string $src): string
    {
        $names = implode('|', self::NAMES);
        $out = '';
        $len = strlen($src);
        $i = 0;
        while ($i < $len) {
            if (!preg_match('/(?<![\w@])@(' . $names . ')\b/', $src, $m, PREG_OFFSET_CAPTURE, $i)) {
                $out .= substr($src, $i);
                break;
            }
            $pos = $m[0][1];
            $name = $m[1][0];
            $out .= substr($src, $i, $pos - $i);
            $j = $pos + strlen($m[0][0]);
            $args = null;
            // optional whitespace then "(" => balanced args
            $k = $j;
            while ($k < $len && ($src[$k] === ' ' || $src[$k] === "\t")) $k++;
            if ($k < $len && $src[$k] === '(') {
                [$args, $end] = $this->balanced($src, $k);
                $j = $end;
            }
            $out .= $this->directive($name, $args);
            // swallow a single newline after bare block directives for cleaner output
            $i = $j;
        }
        return $out;
    }

    /** @return array{0:string,1:int} */
    private function balanced(string $s, int $start): array
    {
        $depth = 0;
        $q = null;
        $n = strlen($s);
        for ($i = $start; $i < $n; $i++) {
            $c = $s[$i];
            if ($q !== null) {
                if ($c === '\\') { $i++; continue; }
                if ($c === $q) $q = null;
                continue;
            }
            if ($c === '"' || $c === "'") { $q = $c; continue; }
            if ($c === '(') $depth++;
            elseif ($c === ')') {
                $depth--;
                if ($depth === 0) return [substr($s, $start + 1, $i - $start - 1), $i + 1];
            }
        }
        return [substr($s, $start + 1), $n];
    }

    private function directive(string $name, ?string $a): string
    {
        $a = $a === null ? null : html_entity_decode($a, ENT_QUOTES);
        switch ($name) {
            case 'extends':
                $this->hasExtends = true;
                $this->footer[] = "<?php echo \$__env->make({$a}, \$__env->scope(get_defined_vars()))->render(); ?>";
                return '';
            case 'section':
                if ($a !== null && str_contains($a, ',')) {
                    [$n, $v] = $this->split2($a);
                    return "<?php \$__env->inlineSection({$n}, {$v}); ?>";
                }
                return "<?php \$__env->startSection({$a}); ?>";
            case 'endsection': return '<?php $__env->stopSection(); ?>';
            case 'show': return '<?php echo $__env->stopSection(true); ?>';
            case 'yield':
                if ($a !== null && str_contains($a, ',')) {
                    [$n, $v] = $this->split2($a);
                    return "<?php echo \$__env->yieldContent({$n}, {$v}); ?>";
                }
                return "<?php echo \$__env->yieldContent({$a}); ?>";
            case 'include':
                return $this->inc($a, '');
            case 'includeIf':
                return $this->inc($a, 'if');
            case 'includeWhen':
                [$cond, $rest] = $this->split2($a);
                return '<?php if (' . $cond . '): ' . ltrim(substr($this->inc($rest, ''), 5), ' ');
            case 'push': return "<?php \$__env->startPush({$a}); ?>";
            case 'endpush': return '<?php $__env->stopPush(); ?>';
            case 'stack': return "<?php echo \$__env->yieldPushContent({$a}); ?>";
            case 'once': return '<?php if (!$__env->once(__FILE__ . ":" . __LINE__)): ?>';
            case 'endonce': return '<?php endif; ?>';
            case 'if': return "<?php if ({$a}): ?>";
            case 'elseif': return "<?php elseif ({$a}): ?>";
            case 'else': return '<?php else: ?>';
            case 'endif': return '<?php endif; ?>';
            case 'unless': return "<?php if (!({$a})): ?>";
            case 'endunless': return '<?php endif; ?>';
            case 'isset': return "<?php if (isset({$a})): ?>";
            case 'endisset': return '<?php endif; ?>';
            case 'auth': return '<?php if (\W5D\Auth::check()): ?>';
            case 'endauth': return '<?php endif; ?>';
            case 'guest': return '<?php if (!\W5D\Auth::check()): ?>';
            case 'endguest': return '<?php endif; ?>';
            case 'empty':
                if ($a !== null) return "<?php if (empty({$a})): ?>";
                $id = array_pop($this->forelseStack);
                return "<?php endforeach; \$__env->popLoop(); \$loop = \$__env->getLastLoop(); if (\$__empty_{$id}): ?>";
            case 'endempty': return '<?php endif; ?>';
            case 'foreach':
                [$data, $as] = $this->splitAs((string)$a);
                return "<?php \$__loopData = {$data}; \$__env->addLoop(\$__loopData); foreach (\$__loopData as {$as}): \$__env->incrementLoopIndices(); \$loop = \$__env->getLastLoop(); ?>";
            case 'endforeach':
                return '<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>';
            case 'forelse':
                [$data, $as] = $this->splitAs((string)$a);
                $id = ++$this->forelse;
                $this->forelseStack[] = $id;
                return "<?php \$__empty_{$id} = true; \$__loopData = {$data}; \$__env->addLoop(\$__loopData); foreach (\$__loopData as {$as}): \$__empty_{$id} = false; \$__env->incrementLoopIndices(); \$loop = \$__env->getLastLoop(); ?>";
            case 'endforelse': return '<?php endif; ?>';
            case 'for': return "<?php for ({$a}): ?>";
            case 'endfor': return '<?php endfor; ?>';
            case 'while': return "<?php while ({$a}): ?>";
            case 'endwhile': return '<?php endwhile; ?>';
            case 'switch': return "<?php switch ({$a}): ?>";
            case 'case': return "<?php case {$a}: ?>";
            case 'default': return '<?php default: ?>';
            case 'endswitch': return '<?php endswitch; ?>';
            case 'break': return $a !== null ? "<?php if ({$a}) break; ?>" : '<?php break; ?>';
            case 'continue': return $a !== null ? "<?php if ({$a}) continue; ?>" : '<?php continue; ?>';
            case 'php': return $a !== null ? "<?php {$a}; ?>" : '<?php ';
            case 'json': return "<?php echo json_encode({$a}, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE); ?>";
            case 'class': return "<?php echo 'class=\"' . e(\\W5D\\View::classes({$a})) . '\"'; ?>";
            case 'props': return "<?php extract(\$__env->props({$a}, get_defined_vars())); ?>";
            case 'csrf': return '<?php echo csrf_field(); ?>';
            case 'widget': return "<?php echo \$__env->widget({$a}); ?>";
            case 'icon': return "<?php echo icon({$a}); ?>";
            case 'lang': return "<?php echo e(__({$a})); ?>";
        }
        return '';
    }

    private function inc(?string $a, string $mode): string
    {
        [$v, $data] = $this->split2((string)$a);
        $data = $data !== '' ? "array_merge(\$__env->scope(get_defined_vars()), {$data})" : '$__env->scope(get_defined_vars())';
        if ($mode === 'if') return "<?php if (\$__env->exists({$v})) echo \$__env->make({$v}, {$data})->render(); ?>";
        return "<?php echo \$__env->make({$v}, {$data})->render(); ?>";
    }

    /** split on first top-level comma */
    private function split2(string $s): array
    {
        $depth = 0; $q = null; $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            $c = $s[$i];
            if ($q !== null) { if ($c === '\\') { $i++; continue; } if ($c === $q) $q = null; continue; }
            if ($c === '"' || $c === "'") { $q = $c; continue; }
            if ($c === '(' || $c === '[' || $c === '{') $depth++;
            elseif ($c === ')' || $c === ']' || $c === '}') $depth--;
            elseif ($c === ',' && $depth === 0) return [trim(substr($s, 0, $i)), trim(substr($s, $i + 1))];
        }
        return [trim($s), ''];
    }

    private function splitAs(string $s): array
    {
        $depth = 0; $q = null; $n = strlen($s);
        for ($i = 0; $i < $n - 4; $i++) {
            $c = $s[$i];
            if ($q !== null) { if ($c === '\\') { $i++; continue; } if ($c === $q) $q = null; continue; }
            if ($c === '"' || $c === "'") { $q = $c; continue; }
            if ($c === '(' || $c === '[') $depth++;
            elseif ($c === ')' || $c === ']') $depth--;
            elseif ($depth === 0 && substr($s, $i, 4) === ' as ') return [trim(substr($s, 0, $i)), trim(substr($s, $i + 4))];
        }
        return [trim($s), '$__item'];
    }
}
