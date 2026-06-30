/**
 * Заменяет плейсхолдер <BASE_URL> в сгенерированном k6-скрипте на чтение из K6_BASE_URL с fallback.
 * Запускается после openapi-to-k6, т.к. генератор всегда подставляет "<BASE_URL>".
 */
const fs = require('fs');
const path = require('path');

const scriptPath = path.join(__dirname, 'generated', 'k6-script.sample.ts');
if (!fs.existsSync(scriptPath)) {
  console.warn('tools/k6: k6-script.sample.ts не найден, пропуск patch.');
  process.exit(0);
}

let content = fs.readFileSync(scriptPath, 'utf8');
const placeholder = 'const baseUrl = "<BASE_URL>";';
const replacement = 'const baseUrl = __ENV.K6_BASE_URL || "http://localhost:8000";';

if (content.includes(placeholder)) {
  content = content.replace(placeholder, replacement);
  fs.writeFileSync(scriptPath, content);
  console.log('tools/k6: baseUrl в k6-script.sample.ts заменён на __ENV.K6_BASE_URL || "http://localhost:8000"');
}
