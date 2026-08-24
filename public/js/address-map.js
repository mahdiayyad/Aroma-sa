/**
 * Aroma — location map picker (checkout address, checkout gift-recipient,
 * account addresses — anywhere the "pin my location" method is offered).
 *
 * Drag the pin, search a place, or use the device's current location; the
 * chosen point is written to hidden latitude/longitude inputs — that pair IS
 * the address for this method. Unlike the retired multi-field flow, nothing
 * here ever writes a street/city/region/postal_code field: reverse-geocoding
 * only shows a "Near: …" label for the shopper's own reassurance, purely
 * informational, never submitted or stored.
 *
 * Map tiles: Mapbox's polished streets style when a public access token is
 * configured (MAPBOX_ACCESS_TOKEN — see config/services.php), falling back to
 * plain OpenStreetMap tiles otherwise so the picker still fully works with
 * zero setup. Search + reverse geocoding: Nominatim (OSM's free public
 * geocoder) either way — no key required for that part.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var mapEl = document.getElementById('addressMap');
        if (!mapEl || typeof L === 'undefined') { return; }

        // The plain CDN build resolves default marker icons relative to its
        // own <script src>, which usually works — but pinning the URLs
        // explicitly avoids the classic "broken marker image" Leaflet gotcha.
        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
            iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
            iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png'
        });

        var latInput = document.getElementById('addressLatitude');
        var lngInput = document.getElementById('addressLongitude');
        var searchInput = document.getElementById('addressMapSearch');
        var searchResults = document.getElementById('addressMapSearchResults');
        var locateBtn = document.getElementById('addressMapLocateBtn');
        var detectedBox = document.getElementById('addressMapDetected');
        var detectedText = document.getElementById('addressMapDetectedText');

        var DEFAULT_CENTER = [24.7136, 46.6753]; // Riyadh — sensible default before a pin is set.
        var startLat = parseFloat(mapEl.getAttribute('data-lat'));
        var startLng = parseFloat(mapEl.getAttribute('data-lng'));
        var hasStart = !isNaN(startLat) && !isNaN(startLng);
        var center = hasStart ? [startLat, startLng] : DEFAULT_CENTER;

        var map = L.map(mapEl, { scrollWheelZoom: false }).setView(center, hasStart ? 16 : 11);

        var mapboxToken = mapEl.getAttribute('data-mapbox-token');
        if (mapboxToken) {
            // Mapbox styles are authored for 512px tiles; tileSize/zoomOffset
            // below is Leaflet's own documented pairing for that — omitting it
            // is the classic "map looks zoomed in a level too far" bug.
            L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}{r}?access_token={accessToken}', {
                attribution: '&copy; <a href="https://www.mapbox.com/about/maps/">Mapbox</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 22,
                id: 'mapbox/streets-v12',
                tileSize: 512,
                zoomOffset: -1,
                accessToken: mapboxToken
            }).addTo(map);
        } else {
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);
        }

        // Scroll-to-zoom stays off until the map is actually focused/clicked,
        // so scrolling the page past it doesn't get hijacked by the map —
        // the same "click to activate" pattern used by embedded Google Maps.
        var hint = document.getElementById('addressMapHint');
        function activateScrollZoom() {
            map.scrollWheelZoom.enable();
            if (hint) { hint.classList.add('is-hidden'); }
        }
        function deactivateScrollZoom() { map.scrollWheelZoom.disable(); }

        map.on('click', activateScrollZoom);
        mapEl.addEventListener('focusin', activateScrollZoom);
        mapEl.addEventListener('focusout', deactivateScrollZoom);
        document.addEventListener('click', function (e) {
            if (!mapEl.contains(e.target)) { deactivateScrollZoom(); }
        });

        var marker = L.marker(center, { draggable: true }).addTo(map);

        function setCoords(lat, lng) {
            latInput.value = lat.toFixed(7);
            lngInput.value = lng.toFixed(7);
        }

        // Informational only — never written to a submitted field. Lets the
        // shopper confirm "yes, that's roughly where I am" before saving.
        function reverseGeocode(lat, lng) {
            if (!detectedBox || !detectedText) { return; }

            fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lng + '&addressdetails=1', {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.display_name) { return; }
                    detectedText.textContent = data.display_name;
                    detectedBox.classList.remove('d-none');
                })
                .catch(function () { /* offline/rate-limited: pin + coords still saved fine */ });
        }

        function placeMarker(lat, lng, zoom) {
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], zoom || Math.max(map.getZoom(), 16));
            setCoords(lat, lng);
            reverseGeocode(lat, lng);
        }

        if (hasStart) { reverseGeocode(startLat, startLng); } else { setCoords(center[0], center[1]); }

        marker.on('dragend', function () {
            var pos = marker.getLatLng();
            setCoords(pos.lat, pos.lng);
            reverseGeocode(pos.lat, pos.lng);
        });

        map.on('click', function (e) { placeMarker(e.latlng.lat, e.latlng.lng); });

        // "Use my current location" — the browser prompts for permission.
        if (locateBtn) {
            locateBtn.addEventListener('click', function () {
                if (!navigator.geolocation) { return; }
                locateBtn.disabled = true;
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        placeMarker(pos.coords.latitude, pos.coords.longitude, 16);
                        locateBtn.disabled = false;
                    },
                    function () { locateBtn.disabled = false; },
                    { enableHighAccuracy: true, timeout: 8000 }
                );
            });
        }

        // Place search — debounced so we stay well under Nominatim's fair-use
        // rate limit. Only moves the pin; never fills a text field (none exist).
        var searchTimer = null;
        if (searchInput) {
            var lang = searchInput.dataset;

            function renderRow(text, extraClass, onClick) {
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'aroma-map-search-item' + (extraClass ? ' ' + extraClass : '');
                item.textContent = text;
                if (onClick) { item.addEventListener('click', onClick); }
                else { item.disabled = true; }
                return item;
            }

            function renderResults(results, query) {
                searchResults.innerHTML = '';

                if (!results || !results.length) {
                    searchResults.appendChild(renderRow(
                        (lang.langNoResults || '').replace(':query', query)
                    , 'aroma-map-search-empty'));
                    searchResults.classList.remove('d-none');
                    return;
                }

                results.forEach(function (r) {
                    searchResults.appendChild(renderRow(r.display_name, null, function () {
                        placeMarker(parseFloat(r.lat), parseFloat(r.lon), 16);
                        searchInput.value = r.display_name;
                        searchResults.classList.add('d-none');
                        searchResults.innerHTML = '';
                    }));
                });
                searchResults.classList.remove('d-none');
            }

            function renderError(rateLimited, query) {
                searchResults.innerHTML = '';
                var message = rateLimited ? (lang.langRateLimited || '') : (lang.langError || '');
                searchResults.appendChild(renderRow(message, 'aroma-map-search-empty'));
                searchResults.appendChild(renderRow(lang.langRetry || '', 'aroma-map-search-retry', function () {
                    runSearch(query);
                }));
                searchResults.classList.remove('d-none');
            }

            function renderLoading() {
                searchResults.innerHTML = '';
                searchResults.appendChild(renderRow(lang.langLoading || '', 'aroma-map-search-loading'));
                searchResults.classList.remove('d-none');
            }

            function runSearch(q) {
                renderLoading();

                // A soft proximity bias, not a hard filter: ranks results near
                // wherever the map is currently centered (its own default, the
                // last pin, or wherever the shopper has already panned to)
                // higher, without excluding a genuine match elsewhere in the
                // country the way bounded=1 would. [lon1,lat1,lon2,lat2].
                var offset = 0.5; // ≈55km at Riyadh's latitude — wide, deliberately soft
                var viewbox = (center[1] - offset) + ',' + (center[0] + offset) + ',' +
                              (center[1] + offset) + ',' + (center[0] - offset);
                var url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&countrycodes=sa' +
                          '&limit=8&viewbox=' + encodeURIComponent(viewbox) + '&bounded=0&q=' + encodeURIComponent(q);

                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(function (res) {
                        if (res.status === 429) { var e = new Error('rate_limited'); e.rateLimited = true; throw e; }
                        if (!res.ok) { throw new Error('network'); }
                        return res.json();
                    })
                    .then(function (results) { renderResults(results, q); })
                    .catch(function (err) { renderError(!!(err && err.rateLimited), q); });
            }

            searchInput.addEventListener('input', function () {
                var q = searchInput.value.trim();
                clearTimeout(searchTimer);
                if (q.length < 2) {
                    searchResults.classList.add('d-none');
                    searchResults.innerHTML = '';
                    return;
                }

                searchTimer = setTimeout(function () { runSearch(q); }, 500);
            });

            document.addEventListener('click', function (e) {
                if (e.target !== searchInput && !searchResults.contains(e.target)) {
                    searchResults.classList.add('d-none');
                }
            });
        }

        // The map may start inside a hidden (d-none) panel when "location
        // code" is the active method — Leaflet mis-sizes/mis-renders tiles if
        // initialised while its container has zero size, so re-check once at
        // load (matches the original always-visible case) and again whenever
        // the location-method-toggle script reveals the map panel.
        setTimeout(function () { map.invalidateSize(); }, 200);
        var mapPanel = mapEl.closest('.js-method-panel');
        if (mapPanel) {
            mapPanel.addEventListener('aroma:location-map-shown', function () {
                setTimeout(function () { map.invalidateSize(); }, 50);
            });
        }

        // A saved-address picker (checkout) sets coordinates directly rather
        // than through map interaction — move the pin the same way any other
        // placeMarker() call would.
        document.addEventListener('aroma:location-coords-set', function (e) {
            var lat = parseFloat(e.detail && e.detail.lat);
            var lng = parseFloat(e.detail && e.detail.lng);
            if (isNaN(lat) || isNaN(lng)) { return; }
            setTimeout(function () { placeMarker(lat, lng, 16); }, 50);
        });
    });
})();
