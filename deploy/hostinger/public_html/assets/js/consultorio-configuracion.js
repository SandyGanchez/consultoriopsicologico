(function () {
    'use strict';

    var mapElement = document.getElementById('clinicMap');

    if (!mapElement || typeof L === 'undefined') {
        return;
    }

    var latInput = document.getElementById('LatitudDir');
    var lngInput = document.getElementById('LongitudDir');
    var coordsDisplay = document.getElementById('clinicMapCoords');
    var mapMessage = document.getElementById('clinicMapMessage');

    var defaultLat = parseFloat(mapElement.dataset.defaultLat || '19.432608');
    var defaultLng = parseFloat(mapElement.dataset.defaultLng || '-99.133209');

    var initialLat = parseFloat(latInput && latInput.value ? latInput.value : '');
    var initialLng = parseFloat(lngInput && lngInput.value ? lngInput.value : '');

    if (Number.isNaN(initialLat) || Number.isNaN(initialLng)) {
        initialLat = defaultLat;
        initialLng = defaultLng;
    }

    var map = L.map('clinicMap').setView([initialLat, initialLng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    var marker = L.marker([initialLat, initialLng], {
        draggable: true
    }).addTo(map);

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

    updateCoords(initialLat, initialLng, false);

    marker.on('dragend', function () {
        var position = marker.getLatLng();
        updateCoords(position.lat, position.lng, true);
        hideMapMessage();
    });

    map.on('click', function (event) {
        marker.setLatLng(event.latlng);
        updateCoords(event.latlng.lat, event.latlng.lng, true);
        hideMapMessage();
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

            showMapMessage('Obteniendo ubicación...', 'info');

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    var lat = position.coords.latitude;
                    var lng = position.coords.longitude;

                    marker.setLatLng([lat, lng]);
                    map.setView([lat, lng], 16);
                    updateCoords(lat, lng, true);
                    hideMapMessage();
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
            var partes = [
                document.getElementById('CalleDir'),
                document.getElementById('NumExtDir'),
                document.getElementById('ColoniaDir'),
                document.getElementById('MunicipioDir'),
                document.getElementById('EstadoDir'),
                document.getElementById('CodPostDir'),
                document.getElementById('PaisDir')
            ]
                .filter(function (el) {
                    return el && el.value.trim() !== '';
                })
                .map(function (el) {
                    return el.value.trim();
                });

            if (partes.length < 2) {
                showMapMessage(
                    'Completa al menos calle, colonia y municipio '
                    + 'para buscar la dirección.',
                    'warning'
                );
                return;
            }

            var consulta = partes.join(', ');

            showMapMessage('Buscando dirección...', 'info');

            fetch(
                'https://nominatim.openstreetmap.org/search?format=json&limit=1&q='
                + encodeURIComponent(consulta),
                {
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            )
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('network');
                    }

                    return response.json();
                })
                .then(function (results) {
                    if (!Array.isArray(results) || results.length === 0) {
                        showMapMessage(
                            'No se encontró la dirección indicada.',
                            'warning'
                        );
                        return;
                    }

                    var lat = parseFloat(results[0].lat);
                    var lng = parseFloat(results[0].lon);

                    marker.setLatLng([lat, lng]);
                    map.setView([lat, lng], 16);
                    updateCoords(lat, lng, true);
                    hideMapMessage();
                })
                .catch(function () {
                    showMapMessage(
                        'No fue posible buscar la dirección. '
                        + 'Intenta nuevamente o coloca el marcador manualmente.',
                        'warning'
                    );
                });
        });
    }

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
})();
