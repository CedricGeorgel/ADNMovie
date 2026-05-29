/**
 * ADN-ZOOM.JS
 * Zoom GSAP : anime le viewBox SVG + agrandit le conteneur simultanément.
 *
 * Au zoom   : le conteneur passe à ZOOM_CONTAINER_W × ZOOM_CONTAINER_H
 * Au dézoom : le conteneur revient à ses dimensions CSS d'origine (lues au premier clic)
 *
 * Dépendance : GSAP core
 * <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
 * <script src="assets/js/adn-zoom.js"></script>
 */

(function () {

    const CRITERIO_CY = {
        complexite:    29.3,
        previsibilite: 49.75,
        intensite:     58.83,
        malaise:       67.78,
        stylisation:  115.64,
        dynamique:    124.74,
        depaysement:  142.91,
        coherence:    172.47,
    };

    const SVG_W      = 60.444;
    const SVG_H      = 191.407;
    const ZOOM_LEVEL = 0.22;
    const ZOOM_W     = SVG_W * ZOOM_LEVEL;
    const ZOOM_H     = SVG_H * ZOOM_LEVEL;
    const DEFAULT_VB = `0 0 ${SVG_W} ${SVG_H}`;

    // Dimensions du conteneur au moment du zoom
    const ZOOM_CONTAINER_W = 220;   // px — ajuste selon ton layout
    const ZOOM_CONTAINER_H = 220;   // px — carré pour garder les proportions

    let _activeCriterio = null;
    let _origW = null;
    let _origH = null;

    window.adnZoom = function (labelEl) {
        const criterio  = labelEl.dataset.criterio;
        const svgId     = labelEl.dataset.svgId;
        const svg       = document.getElementById(svgId);
        if (!svg) return;

        const container = svg.closest('.adn-svg-container');
        if (!container) return;

        const wrapper = labelEl.closest('.adn-chart-wrapper');
        wrapper.querySelectorAll('.adn-label').forEach(el => el.classList.remove('adn-label--active'));

        // Mémorise les dimensions d'origine au premier appel
        if (_origW === null) {
            _origW = container.offsetWidth;
            _origH = container.offsetHeight;
        }

        // Toggle : re-clic → dézoom
        if (_activeCriterio === criterio) {
            _activeCriterio = null;

            gsap.to(svg, {
                attr: { viewBox: DEFAULT_VB },
                duration: 1.2,
                ease: 'expo.out',
            });
            gsap.to(container, {
                width:  _origW,
                height: _origH,
                duration: 1.2,
                ease: 'expo.out',
            });
            return;
        }

        _activeCriterio = criterio;
        labelEl.classList.add('adn-label--active');

        const cy = CRITERIO_CY[criterio];
        if (cy === undefined) return;

        const targetX = (SVG_W / 2) - (ZOOM_W / 2);
        let   targetY = cy - (ZOOM_H / 2);
        targetY = Math.max(0, Math.min(SVG_H - ZOOM_H, targetY));

        // Anime viewBox + conteneur en parallèle
        gsap.to(svg, {
            attr: { viewBox: `${targetX} ${targetY} ${ZOOM_W} ${ZOOM_H}` },
            duration: 1.8,
            ease: 'power3.inOut',
        });
        gsap.to(container, {
            width:  ZOOM_CONTAINER_W,
            height: ZOOM_CONTAINER_H,
            duration: 1.8,
            ease: 'power3.inOut',
        });
    };

})();