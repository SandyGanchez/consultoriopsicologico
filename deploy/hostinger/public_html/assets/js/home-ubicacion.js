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

    function buildPopupContent() {
        var html = '<strong>'
            + escapeHtml(String(datos.nombre || 'Consultorio'))
            + '</strong>';

        if (datos.direccion) {
            html += '<br>' + escapeHtml(String(datos.direccion));
        }

        if (datos.telefono) {
            html += '<br><a href="tel:'
                + escapeHtml(String(datos.telefono).replace(/\D+/g, ''))
                + '">'
                + escapeHtml(String(datos.telefono))
                + '</a>';
        }

        return html;
    }

    function escapeHtml(value) {
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initMap() {
        if (mapInitialized) {
            return;
        }

        mapInstance = L.map('publicClinicMap').setView([lat, lng], 15);

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }
        ).addTo(mapInstance);

        clinicMarker = L.marker([lat, lng]).addTo(mapInstance);

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

            mapInstance.fitBounds(group.getBounds().pad(0.2));
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
                    'Tu navegador no permite obtener la ubicación.'
                );
                return;
            }

            hideMessage();

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    var userLat = position.coords.latitude;
                    var userLng = position.coords.longitude;

                    if (visitorMarker) {
                        mapInstance.removeLayer(visitorMarker);
                    }

                    visitorMarker = L.marker([userLat, userLng], {
                        title: 'Tu ubicación'
                    }).addTo(mapInstance);

                    visitorMarker.bindPopup('Tu ubicación actual').openPopup();

                    fitBothMarkers();
                },
                function () {
                    showMessage(
                        'No fue posible obtener tu ubicación. '
                        + 'Puedes consultar la del consultorio en el mapa.'
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
