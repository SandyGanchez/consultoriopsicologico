(function () {
    'use strict';

    var mapElement = document.getElementById('clinicMap');
    var form = document.getElementById('formConfiguracionConsultorio');

    if (!mapElement || typeof L === 'undefined') {
        initLogoPortada();
        return;
    }

    var NOMINATIM_HEADERS = {
        Accept: 'application/json'
    };

    var latInput = document.getElementById('LatitudDir');
    var lngInput = document.getElementById('LongitudDir');
    var coordsDisplay = document.getElementById('clinicMapCoords');
    var mapMessage = document.getElementById('clinicMapMessage');
    var resultsBox = document.getElementById('clinicMapSearchResults');
    var detectedBox = document.getElementById('clinicMapDetected');
    var detectedTitle = document.getElementById('clinicMapDetectedTitle');
    var detectedText = document.getElementById('clinicMapDetectedText');
    var detectedActions = document.getElementById('clinicMapDetectedActions');

    var fieldIds = [
        'CalleDir',
        'NumExtDir',
        'NumIntDir',
        'ColoniaDir',
        'MunicipioDir',
        'EstadoDir',
        'CodPostDir',
        'PaisDir',
        'ReferenciaDir'
    ];

    var hasInitialCoords = mapElement.dataset.hasCoords === '1';
    var initialLat = parseFloat(mapElement.dataset.initialLat || '');
    var initialLng = parseFloat(mapElement.dataset.initialLng || '');
    var hasValidInitial =
        hasInitialCoords
        && !Number.isNaN(initialLat)
        && !Number.isNaN(initialLng);

    // Solo vista inicial del mapa cuando aún no hay coordenadas guardadas.
    // Nunca se escribe en LatitudDir/LongitudDir.
    var viewportLat = 23.634501;
    var viewportLng = -102.552784;
    var viewportZoom = 5;

    var map = L.map('clinicMap').setView(
        hasValidInitial ? [initialLat, initialLng] : [viewportLat, viewportLng],
        hasValidInitial ? 15 : viewportZoom
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    var marker = null;
    var reverseTimer = null;
    var pendingDetected = null;
    var initialAddressFingerprint = addressFingerprint();
    var initialCoordsFingerprint = coordsFingerprint();
    var confirmBypass = false;

    function fieldValue(id) {
        var el = document.getElementById(id);
        return el ? String(el.value || '').trim() : '';
    }

    function setFieldValue(id, value) {
        var el = document.getElementById(id);
        if (!el || value == null) {
            return;
        }
        var texto = String(value).trim();
        if (texto !== '') {
            el.value = texto;
        }
    }

    function addressFingerprint() {
        return fieldIds
            .filter(function (id) {
                return id !== 'ReferenciaDir';
            })
            .map(fieldValue)
            .join('|')
            .toLowerCase();
    }

    function coordsFingerprint() {
        return String(latInput && latInput.value ? latInput.value : '')
            + ','
            + String(lngInput && lngInput.value ? lngInput.value : '');
    }

    function hasWrittenAddress() {
        return ['CalleDir', 'ColoniaDir', 'MunicipioDir', 'EstadoDir']
            .some(function (id) {
                return fieldValue(id) !== '';
            });
    }

    function showMapMessage(text, type) {
        if (!mapMessage) {
            return;
        }

        mapMessage.textContent = text;
        mapMessage.className = 'settings-alert alert alert-' + (type || 'info');
        mapMessage.classList.remove('d-none');
    }

    function hideMapMessage() {
        if (mapMessage) {
            mapMessage.classList.add('d-none');
        }
    }

    function hideResults() {
        if (!resultsBox) {
            return;
        }
        resultsBox.innerHTML = '';
        resultsBox.classList.add('d-none');
    }

    function hideDetected() {
        pendingDetected = null;
        if (detectedBox) {
            detectedBox.classList.add('d-none');
        }
        if (detectedActions) {
            detectedActions.innerHTML = '';
        }
    }

    function updateCoords(lat, lng, updateInputs) {
        var latFixed = Number(lat).toFixed(6);
        var lngFixed = Number(lng).toFixed(6);

        if (updateInputs !== false) {
            if (latInput) {
                latInput.value = latFixed;
            }
            if (lngInput) {
                lngInput.value = lngFixed;
            }
        }

        if (coordsDisplay) {
            coordsDisplay.textContent =
                'Coordenadas actuales: ' + latFixed + ', ' + lngFixed;
        }
    }

    function ensureMarker(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
            return marker;
        }

        marker = L.marker([lat, lng], { draggable: true }).addTo(map);

        marker.on('dragend', function () {
            var position = marker.getLatLng();
            updateCoords(position.lat, position.lng, true);
            scheduleReverse(
                position.lat,
                position.lng,
                'drag'
            );
        });

        return marker;
    }

    function placeAt(lat, lng, zoom) {
        ensureMarker(lat, lng);
        map.setView([lat, lng], zoom || 16);
        updateCoords(lat, lng, true);
    }

    function buildSearchQuery() {
        return [
            fieldValue('CalleDir'),
            fieldValue('NumExtDir'),
            fieldValue('ColoniaDir'),
            fieldValue('MunicipioDir'),
            fieldValue('EstadoDir'),
            fieldValue('CodPostDir'),
            fieldValue('PaisDir') || 'México'
        ]
            .filter(function (parte) {
                return parte !== '';
            })
            .join(', ');
    }

    function mapNominatimAddress(address) {
        if (!address || typeof address !== 'object') {
            return {};
        }

        var municipio =
            address.city
            || address.town
            || address.municipality
            || address.county
            || address.village
            || '';

        var colonia =
            address.suburb
            || address.neighbourhood
            || address.quarter
            || address.city_district
            || '';

        return {
            PaisDir: address.country || '',
            EstadoDir: address.state || '',
            MunicipioDir: municipio,
            ColoniaDir: colonia,
            CalleDir: address.road || address.pedestrian || '',
            CodPostDir: address.postcode || '',
            NumExtDir: address.house_number || ''
        };
    }

    function applyDetectedAddress(mapped) {
        if (!mapped) {
            return;
        }

        setFieldValue('PaisDir', mapped.PaisDir);
        setFieldValue('EstadoDir', mapped.EstadoDir);
        setFieldValue('MunicipioDir', mapped.MunicipioDir);
        setFieldValue('ColoniaDir', mapped.ColoniaDir);
        setFieldValue('CalleDir', mapped.CalleDir);
        setFieldValue('CodPostDir', mapped.CodPostDir);
        setFieldValue('NumExtDir', mapped.NumExtDir);
        // NumIntDir y ReferenciaDir se conservan.
    }

    function showDetected(title, displayName, mapped, mode) {
        pendingDetected = {
            displayName: displayName,
            mapped: mapped,
            mode: mode
        };

        if (!detectedBox || !detectedTitle || !detectedText || !detectedActions) {
            return;
        }

        detectedTitle.textContent = title;
        detectedText.textContent = displayName || 'Dirección aproximada no disponible.';
        detectedActions.innerHTML = '';

        if (mode === 'geo') {
            var btnCoords = document.createElement('button');
            btnCoords.type = 'button';
            btnCoords.className = 'btn btn-settings-secondary btn-sm';
            btnCoords.textContent = 'Usar solo las coordenadas';
            btnCoords.addEventListener('click', function () {
                hideDetected();
                showMapMessage(
                    'Coordenadas listas. Pulsa «Guardar configuración» para conservarlas.',
                    'success'
                );
            });

            var btnFill = document.createElement('button');
            btnFill.type = 'button';
            btnFill.className = 'btn btn-settings-primary btn-sm';
            btnFill.textContent = 'Completar también la dirección detectada';
            btnFill.addEventListener('click', function () {
                if (hasWrittenAddress()) {
                    var ok = window.confirm(
                        'Ya hay una dirección escrita. ¿Deseas completar o reemplazar '
                        + 'los campos con la dirección detectada? '
                        + 'El número interior y la referencia se conservarán.'
                    );
                    if (!ok) {
                        return;
                    }
                }
                applyDetectedAddress(mapped);
                hideDetected();
                showMapMessage(
                    'Dirección detectada aplicada. Revisa los campos y guarda.',
                    'success'
                );
            });

            detectedActions.appendChild(btnCoords);
            detectedActions.appendChild(btnFill);
        } else {
            var btnUse = document.createElement('button');
            btnUse.type = 'button';
            btnUse.className = 'btn btn-settings-primary btn-sm';
            btnUse.textContent = 'Usar dirección detectada';
            btnUse.addEventListener('click', function () {
                if (hasWrittenAddress()) {
                    var ok = window.confirm(
                        '¿Deseas actualizar los campos de dirección con la ubicación '
                        + 'detectada en el mapa? El número interior y la referencia '
                        + 'se conservarán.'
                    );
                    if (!ok) {
                        return;
                    }
                }
                applyDetectedAddress(mapped);
                hideDetected();
                showMapMessage(
                    'Dirección detectada aplicada. Revisa los campos y guarda.',
                    'success'
                );
            });
            detectedActions.appendChild(btnUse);
        }

        detectedBox.classList.remove('d-none');
    }

    function reverseGeocode(lat, lng, mode) {
        var url =
            'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat='
            + encodeURIComponent(String(lat))
            + '&lon='
            + encodeURIComponent(String(lng));

        return fetch(url, { headers: NOMINATIM_HEADERS })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('network');
                }
                return response.json();
            })
            .then(function (data) {
                var display =
                    (data && (data.display_name || data.name)) || '';
                var mapped = mapNominatimAddress(
                    data && data.address ? data.address : {}
                );

                showDetected(
                    mode === 'geo'
                        ? 'Ubicación detectada'
                        : 'Ubicación seleccionada en el mapa',
                    display,
                    mapped,
                    mode === 'geo' ? 'geo' : 'drag'
                );
            })
            .catch(function () {
                showMapMessage(
                    'Coordenadas actualizadas. No fue posible obtener la dirección aproximada.',
                    'warning'
                );
            });
    }

    function scheduleReverse(lat, lng, mode) {
        if (reverseTimer) {
            window.clearTimeout(reverseTimer);
        }

        reverseTimer = window.setTimeout(function () {
            reverseGeocode(lat, lng, mode);
        }, 450);
    }

    if (hasValidInitial) {
        ensureMarker(initialLat, initialLng);
        updateCoords(initialLat, initialLng, false);
    } else {
        showMapMessage(
            'Todavía no hay una ubicación en el mapa. Busca la dirección o coloca el marcador.',
            'info'
        );
    }

    map.on('click', function (event) {
        placeAt(event.latlng.lat, event.latlng.lng, map.getZoom());
        hideResults();
        scheduleReverse(event.latlng.lat, event.latlng.lng, 'drag');
    });

    var btnGeo = document.getElementById('btnUsarUbicacion');

    if (btnGeo) {
        btnGeo.addEventListener('click', function () {
            if (!navigator.geolocation) {
                showMapMessage(
                    'Tu navegador no permite obtener la ubicación.',
                    'warning'
                );
                return;
            }

            hideResults();
            showMapMessage('Obteniendo ubicación...', 'info');

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    var lat = position.coords.latitude;
                    var lng = position.coords.longitude;

                    placeAt(lat, lng, 16);
                    hideMapMessage();
                    reverseGeocode(lat, lng, 'geo');
                },
                function () {
                    showMapMessage(
                        'No fue posible obtener tu ubicación. '
                        + 'Puedes seleccionar el punto manualmente.',
                        'warning'
                    );
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        });
    }

    var btnBuscar = document.getElementById('btnBuscarDireccion');

    if (btnBuscar) {
        btnBuscar.addEventListener('click', function () {
            var consulta = buildSearchQuery();

            if (consulta.split(',').length < 2) {
                showMapMessage(
                    'Completa al menos calle, colonia y municipio '
                    + 'para buscar la dirección.',
                    'warning'
                );
                return;
            }

            hideDetected();
            showMapMessage('Buscando dirección...', 'info');

            fetch(
                'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=5&q='
                + encodeURIComponent(consulta),
                { headers: NOMINATIM_HEADERS }
            )
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('network');
                    }
                    return response.json();
                })
                .then(function (results) {
                    if (!Array.isArray(results) || results.length === 0) {
                        hideResults();
                        showMapMessage(
                            'No fue posible localizar esta dirección. Revisa los datos o ajusta el marcador manualmente.',
                            'warning'
                        );
                        return;
                    }

                    hideMapMessage();
                    renderSearchResults(results);
                })
                .catch(function () {
                    hideResults();
                    showMapMessage(
                        'No fue posible buscar la dirección. '
                        + 'Intenta nuevamente o coloca el marcador manualmente.',
                        'warning'
                    );
                });
        });
    }

    function renderSearchResults(results) {
        if (!resultsBox) {
            return;
        }

        resultsBox.innerHTML = '';
        resultsBox.classList.remove('d-none');

        var intro = document.createElement('p');
        intro.className = 'clinic-map-results__intro';
        intro.textContent = 'Selecciona un resultado (aún no se guarda):';
        resultsBox.appendChild(intro);

        results.forEach(function (item, index) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'clinic-map-results__item';
            button.setAttribute('role', 'option');
            button.textContent = item.display_name || ('Resultado ' + (index + 1));

            button.addEventListener('click', function () {
                var lat = parseFloat(item.lat);
                var lng = parseFloat(item.lon);

                if (Number.isNaN(lat) || Number.isNaN(lng)) {
                    showMapMessage(
                        'El resultado seleccionado no tiene coordenadas válidas.',
                        'warning'
                    );
                    return;
                }

                placeAt(lat, lng, 16);
                hideResults();
                showDetected(
                    'Vista previa de la dirección encontrada',
                    item.display_name || '',
                    mapNominatimAddress(item.address || {}),
                    'drag'
                );
                showMapMessage(
                    'Marcador actualizado. Pulsa «Guardar configuración» para conservar los cambios.',
                    'success'
                );
            });

            resultsBox.appendChild(button);
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            if (confirmBypass) {
                confirmBypass = false;
                return;
            }

            var addressChanged =
                addressFingerprint() !== initialAddressFingerprint;
            var coordsChanged =
                coordsFingerprint() !== initialCoordsFingerprint;
            var hasCoordsNow =
                latInput
                && lngInput
                && latInput.value !== ''
                && lngInput.value !== '';

            if (addressChanged && !coordsChanged && hasCoordsNow) {
                event.preventDefault();
                var okAddress = window.confirm(
                    'Modificaste la dirección, pero la ubicación del mapa no se actualizó. '
                    + 'Busca la nueva dirección o confirma que deseas conservar las coordenadas actuales.'
                );
                if (okAddress) {
                    confirmBypass = true;
                    form.requestSubmit();
                }
                return;
            }

            if (coordsChanged && !addressChanged && hasCoordsNow) {
                event.preventDefault();
                var okCoords = window.confirm(
                    'La ubicación del mapa cambió. Confirma que corresponde a la dirección registrada.'
                );
                if (okCoords) {
                    confirmBypass = true;
                    form.requestSubmit();
                }
            }
        });
    }

    window.setTimeout(function () {
        map.invalidateSize();
    }, 200);

    initLogoPortada();

    function initLogoPortada() {
        var logoInput = document.getElementById('logotipoInput');
        var logoPreview = document.getElementById('clinicLogoPreview');
        var logoDrop = document.getElementById('clinicLogoDrop');

        if (logoInput && logoPreview) {
            logoInput.addEventListener('change', function () {
                var file = logoInput.files && logoInput.files[0];
                if (!file) {
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (event) {
                    logoPreview.src = event.target.result;
                    logoPreview.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            });
        }

        if (logoDrop && logoInput) {
            ['dragenter', 'dragover'].forEach(function (name) {
                logoDrop.addEventListener(name, function (event) {
                    event.preventDefault();
                    logoDrop.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (name) {
                logoDrop.addEventListener(name, function (event) {
                    event.preventDefault();
                    logoDrop.classList.remove('is-dragover');
                });
            });
            logoDrop.addEventListener('drop', function (event) {
                var files = event.dataTransfer && event.dataTransfer.files;
                if (!files || !files.length) {
                    return;
                }
                logoInput.files = files;
                logoInput.dispatchEvent(new Event('change'));
            });
        }

        var coverInput = document.getElementById('portadaInput');
        var coverPreview = document.getElementById('clinicCoverPreview');
        var coverDrop = document.getElementById('clinicCoverDrop');

        if (coverInput && coverPreview) {
            coverInput.addEventListener('change', function () {
                var file = coverInput.files && coverInput.files[0];
                if (!file) {
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (event) {
                    coverPreview.src = event.target.result;
                    coverPreview.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            });
        }

        if (coverDrop && coverInput) {
            ['dragenter', 'dragover'].forEach(function (name) {
                coverDrop.addEventListener(name, function (event) {
                    event.preventDefault();
                    coverDrop.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (name) {
                coverDrop.addEventListener(name, function (event) {
                    event.preventDefault();
                    coverDrop.classList.remove('is-dragover');
                });
            });
            coverDrop.addEventListener('drop', function (event) {
                var files = event.dataTransfer && event.dataTransfer.files;
                if (!files || !files.length) {
                    return;
                }
                coverInput.files = files;
                coverInput.dispatchEvent(new Event('change'));
            });
        }
    }
})();
