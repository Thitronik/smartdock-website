'use strict';
// Provisioning stays in this browser. No request, storage or external QR API.
(() => {
  const target = document.getElementById('setup-qr');
  if (!target) return;
  try {
    const uri = target.getAttribute('data-provisioning');
    const code = qrcodegen.QrCode.encodeText(uri, qrcodegen.QrCode.Ecc.MEDIUM);
    const border = 4, size = code.size + border * 2;
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', `0 0 ${size} ${size}`);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'QR-Code für Microsoft Authenticator');
    svg.setAttribute('shape-rendering', 'crispEdges');
    const background = document.createElementNS(ns, 'rect');
    background.setAttribute('width', String(size));
    background.setAttribute('height', String(size));
    background.setAttribute('fill', '#fff');
    const path = document.createElementNS(ns, 'path');
    let modules = '';
    for (let y = 0; y < code.size; y++) for (let x = 0; x < code.size; x++) {
      if (code.getModule(x, y)) modules += `M${x + border},${y + border}h1v1h-1z`;
    }
    path.setAttribute('d', modules);
    path.setAttribute('fill', '#000');
    svg.append(background, path);
    target.replaceChildren(svg);
    target.removeAttribute('data-provisioning');
  } catch {
    target.textContent = 'QR-Code konnte nicht angezeigt werden. Bitte den Einrichtungsschlüssel manuell verwenden.';
  }
})();
