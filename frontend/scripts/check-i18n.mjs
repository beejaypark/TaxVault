import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../src/assets/i18n');

function flatten(value, prefix = '', output = {}) {
  for (const [key, child] of Object.entries(value)) {
    const fullKey = prefix ? `${prefix}.${key}` : key;
    if (child && typeof child === 'object' && !Array.isArray(child)) {
      flatten(child, fullKey, output);
    } else {
      output[fullKey] = child;
    }
  }
  return output;
}

const english = flatten(JSON.parse(fs.readFileSync(path.join(root, 'en.json'), 'utf8')));
let failed = false;

for (const locale of ['kr', 'cn', 'es']) {
  const messages = flatten(JSON.parse(fs.readFileSync(path.join(root, `${locale}.json`), 'utf8')));
  const missing = Object.keys(english).filter((key) => !(key in messages));
  if (missing.length > 0) {
    failed = true;
    console.error(`${locale}: ${missing.length} missing translation key(s)`);
    for (const key of missing) console.error(`  ${key}`);
  } else {
    console.log(`${locale}: all translation keys present`);
  }
}

process.exitCode = failed ? 1 : 0;
