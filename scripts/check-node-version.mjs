#!/usr/bin/env node
/**
 * Vite 8 + Rolldown require Node >=20.19 or >=22.12 (see package.json engines).
 * Older 20.x fails with util.styleText / ERR_INVALID_ARG_VALUE.
 */
const match = process.version.match(/^v(\d+)\.(\d+)\.(\d+)/);
if (!match) {
    console.error(`check-node-version: could not parse ${process.version}`);
    process.exit(1);
}
const major = Number(match[1]);
const minor = Number(match[2]);

const ok =
    (major === 20 && minor >= 19) ||
    (major === 22 && minor >= 12) ||
    major >= 23;

if (ok) {
    process.exit(0);
}

console.error(`
Node.js ${process.version} is too old for this project (Vite 8 / Rolldown).

Required (from package.json "engines"):
  >= 20.19.0 < 21, or >= 22.12.0

Fix:
  - If you use nvm:  nvm install && nvm use   (see .nvmrc)
  - Or install Node 22.12+ / 24 LTS from https://nodejs.org
`);
process.exit(1);
