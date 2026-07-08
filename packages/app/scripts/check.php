<?php

declare(strict_types=1);

/**
 * Consumer-side scaffold check — runs in a generated app via `composer check`
 * (and once automatically after `composer create-project`).
 *
 * Pure file parsing: no framework, no vendor, no database. It therefore runs
 * before anything is installed and stays valid as your app diverges from the
 * pristine scaffold — it asserts *structural* invariants, not "matches the
 * template". Distinct from the monorepo's `validate-scaffold` (which lints the
 * skeleton source before release).
 *
 * Exit code 0 = clean, 1 = one or more structural problems.
 */

$root = getcwd();
$errors = [];
$fail = static function (string $m) use (&$errors): void { $errors[] = $m; };

// ── 1. Migration class names must be derivable by MigrationRunner ───────────
// Mirrors Handlr\Database\Migrations\MigrationRunner::classNameFromFile():
// "20250826000500_create_users_table" -> "Migration_20250826000500_CreateUsersTable".
// A mismatch means the runner throws "Migration class … not found" at migrate time.
$migDir = "$root/backend/migrations";
if (is_dir($migDir)) {
    foreach (glob("$migDir/*.php") as $file) {
        $base = basename($file, '.php');
        $stamp = substr($base, 0, 14);
        $rest = substr($base, 15);
        $studly = str_replace(' ', '', ucwords(str_replace('_', ' ', $rest)));
        $class = "Migration_{$stamp}_{$studly}";
        if (!preg_match('/class\s+' . preg_quote($class, '/') . '\b/', (string) file_get_contents($file))) {
            $fail('migration ' . basename($file) . ": runner will look for class $class, which is not declared in the file");
        }
    }
}

// ── 2. routes.php registers no duplicate method+path ────────────────────────
// The 0.5+ router rejects duplicate registrations at boot; catch it here first.
$routes = "$root/backend/app/routes.php";
if (is_file($routes)) {
    $src = (string) file_get_contents($routes);
    if (preg_match_all('/->(get|post|patch|put|delete)\(\s*[\'"]([^\'"]+)[\'"]/i', $src, $m, PREG_SET_ORDER)) {
        $seen = [];
        foreach ($m as $r) {
            $key = strtoupper($r[1]) . ' ' . $r[2];
            if (isset($seen[$key])) {
                $fail("routes.php registers a duplicate route: $key");
            }
            $seen[$key] = true;
        }
    }
}

// ── 3. Every local (string) module reference in site.config.js resolves ─────
$cfg = "$root/frontend/site.config.js";
if (is_file($cfg)) {
    $src = (string) file_get_contents($cfg);
    if (preg_match('/modules\s*:\s*\[([\s\S]*?)\]/', $src, $b)
        && preg_match_all('/[\'"]([^\'"]+)[\'"]/', $b[1], $names)) {
        foreach ($names[1] as $name) {
            if (!is_dir("$root/frontend/modules/$name")) {
                $fail("site.config.js references local module '$name' but frontend/modules/$name/ does not exist");
            }
        }
    }
}

// ── Report ──────────────────────────────────────────────────────────────────
if ($errors === []) {
    fwrite(STDOUT, "\xE2\x9C\x85 handlr app check passed — no structural issues\n");
    exit(0);
}

fwrite(STDERR, "\xE2\x9D\x8C handlr app check found " . count($errors) . " issue(s):\n");
foreach ($errors as $e) {
    fwrite(STDERR, "  \xE2\x9C\x97 $e\n");
}
exit(1);
