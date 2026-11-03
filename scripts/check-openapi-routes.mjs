#!/usr/bin/env node
/**
 * Fail when the checked-in OpenAPI spec drifts from Laravel routes/api.php.
 * Reads source statically; Laravel runtime/database are not needed.
 */
import { readFileSync } from 'node:fs';

const source = readFileSync('services/backend/routes/api.php', 'utf8').split('\n');
const spec = JSON.parse(readFileSync('services/backend/public/api-docs/openapi.json', 'utf8'));
let depth = 0;
let scopes = [];
const current = new Set();

for (const raw of source) {
  const line = raw.trim();
  if (line.startsWith('//') || line.startsWith('*')) continue;

  const group = line.match(/Route::.*?prefix\(['"]([^'"]+)['"]\).*?->group\(function/);
  if (group) scopes.push({ prefix: group[1], atDepth: depth + 1 });

  const match = line.match(/Route::(get|post|put|patch|delete)\(\s*['"]([^'"]+)['"]/);
  if (match) {
    const pathname = ('/api/' + scopes.map(s => s.prefix).join('/') + '/' + match[2])
      .replace(/\/{2,}/g, '/').replace(/\/$/, '');
    current.add(match[1].toUpperCase() + ' ' + pathname);
  }

  // Strip quoted PHP strings so path params {id} and messages do not count as scopes.
  const safe = line.replace(/'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"/g, '""').replace(/\/\/.*$/, '');
  depth += (safe.match(/{/g) || []).length - (safe.match(/}/g) || []).length;
  scopes = scopes.filter(s => s.atDepth <= depth);
}

const documented = new Set();
for (const [pathname, operations] of Object.entries(spec.paths)) {
  for (const verb of ['get', 'post', 'put', 'patch', 'delete']) {
    if (operations[verb]) documented.add(verb.toUpperCase() + ' ' + pathname.replace(/\/$/, ''));
  }
}
const missing = [...current].filter(k => !documented.has(k));
const obsolete = [...documented].filter(k => !current.has(k));
const bad = [];
if (missing.length) bad.push('Missing OpenAPI operations:\n' + missing.join('\n'));
if (obsolete.length) bad.push('Unregistered API operations documented:\n' + obsolete.join('\n'));
const schemas = spec.components?.schemas || {};
const serialized = JSON.stringify(spec);
for (const [, name] of serialized.matchAll(/#\/components\/schemas\/([A-Za-z\d_]+)/g)) {
  if (!Object.hasOwn(schemas, name)) bad.push('Undefined schema ref: ' + name);
}
for (const [pathname, operations] of Object.entries(spec.paths)) {
  for (const [verb, op] of Object.entries(operations)) {
    if (!op || !op.responses) continue;
    for (const security of op.security || []) {
      for (const scheme of Object.keys(security)) {
        if (!spec.components?.securitySchemes?.[scheme]) bad.push('Undefined security scheme: ' + scheme + ' ' + verb + ' ' + pathname);
      }
    }
  }
}
const checkout = spec.paths['/api/v1/orders']?.post?.requestBody?.content?.['application/json']?.schema?.properties || {};
for (const field of ['delivery_date', 'delivery_time', 'delivery_cost', 'assembly_cost', 'shipping_method_id', 'shipping_location_id', 'address']) {
  if (Object.hasOwn(checkout, field)) bad.push('Checkout falsely advertises prohibited field: ' + field);
}
if (bad.length) {
  console.error(bad.join('\n\n'));
  process.exitCode = 1;
} else {
  console.log('OK: OpenAPI agrees with ' + current.size + ' routed operations; all referenced schemas/security schemes exist');
}
