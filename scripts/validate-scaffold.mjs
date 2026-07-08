#!/usr/bin/env node
/**
 * Scaffold-freshness validator.
 *
 * Lints the `app` skeleton (or a freshly-generated app passed as argv[2]) for
 * the class of drift that bit paper-doll during bootstrap: stale framework
 * dependency pins, module references that don't resolve, migration files whose
 * class name the runner can't derive, leftover framework-only routes, and the
 * `username` column going missing from the auth trio.
 *
 * Usage:
 *   node scripts/validate-scaffold.mjs                 # validate packages/app
 *   node scripts/validate-scaffold.mjs /path/to/app    # validate a generated app
 *
 * Exit code 0 = clean, 1 = one or more freshness problems.
 *
 * No dependencies — Node built-ins only. Keep it that way so it can run inside
 * a scaffolded app's post-create hook without an install step.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const MONO = fileURLToPath(new URL('..', import.meta.url));
// With an argv path we're validating a real generated app; without one we're
// linting the skeleton *source* under packages/app.
const validatingGeneratedApp = Boolean(process.argv[2]);
const appDir = validatingGeneratedApp
    ? path.resolve(process.argv[2])
    : path.join(MONO, 'packages', 'app');

const errors = [];
const notes = [];
const fail = (msg) => errors.push(msg);
const note = (msg) => notes.push(msg);

const readJson = (p) => JSON.parse(fs.readFileSync(p, 'utf-8'));
const readText = (p) => fs.readFileSync(p, 'utf-8');
const exists = (p) => fs.existsSync(p);

/** Major.minor of a version or caret constraint ("^0.9", "0.9.0", "v0.9.0"). */
const minor = (v) => (v || '').replace(/^[\^~>=<v\s]+/, '').split('.').slice(0, 2).join('.');

// ── Lockstep version (canonical: the build package) ─────────────────────────
const lockstep = readJson(path.join(MONO, 'packages', 'build', 'package.json')).version;

// ── 1. Framework dependency pins are fresh ──────────────────────────────────
function checkDepPins() {
    const fePkgPath = path.join(appDir, 'frontend', 'package.json');
    if (exists(fePkgPath)) {
        const fe = readJson(fePkgPath);
        const wantNpm = `^${lockstep}`;
        const npmPins = {
            '@phillipsharring/handlr-frontend': fe.dependencies?.['@phillipsharring/handlr-frontend'],
            '@phillipsharring/handlr-build': fe.devDependencies?.['@phillipsharring/handlr-build'],
        };
        for (const [name, pin] of Object.entries(npmPins)) {
            if (!pin) fail(`frontend/package.json is missing a pin for ${name}`);
            else if (pin !== wantNpm) fail(`frontend/package.json pins ${name} at "${pin}", expected "${wantNpm}" (lockstep ${lockstep})`);
        }
    } else {
        note('no frontend/package.json — skipping npm pin checks');
    }

    const bePkgPath = path.join(appDir, 'backend', 'composer.json');
    if (exists(bePkgPath)) {
        const be = readJson(bePkgPath);
        const pin = be.require?.['phillipsharring/handlr-backend'];
        if (!pin) fail('backend/composer.json is missing a pin for phillipsharring/handlr-backend');
        else if (minor(pin) !== minor(lockstep)) fail(`backend/composer.json pins handlr-backend at "${pin}", expected the ${minor(lockstep)} line (lockstep ${lockstep})`);
    } else {
        note('no backend/composer.json — skipping composer pin check');
    }

    // composer.lock must not lag the manifest — a stale lock makes an app
    // uninstallable. Only meaningful for a *generated* app: the skeleton itself
    // ships no lock (a template locks on the consumer's `create-project`), and
    // at release time the new framework version isn't on Packagist yet, so a
    // skeleton lock couldn't reference it anyway.
    if (validatingGeneratedApp) {
        const lockPath = path.join(appDir, 'backend', 'composer.lock');
        if (exists(lockPath)) {
            const lock = readJson(lockPath);
            const locked = [...(lock.packages ?? []), ...(lock['packages-dev'] ?? [])]
                .find((p) => p.name === 'phillipsharring/handlr-backend');
            if (!locked) fail('backend/composer.lock does not contain phillipsharring/handlr-backend');
            else if (minor(locked.version) !== minor(lockstep)) fail(`backend/composer.lock has handlr-backend at "${locked.version}", stale vs lockstep ${lockstep} — run \`composer update phillipsharring/handlr-backend\``);
        } else {
            note('no backend/composer.lock — skipping lock freshness check');
        }
    }
}

// ── 2. Every local (string) module reference resolves ───────────────────────
function checkModuleRefs() {
    const cfgPath = path.join(appDir, 'frontend', 'site.config.js');
    if (!exists(cfgPath)) return note('no frontend/site.config.js — skipping module ref check');

    const src = readText(cfgPath);
    const block = src.match(/modules\s*:\s*\[([\s\S]*?)\]/);
    if (!block) return note('site.config.js has no modules array — skipping');

    const localNames = [...block[1].matchAll(/['"]([^'"]+)['"]/g)].map((m) => m[1]);
    for (const name of localNames) {
        const dir = path.join(appDir, 'frontend', 'modules', name);
        if (!exists(dir)) fail(`site.config.js references local module '${name}' but frontend/modules/${name}/ does not exist`);
    }
}

// ── 3. Migration class names are derivable by the runner ────────────────────
// Mirrors Handlr\Database\Migrations\MigrationRunner::classNameFromFile().
function migrationClassFromFile(file) {
    const base = path.basename(file, '.php');           // 20250826000500_create_users_table
    const stamp = base.slice(0, 14);
    const rest = base.slice(15);
    const studly = rest.split('_').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join('');
    return `Migration_${stamp}_${studly}`;
}

function checkMigrations() {
    const dir = path.join(appDir, 'backend', 'migrations');
    if (!exists(dir)) return note('no backend/migrations — skipping migration checks');

    for (const file of fs.readdirSync(dir).filter((f) => f.endsWith('.php'))) {
        const expected = migrationClassFromFile(file);
        const src = readText(path.join(dir, file));
        if (!new RegExp(`class\\s+${expected}\\b`).test(src)) {
            fail(`migration ${file}: runner will look for class ${expected}, which is not declared in the file`);
        }
    }
}

// ── 4. routes.php hygiene: no framework-only leftovers, no dup routes ────────
function checkRoutes() {
    const routesPath = path.join(appDir, 'backend', 'app', 'routes.php');
    if (!exists(routesPath)) return note('no backend/app/routes.php — skipping route checks');

    const src = readText(routesPath);
    if (/\bHandlr\\Ab\\/.test(src)) {
        fail('routes.php references Handlr\\Ab\\ — A/B is a self-registering module now; its routes should not be hardcoded here');
    }

    const seen = new Set();
    for (const m of src.matchAll(/->(get|post|patch|put|delete)\(\s*['"]([^'"]+)['"]/g)) {
        const key = `${m[1].toUpperCase()} ${m[2]}`;
        if (seen.has(key)) fail(`routes.php registers a duplicate route: ${key}`);
        seen.add(key);
    }
}

// ── 5. The auth trio all carry `username` ───────────────────────────────────
function checkUsername() {
    const targets = [
        fs.existsSync(path.join(appDir, 'backend', 'migrations'))
            && fs.readdirSync(path.join(appDir, 'backend', 'migrations')).find((f) => /create_users_table/.test(f))
            && path.join(appDir, 'backend', 'migrations', fs.readdirSync(path.join(appDir, 'backend', 'migrations')).find((f) => /create_users_table/.test(f))),
        path.join(appDir, 'backend', 'app', 'Users', 'Domain', 'UserRecord.php'),
    ].filter(Boolean);

    for (const t of targets) {
        if (!exists(t)) { fail(`expected ${path.relative(appDir, t)} to exist (username check)`); continue; }
        if (!/username/.test(readText(t))) fail(`${path.relative(appDir, t)} is missing the 'username' column/property`);
    }

    const seedsDir = path.join(appDir, 'backend', 'seeds');
    if (exists(seedsDir)) {
        const hasUsername = fs.readdirSync(seedsDir).some((f) => f.endsWith('.php') && /username/.test(readText(path.join(seedsDir, f))));
        if (!hasUsername) fail("no seed under backend/seeds/ sets a 'username' — seeded users will break auth");
    }
}

// ── Run ─────────────────────────────────────────────────────────────────────
console.log(`Validating scaffold: ${appDir}`);
console.log(`Lockstep version:    ${lockstep}\n`);

checkDepPins();
checkModuleRefs();
checkMigrations();
checkRoutes();
checkUsername();

for (const n of notes) console.log(`  · ${n}`);
if (errors.length === 0) {
    console.log('\n✅ scaffold is fresh — no freshness issues found');
    process.exit(0);
}
console.log(`\n❌ ${errors.length} freshness issue(s):`);
for (const e of errors) console.log(`  ✗ ${e}`);
process.exit(1);
