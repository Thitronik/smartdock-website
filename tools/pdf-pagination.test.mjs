import test from 'node:test';
import assert from 'node:assert/strict';
import { planPdfPages, savePaginatedPdf, escapePdfText, pdfInkLines } from '../src/pdf-pagination.js';

test('Rasterzeilen schützen auch versetzte Schriftpixel und ignorieren helle Kartenflächen', () => {
  const width = 8, height = 100, data = new Uint8ClampedArray(width * height * 4).fill(245);
  for (const [start, end] of [[10, 20], [38, 48], [66, 76], [94, 100]]) {
    for (let y = start; y < end; y++) { const p = (y * width + 3) * 4; data[p] = 23; data[p + 3] = 255; }
  }
  const lines = pdfInkLines({ data, width, height }, 2);
  assert.deepEqual(lines, [{ start: 4.5, end: 10.5 }, { start: 18.5, end: 24.5 }, { start: 32.5, end: 38.5 }, { start: 46.5, end: 50 }]);
  const pages = planPdfPages({ top: 0, bottom: 50, pageHeight: 24, lines });
  complete(pages, 0, 50, 24);
  for (const p of pages) assert.ok(!lines.some(line => line.start < p.end && line.end > p.end));
});

function complete(pages, top, bottom, capacity) {
  assert.equal(pages[0].start, top);
  assert.equal(pages.at(-1).end, bottom);
  for (const [i, page] of pages.entries()) {
    assert.ok(page.end > page.start);
    assert.ok(page.end - page.start <= capacity);
    if (i) assert.equal(page.start, pages[i - 1].end, 'Kein Pixel darf fehlen oder doppelt vorkommen');
  }
}

test('Summenblock mit Rabatt und Footer bleibt bei jeder Randposition zusammen', () => {
  for (let offset = 600; offset < 1200; offset++) {
    const totals = { start: offset, end: offset + 250 };
    const footer = { start: offset + 290, end: offset + 350 };
    const end = footer.end;
    const pages = planPdfPages({ top: 60, bottom: end, pageHeight: 1000, blocks: [totals, footer, { start: totals.start, end }] });
    complete(pages, 60, end, 1000);
    assert.ok(pages.some(p => p.start <= totals.start && p.end >= footer.end));
    assert.ok(pages.length <= 2, 'Keine zusätzliche Restseite');
  }
});

test('Exakt passende ein- und mehrseitige Inhalte erzeugen keine leere Seite', () => {
  for (const height of [900, 1000, 2000, 3000]) {
    const pages = planPdfPages({ top: 60, bottom: 60 + height, pageHeight: 1000 });
    complete(pages, 60, 60 + height, 1000);
    assert.equal(pages.length, Math.ceil(height / 1000));
  }
});

test('Sehr lange Kontaktkarte wird nur zwischen vollständigen Textzeilen geteilt', () => {
  const lines = Array.from({ length: 150 }, (_, i) => ({ start: 80 + i * 24, end: 99 + i * 24 }));
  const pages = planPdfPages({ top: 60, bottom: 3700, pageHeight: 1000, blocks: [{ start: 60, end: 3700 }], lines });
  complete(pages, 60, 3700, 1000);
  for (const page of pages) assert.ok(!lines.some(line => line.start < page.end && line.end > page.end));
});

test('Überlappende Karten in zwei Spalten führen nicht zum Abschneiden einer Textzeile', () => {
  const pages = planPdfPages({ top: 0, bottom: 1800, pageHeight: 1000,
    blocks: [{ start: 0, end: 700 }, { start: 650, end: 1100 }, { start: 1050, end: 1750 }],
    lines: [{ start: 990, end: 1010 }, { start: 1680, end: 1700 }] });
  complete(pages, 0, 1800, 1000);
  assert.equal(pages[0].end, 990);
});

test('Freitext bleibt Text und kann das PDF-Layout nicht durch HTML verändern', () => {
  assert.equal(escapePdfText('Werft <Nord> & "Süd"'), 'Werft &lt;Nord&gt; &amp; &quot;Süd&quot;');
});

test('Rasterausschnitte sind fortlaufend, mit Seitenrändern und ohne Negativpositionen', async () => {
  const original = { document: globalThis.document, NodeFilter: globalThis.NodeFilter, getComputedStyle: globalThis.getComputedStyle };
  const images = [], crops = [], logoDraws = []; let logoDecoded=false; let removed = false, saved = '', pageCount = 1;
  const rect = { left:0, width: 800, height: 2200, top: 0, bottom: 2200 };
  const totals = { getBoundingClientRect: () => ({ top: 1900, bottom: 2100 }) };
  const logo = { decode: async()=>{logoDecoded=true;},getBoundingClientRect:()=>({left:76,top:72,width:260,height:27}) };
  const footer = { getBoundingClientRect: () => ({ top: 2120, bottom: 2140 }) };
  const element = { getBoundingClientRect: () => rect, remove: () => { removed = true; },
    querySelectorAll: selector => selector === '[data-pdf-keep]' ? [totals, footer] : ['img','img[data-pdf-logo]'].includes(selector)?[logo]:[],
    querySelector: selector => selector === '[data-pdf-totals]' ? totals : footer };
  const pdf = { internal: { pageSize: { getWidth: () => 210, getHeight: () => 297 } },
    addImage: (...args) => images.push(args), addPage: () => pageCount++, save: name => { saved = name; } };
  globalThis.document = { body: { appendChild() {} }, fonts: { ready: Promise.resolve() },
    createTreeWalker: () => ({ nextNode: () => null }),
    createElement: () => ({ getContext: () => ({ drawImage: (...args) => crops.push(args) }), toDataURL: () => 'image' }) };
  globalThis.NodeFilter = { SHOW_TEXT: 4 };
  globalThis.getComputedStyle = () => ({ paddingTop: '60px', paddingBottom: '60px' });
  try {
    await savePaginatedPdf(element, async () => ({ width: 1600, height: 4400, getContext: () => ({ save(){},restore(){},setTransform(...args){assert.deepEqual(args,[1,0,0,1,0,0]);},drawImage(...args){logoDraws.push(args);}, getImageData: () => ({ data: new Uint8ClampedArray(0), width: 0, height: 0 }) }) }), pdf, 'test.pdf');
    assert.equal(logoDecoded,true);assert.deepEqual(logoDraws[0],[logo,152,144,520,54]);
    assert.equal(saved, 'test.pdf'); assert.equal(removed, true);
    assert.equal(images.length, pageCount);
    assert.equal(crops[0][2], 120);
    assert.equal(crops.at(-1)[2] + crops.at(-1)[4], 4312, 'Letzte Textzeile behält einen Raster-Sicherheitsrand');
    crops.forEach((c, i) => { if(i) assert.equal(c[2], crops[i-1][2] + crops[i-1][4]); });
    images.forEach(args => { assert.equal(args[2], 0); assert.equal(args[3], 15.75); assert.ok(args[3] + args[5] <= 281.25); assert.equal(args[7], 'FAST'); });
    removed = false;
    await assert.rejects(savePaginatedPdf(element, async () => { throw Error('Rasterfehler'); }, pdf, 'test.pdf'), /Rasterfehler/);
    assert.equal(removed, true, 'Unsichtbare Vorlage muss auch bei Fehlern entfernt werden');
  } finally {
    for (const [key, value] of Object.entries(original)) {
      if (value === undefined) delete globalThis[key]; else globalThis[key] = value;
    }
  }
});
