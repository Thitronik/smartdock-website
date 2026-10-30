// Die bestehende PDF-Gestaltung bleibt ein Rasterbild. Umbrüche müssen deshalb
// vor dem Ausschneiden des Bildes anhand des echten Browserlayouts geplant werden.
export function planPdfPages({ top, bottom, pageHeight, blocks = [], lines = [] }) {
  if (![top, bottom, pageHeight].every(Number.isFinite) || bottom <= top || pageHeight <= 0) {
    throw new Error('Ungültige PDF-Seitenmaße');
  }
  // Eine sehr hohe Kontaktkarte darf zwischen Textzeilen weitergehen. Normale
  // Karten und der Summenblock bleiben dagegen ungeteilt.
  const protectedRanges = [...lines, ...blocks.filter(x => x.end - x.start <= pageHeight)];
  const pages = [];
  for (let start = top; start < bottom - 0.001;) {
    let end = Math.min(start + pageHeight, bottom);
    if (end < bottom - 0.001) {
      for (;;) {
        const crossing = protectedRanges.filter(x => x.start < end - 0.001 && x.end > end + 0.001);
        if (!crossing.length) break;
        const next = Math.min(...crossing.map(x => x.start));
        if (next <= start + 0.001) {
          // Überlappende Spalten können zusammen höher als eine Seite sein.
          // Dann nur zwischen echten Textzeilen schneiden, nie mitten darin.
          end = Math.min(start + pageHeight, bottom);
          for (;;) {
            const text = lines.filter(x => x.start < end - 0.001 && x.end > end + 0.001);
            if (!text.length) break;
            end = Math.min(...text.map(x => x.start));
          }
          break;
        }
        end = next;
      }
    }
    if (end <= start + 0.001) throw new Error('PDF-Zeile ist höher als eine Seite');
    pages.push({ start, end });
    start = end;
  }
  return pages;
}

export function escapePdfText(value) {
  return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// Bei html2canvas liegen Schriftpixel nicht immer genau in den DOM-Zeilenboxen.
// Die Vorlage hat ausschließlich helle Flächen (ab #e5e5e5); Text und die
// Kopf-Trennlinie sind dunkler. Tatsächliche Rasterzeilen schützen deshalb auch
// überlange Kontaktkarten vor einem Schnitt durch Buchstaben.
export function pdfInkLines({ data, width, height }, scale) {
  const lines = []; let start = -1;
  for (let y = 0; y <= height; y++) {
    let ink = false;
    if (y < height) for (let x = 0; x < width; x++) {
      const p = (y * width + x) * 4;
      if (data[p + 3] > 32 && Math.min(data[p], data[p + 1], data[p + 2]) < 200) { ink = true; break; }
    }
    if (ink && start < 0) start = y;
    if (!ink && start >= 0) {
      lines.push({ start: Math.max(0, start - 1) / scale, end: Math.min(height, y + 1) / scale });
      start = -1;
    }
  }
  return lines;
}

function measurePdf(element, pageHeight) {
  const box = element.getBoundingClientRect();
  const style = getComputedStyle(element);
  // html2canvas zeichnet Unterlängen teils unterhalb der DOM-Zeilenbox.
  // Ein kleiner Teil des vorhandenen Weißraums schützt die letzte Zeile;
  // der vollständige untere Außenrand würde unnötige Restseiten erzeugen.
  const bottomGuard = Math.min(16, parseFloat(style.paddingBottom) || 0);
  const relative = node => {
    const rect = node.getBoundingClientRect();
    return { start: rect.top - box.top, end: rect.bottom - box.top };
  };
  const blocks = [...element.querySelectorAll('[data-pdf-keep]')].map(relative);
  for (const heading of element.querySelectorAll('[data-pdf-heading]')) {
    const next = heading.nextElementSibling;
    const first = next?.matches('[data-pdf-keep]') ? next : next?.querySelector('[data-pdf-keep]');
    if (first) blocks.push({ start: relative(heading).start, end: relative(first).end });
  }
  // Der Footer soll nicht allein eine fast leere letzte Seite erzeugen.
  const totals = element.querySelector('[data-pdf-totals]');
  const footer = element.querySelector('[data-pdf-footer]');
  if (totals && footer) {
    const together = { start: relative(totals).start, end: relative(footer).end + bottomGuard };
    if (together.end - together.start <= pageHeight) blocks.push(together);
  }
  const bottom = Math.min(box.height, box.height - (parseFloat(style.paddingBottom) || 0) + bottomGuard);
  return { top: parseFloat(style.paddingTop) || 0, bottom, blocks };
}

export async function savePaginatedPdf(element, renderCanvas, pdf, filename) {
  document.body.appendChild(element);
  try {
    await document.fonts?.ready;
    await Promise.all([...element.querySelectorAll('img')].map(image => image.decode()));
    const width = element.getBoundingClientRect().width;
    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = pdf.internal.pageSize.getHeight();
    const margin = parseFloat(getComputedStyle(element).paddingTop) || 0;
    const available = pdfHeight * width / pdfWidth - 2 * margin;
    // Extrem lange Freitexte bekommen eine eigene Grid-Zeile. So können ihre
    // Textzeilen nicht mit einer zweiten Spalte untrennbar überlappen.
    for (const card of element.querySelectorAll('[data-pdf-contacts] > [data-pdf-keep]')) {
      if (card.getBoundingClientRect().height > available) card.style.gridColumn = '1 / -1';
    }
    const canvas = await renderCanvas(element, { scale: 2, useCORS: true, logging: false, backgroundColor: '#ffffff' });
    const scale = canvas.width / width;
    const capacity = Math.floor((pdfHeight * width / pdfWidth - 2 * margin) * scale) / scale;
    const raster = canvas.getContext('2d');
    if (!raster) throw new Error('PDF-Bild konnte nicht gelesen werden');
    // Das Markenbild separat auftragen: der übernommene HTML-Rasterer lässt
    // eingebettete PNGs in dieser Vorlage trotz erfolgreichem Laden aus.
    const origin = element.getBoundingClientRect();
    for (const logo of element.querySelectorAll('img[data-pdf-logo]')) {
      const box = logo.getBoundingClientRect();
      raster.save();
      raster.setTransform(1, 0, 0, 1, 0, 0);
      raster.drawImage(logo, (box.left - origin.left) * scale, (box.top - origin.top) * scale, box.width * scale, box.height * scale);
      raster.restore();
    }
    const lines = pdfInkLines(raster.getImageData(0, 0, canvas.width, canvas.height), scale);
    const bounds = measurePdf(element, capacity);
    bounds.bottom = Math.min(canvas.height / scale, Math.max(bounds.bottom, (lines.at(-1)?.end || 0) + 2));
    const pages = planPdfPages({ ...bounds, lines, pageHeight: capacity });
    for (const [index, page] of pages.entries()) {
      const start = Math.round(page.start * scale), end = Math.round(page.end * scale);
      const slice = document.createElement('canvas');
      slice.width = canvas.width;
      slice.height = end - start;
      const context = slice.getContext('2d');
      if (!context) throw new Error('PDF-Bild konnte nicht erzeugt werden');
      context.drawImage(canvas, 0, start, canvas.width, slice.height, 0, 0, canvas.width, slice.height);
      if (index) pdf.addPage();
      pdf.addImage(slice.toDataURL('image/png'), 'PNG', 0, margin * pdfWidth / width, pdfWidth, slice.height * pdfWidth / canvas.width, undefined, 'FAST');
    }
    pdf.save(filename);
  } finally {
    element.remove();
  }
}
