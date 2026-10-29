import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import { escapePdfText } from '../src/pdf-pagination.js';

const bundles = [
  { file: 'index-RRVGC1mK.js', start: 'f5={summary_inquiry:', end: ',R$=', hook: 'U$', moneyStart: 'Ui=e=>', moneyEnd: ',V0=', money: 'Wa', react: 'j', canvas: 'tF', pdf: 'Gt', languages: ['DE', 'EN', 'FR', 'NL', 'ES'], args: language => [language] },
  { file: 'positionhold.js', start: 'c$={summary_inquiry:', end: ',d$=', hook: 'u$', moneyStart: 'Ii=e=>', moneyEnd: ',Hx=', money: 'Ga', react: 'k', canvas: 'TF', pdf: 'Zt', languages: ['DE', 'EN', 'FR'], args: language => [language === 'EN', language === 'FR'] },
];
const subtotalLabels = { DE: 'Zwischensumme', EN: 'Subtotal', FR: 'Sous-total', NL: 'Subtotaal', ES: 'Subtotal' };
const totalLabels = { DE: 'Gesamtpreis', EN: 'Total Price', FR: 'Prix total', NL: 'Totaalprijs', ES: 'Precio total' };

// Beobachtete Beträge werden als Eingabe weitergereicht. Diese Tests prüfen die
// Darstellung und unveränderte Übergabe, nicht die fachliche Preis-/Steuerregel.
const cases = [
  { name: 'Deutschland / ACU', subtotal: 1450, bundleDiscount: 0, totalPrice: 1450, currency: 'EUR', vat: 19, country: 'Deutschland', installation: false },
  { name: 'Deutschland / ACU mit Einbau', subtotal: 3450, bundleDiscount: 0, totalPrice: 3450, currency: 'EUR', vat: 19, country: 'Deutschland', installation: true },
  { name: 'Deutschland / Einbau und Paketrabatt', subtotal: 7400, bundleDiscount: -500, totalPrice: 6900, currency: 'EUR', vat: 19, country: 'Deutschland', installation: true },
  { name: 'Schweiz / ACU', subtotal: 2437, bundleDiscount: 0, totalPrice: 2437, currency: 'CHF', vat: 8.1, country: 'Schweiz', installation: false },
];

function section(source, start, end) {
  const first = source.indexOf(start), last = source.indexOf(end, first);
  assert.ok(first >= 0 && last > first, 'PDF- oder Formatierbereich im Bündel nicht gefunden: ' + start);
  assert.equal(source.indexOf(start, first + 1), -1, 'Mehrdeutiger Bündeleinstieg: ' + start);
  return source.slice(first, last);
}

async function renderTemplate(bundle, source, language, fixture) {
  const input = {
    priceData: { ...fixture, configurationPrices: [{ name: 'ACU', price: fixture.subtotal }] },
    configData: {}, contactData: fixture.contactData || {}, selectedCountry: fixture.country,
    installationService: fixture.installation,
  };
  const original = structuredClone(input);
  let html = '', saved = '';
  const context = {
    Bs: () => input,
    sdEscapePdfText: escapePdfText,
    // Diese Tests prüfen die echte Vorlage. Seitenplanung/Rasterausschnitte
    // werden separat und zusätzlich mit echten Browser-Downloads geprüft.
    sdSavePaginatedPdf: async (element, render, pdf, filename) => { await render(element); pdf.save(filename); },
    [bundle.react]: { useState: () => [false, () => {}] },
    document: { createElement: () => ({ style: {}, innerHTML: '' }), body: { appendChild() {}, removeChild() {} } },
    [bundle.canvas]: async element => {
      html = element.innerHTML;
      return { width: 800, height: 1200, toDataURL: () => 'data:image/png;base64,TEST' };
    },
    [bundle.pdf]: class {
      internal = { pageSize: { getWidth: () => 210, getHeight: () => 297 } };
      addImage() {}
      addPage() {}
      save(name) { saved = name; }
    },
    // Die Quelltexte/Formatierung der PDF-Vorlage laufen echt. Nur Browser,
    // Rasterisierung, Download und hier nicht geprüfte Produktübersetzung sind ersetzt.
    vc: value => value, za: value => value,
    setTimeout: callback => callback(), console,
  };
  vm.createContext(context);
  const code = 'const ' + section(source, bundle.moneyStart, bundle.moneyEnd) + '; const ' + section(source, bundle.start, bundle.end) + '; globalThis.makePDF=' + bundle.hook + ';globalThis.money=' + bundle.money + ';';
  vm.runInContext(code, context, { timeout: 1000 });
  await context.makePDF(...bundle.args(language)).generatePDF();
  assert.match(saved, /^SMARTDOCK-Configuration-\d{4}-\d{2}-\d{2}\.pdf$/);
  assert.deepEqual(input, original, 'PDF-Erzeugung darf Eingabebeträge nicht verändern');
  return { html, format: value => context.money(value, fixture.currency) };
}

const escape = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

for (const bundle of bundles) {
  test('PDF-Vorlage ' + bundle.file + ': Kontaktangaben können kein HTML einschleusen', async () => {
    const source = await readFile(new URL('../vendor/website/assets/' + bundle.file, import.meta.url), 'utf8');
    const { html } = await renderTemplate(bundle, source, 'DE', { ...cases[0], contactData: {
      firstName: 'TESTDATEN <Nord>', lastName: 'Süd & West', company: '<script>test</script>',
      email: 'test@example.invalid', phone: '<Test>', street: '<Straße>', city: '<Stadt>', country: '<Land>',
    } });
    for (const literal of ['<Nord>', '<script>test</script>', '<Test>', '<Straße>', '<Stadt>', '<Land>']) {
      assert.ok(!html.includes(literal));
      assert.ok(html.includes(escapePdfText(literal)));
    }
  });
  test('PDF-Vorlage ' + bundle.file + ': Zwischensumme und Beträge', async t => {
    const source = await readFile(new URL('../vendor/website/assets/' + bundle.file, import.meta.url), 'utf8');
    for (const language of bundle.languages) for (const fixture of cases) {
      await t.test(language + ' / ' + fixture.name, async () => {
        const { html, format } = await renderTemplate(bundle, source, language, fixture);
        assert.ok(new RegExp('<span>' + escape(subtotalLabels[language]) + '</span>\\s*<span[^>]*>' + escape(format(fixture.subtotal)) + '</span>').test(html), 'Zwischensumme muss zum enthaltenen Betrag passen');
        assert.ok(!/<span>Netto<\/span>/.test(html), 'Brutto-Zwischensumme darf nicht Netto heißen');
        assert.ok(new RegExp('<span[^>]*>' + escape(totalLabels[language]) + '</span>\\s*<span[^>]*>' + escape(format(fixture.totalPrice)) + '</span>').test(html), 'Gesamtbetrag muss unverändert in der Gesamtzeile ausgegeben werden');
        assert.ok(html.includes('(' + fixture.vat + '%)'), 'Steuersatz aus der Eingabe muss erhalten bleiben');
        assert.equal((html.match(/data-pdf-totals/g) || []).length, 1, 'Summenblock braucht eine Umbruchgrenze');
        assert.match(html, /<img data-pdf-logo src="data:image\/png;base64,/);
        assert.ok(!html.includes('#0284c7'));
        assert.ok(html.includes('#bceb02'));
        assert.ok(!html.includes('#191c20'));
        assert.match(html,/data-pdf-totals style="background: #d6d6d6;/);
        assert.equal((html.slice(html.indexOf('data-pdf-footer')).match(/max-width:none/g)||[]).length,2);
        assert.equal((html.match(/data-pdf-footer/g) || []).length, 1, 'Footer gehört zur letzten Summenseite');
        if (fixture.bundleDiscount < 0) assert.ok(html.includes(format(fixture.bundleDiscount)), 'Rabatt muss erhalten bleiben');
      });
    }
  });
}
