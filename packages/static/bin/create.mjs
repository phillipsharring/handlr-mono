#!/usr/bin/env node

import { execSync } from 'node:child_process';
import { existsSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';

const REPO = 'https://github.com/phillipsharring/graspr-static-skeleton.git';

const name = process.argv[2];

if (!name) {
    console.error('\n  Usage: npm create graspr-static <project-name>\n');
    process.exit(1);
}

const dir = resolve(process.cwd(), name);

if (existsSync(dir)) {
    console.error(`\n  Error: "${name}" already exists.\n`);
    process.exit(1);
}

console.log(`\n  Creating a new Graspr static site in ${dir}\n`);

// Clone the static skeleton
console.log('  Cloning static skeleton...');
try {
    execSync(`git clone --depth 1 ${REPO} "${dir}"`, { stdio: 'pipe' });
} catch (err) {
    console.error('  Failed to clone the static skeleton. Check your network connection.');
    process.exit(1);
}

// Remove .git so it's a fresh project
console.log('  Removing VCS history...');
rmSync(resolve(dir, '.git'), { recursive: true, force: true });

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
  Done! Your new Graspr static site is ready.

  Next steps:

    cd ${name}
    npm run dev

  Documentation: https://github.com/phillipsharring/graspr-static-skeleton
`);
