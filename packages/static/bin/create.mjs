#!/usr/bin/env node

import { execSync } from 'node:child_process';
import { cpSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

// The static skeleton ships inside this package, one level up from bin/.
const SKELETON = fileURLToPath(new URL('../skeleton', import.meta.url));

const name = process.argv[2];

if (!name) {
    console.error('\n  Usage: npm create @phillipsharring/handlr-static <project-name>\n');
    process.exit(1);
}

const dir = resolve(process.cwd(), name);

if (existsSync(dir)) {
    console.error(`\n  Error: "${name}" already exists.\n`);
    process.exit(1);
}

console.log(`\n  Creating a new Handlr static site in ${dir}\n`);

// Copy the bundled skeleton into the target directory
console.log('  Copying static skeleton...');
try {
    cpSync(SKELETON, dir, { recursive: true });
} catch (err) {
    console.error('  Failed to copy the static skeleton.');
    console.error(`  ${err.message}`);
    process.exit(1);
}

// Install dependencies
console.log('  Installing dependencies...\n');
try {
    execSync('npm install', { cwd: dir, stdio: 'inherit' });
} catch (err) {
    console.error('\n  npm install failed. You can retry manually:\n');
    console.error(`    cd ${name} && npm install\n`);
    process.exit(1);
}

console.log(`
  Done! Your new Handlr static site is ready.

  Next steps:

    cd ${name}
    npm run dev

  Documentation: https://github.com/phillipsharring/handlr-mono/tree/main/packages/static
`);
