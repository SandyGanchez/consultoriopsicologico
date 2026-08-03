(function () {
    'use strict';

    var SCALE = {
        normal: '1',
        large: '1.10',
        xlarge: '1.20'
    };

    var scaleKey = 'psico_font_scale';
    var boldKey = 'psico_bold_text';

    var scaleRaw = '';
    var boldRaw = '';

    try {
        scaleRaw = String(localStorage.getItem(scaleKey) || 'normal');
        boldRaw = String(localStorage.getItem(boldKey) || 'false');
    } catch (e) {
        scaleRaw = 'normal';
        boldRaw = 'false';
    }

    if (!Object.prototype.hasOwnProperty.call(SCALE, scaleRaw)) {
        scaleRaw = 'normal';
    }

    if (boldRaw !== 'true' && boldRaw !== 'false') {
        boldRaw = 'false';
    }

    var root = document.documentElement;
    root.setAttribute('data-font-size', scaleRaw);
    root.setAttribute('data-bold-text', boldRaw);
    root.style.setProperty('--app-font-scale', SCALE[scaleRaw]);
    root.style.setProperty(
        '--app-font-weight',
        boldRaw === 'true' ? '600' : '400'
    );
    root.style.setProperty(
        '--app-heading-weight',
        boldRaw === 'true' ? '700' : '700'
    );
})();
