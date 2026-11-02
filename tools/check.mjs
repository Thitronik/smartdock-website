import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { build, ROOT } from './build.mjs';

execFileSync(process.execPath, ['tools/check-repository.mjs'], { cwd: ROOT, stdio: 'inherit' });
execFileSync(process.execPath, ['--test', 'tools/admin-model.test.mjs', 'tools/build.test.mjs', 'tools/repository.test.mjs', 'tools/pdf.test.mjs', 'tools/configurator.test.mjs', 'tools/pdf-pagination.test.mjs'], { cwd: ROOT, stdio: 'inherit' });
for (const pages of [false, true]) {
  const files = await build({ pages });
  let scripts = 0;
  for (const [name, bytes] of files) {
    if (name.endsWith('.js')) {
      execFileSync(process.execPath, ['--check', '--input-type=module'], { input: bytes, stdio: ['pipe', 'inherit', 'inherit'] });
      scripts++;
    }
    if (name.endsWith('.json')) JSON.parse(bytes.toString('utf8'));
    if (name.endsWith('.pdf')) assert.equal(bytes.subarray(0, 5).toString(), '%PDF-', name);
    if (name.endsWith('.html')) {
      const html = bytes.toString('utf8');
      assert.ok(html.includes('noindex,nofollow') && html.includes('TESTVORSCHAU'), 'Vorschau-Schutz fehlt: ' + name);
      assert.ok(!html.includes('{{'), 'Unersetzter Platzhalter: ' + name);
      for (const [, url] of html.matchAll(/(?:src|href)="(\/[^"#?]+)(?:\?[^"#]*)?"/g)) {
        const target = url.replace(pages ? /^\/smartdock-website\// : /^\//, '');
        assert.ok(files.has(target) || ((target === '' || target.endsWith('/')) && files.has(target + 'index.html')), name + ' verweist auf fehlende Datei ' + url);
      }
    }
  }
  const faqs = JSON.parse(files.get('data/faqs.json')).items;
  for (const faq of faqs) for (const language of ['de', 'en', 'fr', 'nl', 'es']) {
    for (const field of ['question_', 'answer_']) assert.ok(faq[field + language]?.trim(), 'Leeres FAQ-Feld: ' + field + language);
  }
  console.log(`PASS: ${pages ? 'Pages' : 'Root'} – Ressourcen, JSON, PDFs, FAQ-Sprachen und ${scripts} JavaScript-Dateien.`);
}
execFileSync(process.execPath, ['tools/check-legacy-routes.mjs'], { cwd: ROOT, stdio: 'inherit' });
