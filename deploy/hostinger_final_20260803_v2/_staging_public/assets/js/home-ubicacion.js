(function () {
    'use strict';

    var mapElement = document.getElementById('publicClinicMap');
    var dataElement = document.getElementById('publicClinicMapData');

    if (!mapElement || !dataElement || typeof L === 'undefined') {
        return;
    }

    var datos;

    try {
        datos = JSON.parse(dataElement.textContent || '{}');
    } catch (error) {
        return;
    }

    var lat = Number(datos.latitud);
    var lng = Number(datos.longitud);

    if (
        Number.isNaN(lat) ||
        Number.isNaN(lng) ||
        lat < -90 ||
        lat > 90 ||
        lng < -180 ||
        lng > 180
    ) {
        return;
    }

    var messageElement = document.getElementById('publicMapMessage');
    var visitorMarker = null;
    var mapInitialized = false;
    var mapInstance = null;
    var clinicMarker = null;

    var clinicIcon = L.divIcon({
        className: 'public-map-marker public-map-marker--clinic',
        html: '<span class="public-map-marker__pin" aria-hidden="true"></span>'
            + '<span class="public-map-marker__label">Consultorio</span>',
        iconSize: [34, 46],
        iconAnchor: [17, 44],
        popupAnchor: [0, -40]
    });

    var visitorIcon = L.divIcon({
        className: 'public-map-marker public-map-marker--visitor',
        html: '<span class="public-map-marker__pin" aria-hidden="true"></span>'
            + '<span class="public-map-marker__label">Tu ubicación</span>',
        iconSize: [34, 46],
        iconAnchor: [17, 44],
        popupAnchor: [0, -40]
    });

    function showMessage(text) {
        if (!messageElement) {
            return;
        }

        messageElement.textContent = text;
        messageElement.classList.remove('d-none');
    }

    function hideMessage() {
        if (messageElement) {
            messageElement.classList.add('d-none');
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildPopupContent() {
        var html = '<strong>'
            + escapeHtml(String(datos.nombre || 'Consultorio'))
            + '</strong>';

        if (datos.direccion) {
            html += '<br>' + escapeHtml(String(datos.direccion));
        }

        if (datos.referencia) {
            html += '<br><em>Referencia: '
                + escapeHtml(String(datos.referencia))
                + '</em>';
        }

        return html;
    }

    function initMap() {
        if (mapInitialized) {
            return;
        }

        mapInstance = L.map('publicClinicMap', {
            scrollWheelZoom: false
        }).setView([lat, lng], 15);

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }
        ).addTo(mapInstance);

        clinicMarker = L.marker([lat, lng], {
            icon: clinicIcon,
            title: 'Consultorio',
            draggable: false,
            keyboard: true
        }).addTo(mapInstance);

        clinicMarker.bindPopup(buildPopupContent()).openPopup();

        mapInitialized = true;
    }

    function fitBothMarkers() {
        if (!mapInstance || !clinicMarker) {
            return;
        }

        if (visitorMarker) {
            var group = L.featureGroup([
                clinicMarker,
                visitorMarker
            ]);

            mapInstance.fitBounds(group.getBounds().pad(0.25));
            return;
        }

        mapInstance.setView([lat, lng], 15);
    }

    initMap();

    var btnGeo = document.getElementById('btnPublicUsarUbicacion');

    if (btnGeo) {
        btnGeo.addEventListener('click', function () {
            if (!navigator.geolocation) {
                showMessage(
                    'No se pudo obtener tu ubicación. Puedes consultar la dirección del consultorio manualmente.'
                );
                return;
            }

            hideMessage();

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    var userLat = position.coords.latitude;
                    var userLng = position.coords.longitude;

                    // Solo en el navegador: no se envía ni guarda.
                    if (visitorMarker) {
                        mapInstance.removeLayer(visitorMarker);
                    }

                    visitorMarker = L.marker([userLat, userLng], {
                        icon: visitorIcon,
                        title: 'Tu ubicación',
                        draggable: false
                    }).addTo(mapInstance);

                    visitorMarker.bindPopup('Tu ubicación').openPopup();
                    fitBothMarkers();
                },
                function () {
                    showMessage(
                        'No se pudo obtener tu ubicación. Puedes consultar la dirección del consultorio manualmente.'
                    );
                    fitBothMarkers();
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        });
    }
})();
