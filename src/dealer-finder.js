// Gemeinsame Karten- und Standortlogik für die übernommenen Händleransichten.
export function coordinates(dealer) {
  const values = [dealer?.latitude, dealer?.longitude];
  if (values.some(value => value == null || String(value).trim() === '')) return null;
  const [lat, lng] = values.map(Number);
  return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180
    ? [lat, lng] : null;
}

export function distanceKm(lat, lng, dealerLat, dealerLng) {
  const a = coordinates({ latitude: lat, longitude: lng });
  const b = coordinates({ latitude: dealerLat, longitude: dealerLng });
  if (!a || !b) return Infinity;
  const rad = Math.PI / 180;
  const h = Math.sin((b[0] - a[0]) * rad / 2) ** 2
    + Math.cos(a[0] * rad) * Math.cos(b[0] * rad) * Math.sin((b[1] - a[1]) * rad / 2) ** 2;
  return 6371 * 2 * Math.asin(Math.sqrt(Math.min(1, Math.max(0, h))));
}

export function filterDealers(dealers, filters, location, countryCode) {
  if (location) return dealers.map(dealer => ({ ...dealer,
    distance: distanceKm(location.lat, location.lng, dealer.latitude, dealer.longitude),
  })).sort((a, b) => a.distance - b.distance);
  const city = filters.city.trim().toLocaleLowerCase();
  const postalCode = filters.postalCode.trim();
  return dealers.filter(dealer => (!filters.country || countryCode(dealer.country) === filters.country)
    && (!city || dealer.address?.toLocaleLowerCase().includes(city))
    && (!postalCode || dealer.address?.includes(postalCode)));
}

// Eine in der Listenansicht versteckte Leaflet-Karte hat Größe 0. flyTo rechnet
// dort mit ungültigen Werten. Erst nach sichtbarer Größe zentrieren; auch ein
// Wechsel zwischen Liste/Karte und eine Größenänderung müssen erfasst werden.
export function syncDealerMap(map, center, markers, Observer = globalThis.ResizeObserver) {
  const container = map.getContainer();
  let disposed = false;
  const update = () => {
    if (disposed || !map._loaded || !map._mapPane?._leaflet_pos || container.clientWidth <= 100 || container.clientHeight <= 100) return;
    map.stop();
    map.invalidateSize({ pan: false, animate: false });
    const selected = coordinates(center);
    const points = markers.map(coordinates).filter(Boolean);
    if (selected) map.setView(selected, 12, { animate: false });
    else if (points.length) map.fitBounds(points, { padding: [50, 50], maxZoom: 12, animate: false });
  };
  const observer = Observer ? new Observer(update) : null;
  observer?.observe(container);
  update();
  return () => {
    disposed = true;
    observer?.disconnect();
    // Leaflet lässt _loaded beim Entfernen gesetzt; die Kartenfläche ist dann
    // bereits weg, bevor React die untergeordneten Effekte aufräumt.
    if (map._loaded && map._mapPane?._leaflet_pos) map.stop();
  };
}

export function requestDealerLocation(geolocation, success, failure) {
  if (!geolocation?.getCurrentPosition) { failure('unsupported'); return; }
  try {
    geolocation.getCurrentPosition(position => {
      const point = coordinates(position.coords);
      if (point) success({ lat: point[0], lng: point[1] });
      else failure('unavailable');
    }, error => failure(error.code === 1 ? 'denied' : error.code === 3 ? 'timeout' : 'unavailable'),
    { timeout: 10000, maximumAge: 60000, enableHighAccuracy: false });
  } catch { failure('unavailable'); }
}

const messages = {
  DE: {
    denied: 'Standortzugriff wurde nicht erlaubt. Bitte suche über Land, Stadt oder PLZ.',
    unavailable: 'Dein Standort konnte nicht ermittelt werden. Bitte suche über Land, Stadt oder PLZ.',
    timeout: 'Die Standortabfrage dauert zu lange. Bitte versuche es erneut oder nutze die Suche.',
    unsupported: 'Dieser Browser unterstützt keine Standortabfrage. Bitte nutze die Suche.',
    loading: 'Standort wird ermittelt …', location: 'Dein Standort',
    loadError: 'Händler konnten nicht geladen werden. Bitte lade die Seite erneut.',
  },
  EN: {
    denied: 'Location access was denied. Please search by country, city or postal code.',
    unavailable: 'Your location could not be determined. Please search by country, city or postal code.',
    timeout: 'The location request took too long. Please try again or use the search.',
    unsupported: 'This browser does not support location requests. Please use the search.',
    loading: 'Finding your location …', location: 'Your location',
    loadError: 'Dealers could not be loaded. Please reload the page.',
  },
  FR: {
    denied: 'L’accès à la localisation a été refusé. Recherchez par pays, ville ou code postal.',
    unavailable: 'Votre position n’a pas pu être déterminée. Recherchez par pays, ville ou code postal.',
    timeout: 'La localisation prend trop de temps. Réessayez ou utilisez la recherche.',
    unsupported: 'Ce navigateur ne prend pas en charge la localisation. Utilisez la recherche.',
    loading: 'Localisation en cours …', location: 'Votre position',
    loadError: 'Impossible de charger les revendeurs. Rechargez la page.',
  },
  NL: {
    denied: 'Toegang tot je locatie is geweigerd. Zoek op land, plaats of postcode.',
    unavailable: 'Je locatie kon niet worden bepaald. Zoek op land, plaats of postcode.',
    timeout: 'Het bepalen van je locatie duurt te lang. Probeer het opnieuw of gebruik de zoekfunctie.',
    unsupported: 'Deze browser ondersteunt geen locatiebepaling. Gebruik de zoekfunctie.',
    loading: 'Locatie wordt bepaald …', location: 'Je locatie',
    loadError: 'De dealers konden niet worden geladen. Laad de pagina opnieuw.',
  },
  ES: {
    denied: 'Se ha denegado el acceso a la ubicación. Busca por país, ciudad o código postal.',
    unavailable: 'No se ha podido determinar tu ubicación. Busca por país, ciudad o código postal.',
    timeout: 'La solicitud de ubicación ha tardado demasiado. Inténtalo de nuevo o utiliza la búsqueda.',
    unsupported: 'Este navegador no admite la ubicación. Utiliza la búsqueda.',
    loading: 'Buscando tu ubicación …', location: 'Tu ubicación',
    loadError: 'No se han podido cargar los distribuidores. Vuelve a cargar la página.',
  },
};

export function dealerMessage(language, key) {
  return (messages[String(language).toUpperCase().split('-')[0]] || messages.DE)[key];
}
