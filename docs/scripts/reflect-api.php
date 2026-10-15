<?php

declare(strict_types=1);

// Reflects the public API of the whole A/B testing family into one snapshot
// (docs/scripts/api-snapshot.json), which generate-api.mjs turns into pages.
//
// The package list is docs/scripts/packages.json — the same manifest the
// aggregator, the integrity check and the sidebar read. Every sibling must be
// found: a missing one fails the run instead of quietly shrinking the
// reference, because a site that documents four packages out of five looks
// exactly like a site that documents five.
//
// Each package's OWN vendor/autoload.php is required for its third-party
// dependencies (yiisoft/db, yiisoft/cookies, ...). Composer prepends its
// loader, so a sibling's vendor copy of the core would otherwise shadow this
// checkout's src/ — the prepended PSR-4 loader below maps every family
// namespace to the src/ directory being documented, and it is registered last.

$coreDir = realpath(__DIR__ . '/../..');
$manifest = json_decode(
    (string) file_get_contents(__DIR__ . '/packages.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);

function resolvePackageDir(string $coreDir, array $package): ?string
{
    if ($package['slug'] === 'core') {
        return $coreDir;
    }

    $candidates = [
        $coreDir . '/docs/siblings/' . $package['dir'], // CI layout
        dirname($coreDir) . '/' . $package['dir'],       // monorepo layout
    ];
    foreach ($candidates as $candidate) {
        if (is_dir($candidate . '/src')) {
            return $candidate;
        }
    }

    return null;
}

$packages = [];
$missing = [];
foreach ($manifest as $package) {
    $dir = resolvePackageDir($coreDir, $package);
    if ($dir === null) {
        $missing[] = "{$package['dir']}: not found under docs/siblings/ or as a monorepo sibling";

        continue;
    }
    if (!is_file($dir . '/vendor/autoload.php')) {
        $missing[] = "{$package['dir']}: vendor/autoload.php missing — run composer install in {$dir}";

        continue;
    }
    $packages[] = $package + ['path' => $dir];
}

if ($missing !== []) {
    fwrite(STDERR, "reflect-api: cannot document the whole family:\n  - " . implode("\n  - ", $missing) . "\n");

    exit(1);
}

foreach ($packages as $package) {
    require $package['path'] . '/vendor/autoload.php';
}

spl_autoload_register(static function (string $class) use ($packages): void {
    foreach ($packages as $package) {
        $prefix = $package['namespace'] . '\\';
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $file = $package['path'] . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }

        return;
    }
}, prepend: true);

/**
 * Splits a docblock into its free-text summary (everything before the first
 * tag) and the @param descriptions keyed by parameter name.
 *
 * @return array{summary: string, params: array<string, string>}
 */
function parseDocComment(string|false $docComment): array
{
    if ($docComment === false) {
        return ['summary' => '', 'params' => []];
    }

    $clean = [];
    foreach (explode("\n", $docComment) as $line) {
        $line = trim($line);
        $line = preg_replace('#^/\*\*#', '', $line);
        $line = preg_replace('#\*/$#', '', $line);
        $line = preg_replace('#^\*\s?#', '', $line) ?? '';
        // A one-line docblock (`/** @return list<T> */`) leaves the tag behind
        // a space once the delimiters are gone.
        $clean[] = $clean === [] ? trim($line) : rtrim($line);
    }

    $summaryLines = [];
    $params = [];
    $currentParam = null;
    $buffer = [];

    $flush = static function () use (&$currentParam, &$buffer, &$params): void {
        if ($currentParam === null || $currentParam === 'done') {
            return;
        }
        $text = trim(implode(' ', $buffer));
        if ($text !== '') {
            $params[$currentParam] = $text;
        }
        $buffer = [];
    };

    foreach ($clean as $line) {
        if (preg_match('/^@param\s+.+?\s+\$(\w+)\s*(.*)$/', $line, $m) === 1) {
            $flush();
            $currentParam = $m[1];
            $buffer = $m[2] !== '' ? [$m[2]] : [];

            continue;
        }
        if (preg_match('/^@\w+/', $line) === 1) {
            $flush();
            $currentParam = 'done';
            $buffer = [];

            continue;
        }
        if ($currentParam === null) {
            $summaryLines[] = $line;
        } elseif ($currentParam !== 'done') {
            $buffer[] = $line;
        }
    }
    $flush();

    $summary = trim(implode("\n", $summaryLines));
    $summary = preg_replace('/\n{3,}/', "\n\n", $summary) ?? $summary;
    $summary = preg_replace('/\{@(?:see|link)\s+([^}]+)\}/', '`$1`', $summary) ?? $summary;

    return ['summary' => $summary, 'params' => $params];
}

/** @return list<string> */
function findPhpFiles(string $dir): array
{
    $files = [];
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        if (is_dir($path)) {
            $files = [...$files, ...findPhpFiles($path)];
        } elseif (str_ends_with($entry, '.php')) {
            $files[] = $path;
        }
    }

    return $files;
}

// PHP source spelling for a default or constant value: `null`, `[]`,
// `['a', 'b']`, `new Foo()` — not var_export's `NULL` and `array (\n)`.
function exportValue(mixed $value): string
{
    if (is_object($value)) {
        return $value instanceof UnitEnum ? $value::class . '::' . $value->name : 'new ' . $value::class . '()';
    }
    if (is_array($value)) {
        $list = array_is_list($value);
        $items = [];
        foreach ($value as $key => $item) {
            $items[] = ($list ? '' : exportValue($key) . ' => ') . exportValue($item);
        }

        return '[' . implode(', ', $items) . ']';
    }

    return $value === null ? 'null' : var_export($value, true);
}

function describeParameters(ReflectionFunctionAbstract $function, array $descriptions): array
{
    $params = [];
    foreach ($function->getParameters() as $param) {
        $default = null;
        if ($param->isDefaultValueAvailable()) {
            $default = $param->isDefaultValueConstant()
                ? (string) $param->getDefaultValueConstantName()
                : exportValue($param->getDefaultValue());
        }
        $params[] = [
            'name' => $param->getName(),
            'type' => $param->getType()?->__toString(),
            'variadic' => $param->isVariadic(),
            'default' => $default,
            'description' => $descriptions[$param->getName()] ?? '',
        ];
    }

    return $params;
}

$report = [];

foreach ($packages as $package) {
    $src = $package['path'] . '/src';
    foreach (findPhpFiles($src) as $path) {
        $className = $package['namespace'] . '\\' . str_replace('/', '\\', substr($path, strlen($src) + 1, -4));
        if (!class_exists($className) && !interface_exists($className) && !enum_exists($className)) {
            continue;
        }

        $reflection = new ReflectionClass($className);
        $docComment = $reflection->getDocComment();
        $classDoc = parseDocComment($docComment);

        $constructor = null;
        $ctor = $reflection->getConstructor();
        if ($ctor !== null && $ctor->isPublic() && $ctor->getDeclaringClass()->getName() === $className) {
            $constructor = describeParameters($ctor, parseDocComment($ctor->getDocComment())['params']);
        }

        $methods = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $className || $method->isConstructor()) {
                continue;
            }
            if ($reflection->isEnum() && in_array($method->getName(), ['cases', 'from', 'tryFrom'], true)) {
                continue;
            }
            $methodDoc = parseDocComment($method->getDocComment());
            $methods[] = [
                'name' => $method->getName(),
                'static' => $method->isStatic(),
                'params' => describeParameters($method, $methodDoc['params']),
                'returnType' => $method->getReturnType()?->__toString(),
                'summary' => $methodDoc['summary'],
            ];
        }

        $properties = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $className) {
                continue;
            }
            if ($reflection->isEnum() && in_array($property->getName(), ['name', 'value'], true)) {
                continue;
            }
            $properties[] = [
                'name' => $property->getName(),
                'type' => $property->getType()?->__toString(),
                'readonly' => $property->isReadOnly(),
                'summary' => parseDocComment($property->getDocComment())['summary'],
            ];
        }

        $constants = [];
        $cases = [];
        foreach ($reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
            if ($constant->getDeclaringClass()->getName() !== $className) {
                continue;
            }
            if ($constant->isEnumCase()) {
                $case = $constant->getValue();
                $cases[] = [
                    'name' => $constant->getName(),
                    'value' => $case instanceof BackedEnum ? $case->value : null,
                    'summary' => parseDocComment($constant->getDocComment())['summary'],
                ];

                continue;
            }
            $constants[] = [
                'name' => $constant->getName(),
                'value' => exportValue($constant->getValue()),
                'summary' => parseDocComment($constant->getDocComment())['summary'],
            ];
        }

        $report[] = [
            'class' => $className,
            'package' => $package['slug'],
            'kind' => $reflection->isInterface() ? 'interface' : ($reflection->isEnum() ? 'enum' : 'class'),
            'isApi' => $docComment !== false && preg_match('/@api\b/', $docComment) === 1,
            'summary' => $classDoc['summary'],
            'extends' => ($reflection->getParentClass() ?: null)?->getName(),
            'implements' => array_values($reflection->getInterfaceNames()),
            'constructor' => $constructor,
            'cases' => $cases,
            'constants' => $constants,
            'publicProperties' => $properties,
            'publicMethods' => $methods,
        ];
    }
}

usort($report, static fn(array $a, array $b): int => $a['class'] <=> $b['class']);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
