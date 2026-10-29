import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import { allocationFromSelection, moduleFields } from '../src/module-allocation.js';
import { calculate as live, countries } from './fixtures/live-pricing-2026-10-22.js';

test('Erfolgstext unterscheidet echte Hetzner-Anfrage von lokal gespeicherter Pages-Vorschau',async()=>{
 const source=await readFile(new URL('../vendor/website/assets/index-RRVGC1mK.js',import.meta.url),'utf8');
 const expression=source.match(/\(typeof location!=="undefined"&&location\.hostname==="test\.smartdock\.de"\?"Konfigurationsanfrage eingegangen":"Testkonfiguration gespeichert"\)/)?.[0];assert.ok(expression);
 assert.equal(vm.runInNewContext(expression,{location:{hostname:'test.smartdock.de'}}),'Konfigurationsanfrage eingegangen');assert.equal(vm.runInNewContext(expression,{location:{hostname:'thitronik.github.io'}}),'Testkonfiguration gespeichert');
 assert.match(source,/Ot=\{promoCode:A\.promoCode\|\|"",referenceNumber:yt/);
});

async function currentCalculator() {
  const source = await readFile(new URL('../vendor/website/assets/index-RRVGC1mK.js', import.meta.url), 'utf8');
  const first = source.indexOf(',Eg={'), last = source.indexOf(',a4=j.createContext()', first);
  assert.ok(first > 0 && last > first, 'Preisbereich im Anwendungspaket nicht gefunden');
  const context = { sdAllocationFromSelection: allocationFromSelection, console };
  vm.createContext(context);
  vm.runInContext('const ' + source.slice(first + 1, last) + ';globalThis.calculate=uV;', context);
  return context.calculate;
}

// Zusätzliche Kanalmetadaten sind eine bewusste Verbesserung der Vorschau.
// Verglichen werden die auf Bildschirm/PDF sichtbaren Namen und Beträge.
function visible(result) {
  return JSON.stringify({
    items: result.items.map(({ name, price }) => ({ name, price })),
    subtotal: result.subtotal, bundleDiscount: result.bundleDiscount,
    totalPrice: result.totalPrice, vat: result.vat, currency: result.currency,
  });
}

test('Browser-Gegenbeispiel: eine Ankerwinde wie live als WCU für 1.250 EUR', async () => {
  const calculate = await currentCalculator();
  const selected = { ecu: null, tcu: [], acu: '1 Ankerwinde', wcu: null, horn: false };
  const result = calculate(selected, '', 'Deutschland', '', false, 'DE');
  assert.equal(result.items[0].name, 'WCU (1 Ankerwinde)');
  assert.equal(result.totalPrice, 1250);
  assert.equal(visible(result), visible(live(selected, '', 'Deutschland', '', false, 'DE')));
  assert.deepEqual(moduleFields(result.devices, selected).moduleAllocation,
    [{ type: 'WCU', channels: ['anchor1', null] }]);
});

test('Preis-/Modulberechnung stimmt mit eingefrorener Live-Referenz überein', async () => {
  const calculate = await currentCalculator();
  let cases = 0;
  for (const ecu of [null, '1 Motor', '2+ Motoren'])
  for (const tcu of [[], ['Bugstrahlruder'], ['Heckstrahlruder'], ['Heckstrahlruder', 'Bugstrahlruder']])
  for (const acu of [null, '1 Ankerwinde', '2 Ankerwinden'])
  for (const wcu of [null, '1 Winch', '2 Winches'])
  for (const horn of [false, true])
  for (const manufacturer of ['', 'Sleipner proportional'])
  for (const country of Object.keys(countries))
  for (const language of ['DE', 'EN', 'FR', 'NL', 'ES'])
  for (const installation of [false, true])
  for (const promo of ['', ' pibs26 ', 'INVALID']) {
    const args = [{ ecu, tcu, acu, wcu, horn }, manufacturer, country, promo, installation, language];
    assert.equal(visible(calculate(...args)), visible(live(...args)), JSON.stringify(args));
    cases++;
  }
  assert.equal(cases, 414720);
});

test('Jede ausgewählte Zusatzfunktion ist genau einmal belegt', () => {
  for (const tcu of [[], ['Bugstrahlruder'], ['Heckstrahlruder'], ['Bugstrahlruder', 'Heckstrahlruder']])
  for (let anchors = 0; anchors <= 2; anchors++)
  for (let winches = 0; winches <= 2; winches++)
  for (const horn of [false, true]) {
    const selected = { tcu, acu: anchors ? anchors + (anchors === 1 ? ' Ankerwinde' : ' Ankerwinden') : null,
      wcu: winches ? winches + (winches === 1 ? ' Winch' : ' Winches') : null, horn };
    const modules = allocationFromSelection(selected);
    const channels = modules.flatMap(x => x.channels).filter(Boolean);
    assert.equal(channels.length, tcu.length + anchors + winches + Number(horn));
    assert.equal(new Set(channels).size, channels.length);
  }
});

async function provider() {
  const source = await readFile(new URL('../vendor/website/assets/index-RRVGC1mK.js', import.meta.url), 'utf8');
  const first = source.indexOf(',AV=e=>'), last = source.indexOf(',Bs=()=>', first);
  assert.ok(first > 0 && last > first);
  const state = []; let cursor = 0, effects = [];
  const context = {
    j: {
      useState(initial) {
        const i = cursor++;
        if (!(i in state)) state[i] = initial;
        return [state[i], value => { state[i] = typeof value === 'function' ? value(state[i]) : value; }];
      },
      useMemo: fn => fn(), useEffect: fn => effects.push(fn),
    },
    vn: () => ({ currentLanguage: 'DE' }), r: { jsx: (_, props) => props.value }, a4: {},
    uV: await currentCalculator(), sdModuleFields: moduleFields,
  };
  vm.createContext(context);
  vm.runInContext('const ' + source.slice(first + 1, last) + ';globalThis.render=hV;', context);
  function render() {
    cursor = 0; effects = [];
    context.render({});
    effects.forEach(fn => fn());
    cursor = 0; effects = [];
    return context.render({});
  }
  return render;
}

test('Motor abwählen leert Hersteller-/Joystickdaten in Zusammenfassung und PDF-Eingabe', async () => {
  const render = await provider();
  for (const remove of ['uncheck', 'remove']) {
    let ui = render();
    ui.addProduct('ecu', '1 Motor'); ui.addProduct('acu', '1 Ankerwinde');
    ui.setEngineManufacturer('Volvo Penta');
    ui.setVolvoPentaJoystick('Ja, ein Joystick ist vorhanden');
    ui.setVolvoPentaJoystickModel('IPS');
    ui = render();
    assert.equal(ui.configData.motorType, 'Volvo Penta');
    if (remove === 'uncheck') ui.addProduct('ecu', null);
    else ui.removeProduct('ecu');
    ui = render();
    assert.equal(ui.engineManufacturer, '');
    assert.equal(ui.volvoPentaJoystick, '');
    assert.equal(ui.volvoPentaJoystickModel, '');
    assert.equal(ui.configData.motorType, '');
    assert.equal(ui.configData.joystickType, '');
    assert.equal(ui.configData.motorCount, 0);
    assert.equal(ui.configData.ecuCount, 0);
    assert.equal(ui.priceData.totalPrice, 1250);
    assert.equal(ui.answers.manufacturer, undefined);
    assert.equal(ui.answers.engineManufacturer, '');
  }
});

const engineFields = ['merCruiserJoystick', 'merCruiserSystem', 'yamahaJoystick', 'yamahaSystem',
  'volvoPentaJoystick', 'volvoPentaJoystickModel', 'volvoPentaSingleSystem', 'volvoPentaSystem'];
const manufacturers = ['MerCruiser/Mercury', 'Yamaha', 'Volvo Penta'];
function configureEngine(ui, manufacturer) {
  if (manufacturer === 'MerCruiser/Mercury') {
    ui.setMerCruiserJoystick('Ja'); ui.setMerCruiserSystem('MerCruiser Bj 2016 (Z-Antrieb oder Zeus)');
  } else if (manufacturer === 'Yamaha') {
    ui.setYamahaJoystick('Ja'); ui.setYamahaSystem('Helm Master EX');
  } else {
    ui.setVolvoPentaJoystick('Ja, ein Joystick ist vorhanden');
    ui.setVolvoPentaJoystickModel('Joystick EVC B & C (Volvo Penta)');
  }
}
function output(ui) {
  // Öffentliche Providerwerte sind zugleich die Eingaben für Bildschirm, PDF
  // und Testanfrage. Sitzungs-/Navigationsfelder gehören nicht zum Vergleich.
  return JSON.parse(JSON.stringify({ products: ui.selectedProducts, manufacturer: ui.engineManufacturer,
    engine: Object.fromEntries(engineFields.map(k => [k, ui[k]])), config: ui.configData,
    answers: ui.answers, contact: ui.contactData, prices: ui.priceData, steps: ui.getStepSequence() }));
}

test('Alle Herstellerwechsel löschen fremde Angaben; erneute Wahl desselben Herstellers erhält sie', async () => {
  for (const from of manufacturers) for (const to of manufacturers) {
    const read = await provider(); let ui = read();
    ui.addProduct('ecu', '1 Motor'); ui.setEngineManufacturer(from);
    ui = read(); configureEngine(ui, from); ui = read();
    const before = output(ui);
    ui.setEngineManufacturer(to); ui = read();
    if (from === to) assert.deepEqual(output(ui), before);
    else {
      assert.equal(ui.engineManufacturer, to);
      assert.equal(ui.configData.motorType, to);
      assert.equal(ui.configData.joystickType, '');
      for (const key of engineFields) {
        assert.equal(ui[key], '', `${from} → ${to}: ${key}`);
        assert.equal(ui.answers[key], '', `${key} darf nicht in der Anfrage bleiben`);
      }
    }
  }
});

test('Motoranzahlwechsel und Wiederwahl verlangen passende neue Details, Extras bleiben erhalten', async () => {
  for (const manufacturer of manufacturers) for (const [from, to] of [['1 Motor','2+ Motoren'],['2+ Motoren','1 Motor']]) {
    const read = await provider(); let ui = read();
    ui.addProduct('ecu', from); ui.addProduct('acu','1 Ankerwinde'); ui.addProduct('horn', true);
    ui.setCountry('Deutschland'); ui.setEngineManufacturer(manufacturer);
    ui = read(); configureEngine(ui, manufacturer); ui = read();
    const same = output(ui); ui.addProduct('ecu', from); ui = read();
    assert.deepEqual(output(ui), same, 'Gleiche Anzahl darf keine Antworten löschen');
    ui.addProduct('ecu', to); ui = read();
    assert.equal(ui.engineManufacturer, manufacturer);
    assert.equal(ui.configData.motorCount, to === '1 Motor' ? 1 : 2);
    for (const key of engineFields) assert.equal(ui[key], '');
    assert.equal(ui.selectedProducts.acu, '1 Ankerwinde'); assert.equal(ui.selectedProducts.horn, true);
    configureEngine(ui, manufacturer); ui = read(); ui.removeProduct('ecu'); ui = read();
    ui.addProduct('ecu', to); ui = read();
    assert.equal(ui.engineManufacturer, '');
    for (const key of engineFields) assert.equal(ui[key], '');
    assert.ok(ui.getStepSequence().includes('step3_manufacturer'));
  }
});

test('Volvo-Joystickwechsel verwirft nur davon abhängige Modell-/Systemantworten', async () => {
  const read = await provider(); let ui = read();
  ui.addProduct('ecu', '1 Motor'); ui.setEngineManufacturer('Volvo Penta');
  ui = read(); configureEngine(ui, 'Volvo Penta'); ui = read();
  ui.setVolvoPentaJoystick('Nein, nur Gashebel'); ui = read();
  assert.equal(ui.volvoPentaJoystickModel, ''); assert.equal(ui.configData.joystickType, 'Nein, nur Gashebel');
  assert.ok(ui.getStepSequence().includes('step_vol_sys'));
  assert.ok(!ui.getStepSequence().includes('step10_vol_joy_mod'));
  ui.setVolvoPentaSingleSystem('EVC -D, -E'); ui = read();
  ui.setVolvoPentaJoystick('Ja, ein Joystick ist vorhanden'); ui = read();
  assert.equal(ui.volvoPentaSingleSystem, ''); assert.equal(ui.volvoPentaSystem, '');
  assert.equal(ui.volvoPentaJoystickModel, '');
});

test('Länderwechsel und Rückkehr erhalten Auswahlen und aktualisieren alle Preis-/PDF-/Anfragewerte', async () => {
  for (const manufacturer of manufacturers) for (const count of ['1 Motor','2+ Motoren']) {
    const read = await provider(); let ui = read();
    ui.addProduct('ecu', count); ui.addProduct('acu', '1 Ankerwinde'); ui.setEngineManufacturer(manufacturer);
    ui.setCountry('Deutschland'); ui.setInstallationService(true);
    ui = read(); configureEngine(ui, manufacturer); ui = read();
    const german = output(ui);
    for (const country of ['Schweiz','Schweden','Deutschland']) {
      ui.setCountry(country); ui = read();
      const expected = live(ui.selectedProducts, '', country, '', true, 'DE');
      assert.equal(ui.priceData.currency, expected.currency);
      assert.equal(ui.priceData.totalPrice, expected.totalPrice);
      assert.equal(ui.configData.totalPrice, expected.totalPrice);
      assert.equal(ui.priceData.vat, expected.vat);
      assert.equal(ui.contactData.country, country); assert.equal(ui.answers.country, country);
      assert.equal(ui.engineManufacturer, manufacturer);
      for (const key of engineFields) assert.equal(ui[key], german.engine[key]);
    }
    assert.deepEqual(output(ui), german, 'Rückkehr nach Deutschland muss denselben Stand herstellen');
  }
});

test('Nach Hersteller-, Motor- und Länderwechseln entspricht das Ergebnis einer neuen gleichen Konfiguration', async () => {
  for (const manufacturer of manufacturers) for (const count of ['1 Motor','2+ Motoren']) for (const country of ['Deutschland','Schweiz','Schweden']) {
    const fresh = await provider(), changed = await provider();
    let ui = changed(); ui.addProduct('ecu', count === '1 Motor' ? '2+ Motoren' : '1 Motor');
    ui.setEngineManufacturer(manufacturer === 'Volvo Penta' ? 'Yamaha' : 'Volvo Penta'); ui.setCountry('Deutschland');
    ui = changed(); configureEngine(ui, ui.engineManufacturer); ui = changed();
    ui.addProduct('ecu', null); ui = changed();
    for (const read of [fresh, changed]) {
      ui = read(); ui.addProduct('ecu', count); ui.addProduct('acu', '1 Ankerwinde');
      ui.setEngineManufacturer(manufacturer); ui.setCountry(country); ui.setInstallationService(true);
      ui = read(); configureEngine(ui, manufacturer); read();
    }
    assert.deepEqual(output(changed()), output(fresh()), `${manufacturer}, ${count}, ${country}`);
  }
});
