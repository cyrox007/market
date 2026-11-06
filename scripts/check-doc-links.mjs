#!/usr/bin/env node
/**
 * Validate repo-relative links in canonical, actively maintained documentation.
 * Historical documents are catalogued but deliberately not treated as current.
 * No external network, dependencies or production access needed.
 */
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const activeDocs = [
  'README.md',
  'services/backend/README.md',
  'market-docs/README.md',
  'market-docs/system-overview.md',
  'market-docs/disaster-recovery.md',
  'market-docs/DOCS_POLICY.md',
  'market-docs/documentation-inventory.md',
  'market-docs/documentation-audit-20261103.md',
];
const problems = [];
let checked = 0;

for (const file of activeDocs) {
  if (!existsSync(file)) {
    problems.push(file + ': missing canonical document');
    continue;
  }
  const text = readFileSync(file, 'utf8');
  // Conventional Markdown links (not reference-style, external URLs or anchors).
  const links = [...text.matchAll(/\]\(([^)]+)\)/g)];
  for (const [, raw] of links) {
    const href = raw.trim().split(' ')[0];
    if (/^(?:[a-z][a-z\d+.-]*:|#|\/\/)/i.test(href)) continue;
    const target = href.split('#', 1)[0];
    if (!target) continue;
    let decoded = target;
    try { decoded = decodeURIComponent(target); }
    catch { problems.push(file + ': invalid URI encoding: ' + target); continue; }
    const absolute = resolve(dirname(file), decoded);
    checked++;
    if (!existsSync(absolute)) problems.push(file + ': missing ' + target);
  }
}

if (problems.length) {
  console.error('Documentation link errors:\n' + problems.map(p => ' - ' + p).join('\n'));
  process.exitCode = 1;
} else {
  console.log('OK: ' + checked + ' local links in ' + activeDocs.length + ' canonical documents');
}
