let currentFormat = 'story';
let iframeFmt     = 'story';

const DL_SCALE = { story:3, square:2, discord:2 };
const IFRAME_SIZES = { story:{w:360,h:640}, square:{w:500,h:500}, discord:{w:600,h:380} };

function switchFormat(fmt, btn) {
    document.querySelectorAll('.sc-tab:not(.iframe-fmt)').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');

    const panelIframe = document.getElementById('panel-iframe');
    const dlBtn  = document.getElementById('dlBtn');
    const dlHint = document.getElementById('dlHint');

    if (fmt === 'iframe') {
        document.querySelectorAll('.sc-card').forEach(c => c.classList.remove('active'));
        panelIframe.style.display = 'flex';
        dlBtn.style.display  = 'none';
        dlHint.style.display = 'none';
        updateIframeCode();
    } else {
        panelIframe.style.display = 'none';
        dlBtn.style.display  = '';
        dlHint.style.display = '';
        document.querySelectorAll('.sc-card').forEach(c => c.classList.remove('active'));
        document.getElementById('card-' + fmt).classList.add('active');
    }
    currentFormat = fmt;
}

function setIframeFmt(fmt, btn) {
    document.querySelectorAll('.iframe-fmt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    iframeFmt = fmt;
    updateIframeCode();
}

function updateIframeCode() {
    const { w, h } = IFRAME_SIZES[iframeFmt];
    const src  = IFRAME_BASE_URL + '&format=' + iframeFmt;
    const code = `<iframe src="${src}" width="${w}" height="${h}" frameborder="0" scrolling="no" style="border-radius:12px;overflow:hidden;display:block;"></iframe>`;
    document.getElementById('iframeCode').value = code;
}

function copyIframeCode() {
    const code = document.getElementById('iframeCode').value;
    const btn  = document.getElementById('copyIframeBtn');
    navigator.clipboard.writeText(code).then(() => {
        btn.textContent = '✓ Copié';
        setTimeout(() => { btn.textContent = 'Copier le code'; }, 2000);
    });
}

function applyManualCover(card) {
    const snaps = [];
    card.querySelectorAll('img[data-poster]').forEach(img => {
        if (!img.naturalWidth || !img.naturalHeight) return;
        const nw = img.naturalWidth,  nh = img.naturalHeight;
        const cw = img.offsetWidth,   ch = img.offsetHeight;
        if (!cw || !ch) return;

        const s    = Math.max(cw / nw, ch / nh);
        const w    = Math.ceil(nw * s);
        const h    = Math.ceil(nh * s);
        const left = Math.floor((cw - w) / 2);
        const top  = Math.floor((ch - h) / 2);

        const parent = img.parentElement;
        snaps.push({ img, origStyle: img.style.cssText,
                     parent, origPos: parent.style.position, origOv: parent.style.overflow });
        parent.style.position = 'relative';
        parent.style.overflow = 'hidden';
        img.style.cssText = `position:absolute;width:${w}px;height:${h}px;top:${top}px;left:${left}px;display:block;`;
    });
    return snaps;
}

function restoreManualCover(snaps) {
    snaps.forEach(({ img, origStyle, parent, origPos, origOv }) => {
        img.style.cssText     = origStyle;
        parent.style.position = origPos;
        parent.style.overflow = origOv;
    });
}

async function downloadCard() {
    const btn  = document.getElementById('dlBtn');
    const hint = document.getElementById('dlHint');
    btn.disabled    = true;
    btn.textContent = 'Génération…';
    hint.style.color = '#444';
    hint.textContent = 'Chargement des images…';

    const card = document.getElementById('card-' + currentFormat);
    await Promise.all([...card.querySelectorAll('img')].map(img =>
        img.complete ? Promise.resolve()
                     : new Promise(r => { img.onload = img.onerror = r; })
    ));

    try {
        const scale     = DL_SCALE[currentFormat];
        const prevScroll = window.scrollY;

        // Scroll vers y=0 : élimine tout offset de scroll dans html2canvas
        window.scrollTo(0, 0);
        await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));

        const imgSnaps = applyManualCover(card);

        const canvas = await html2canvas(card, {
            scale:           scale,
            useCORS:         true,
            backgroundColor: '#050505',
            logging:         false,
            imageTimeout:    0,
        });

        restoreManualCover(imgSnaps);
        window.scrollTo(0, prevScroll);

        const a = document.createElement('a');
        a.download = DL_NAME[currentFormat];
        a.href = canvas.toDataURL('image/png');
        a.click();
        hint.textContent = '✓ Image générée avec succès.';
        hint.style.color = '#9dffb0';
    } catch(e) {
        hint.textContent = 'Erreur lors de la génération.';
        hint.style.color = '#FF6B6B';
        console.error(e);
    }
    btn.disabled    = false;
    btn.textContent = '↓ Télécharger le PNG';
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.sc-card img').forEach(img => { img.loading = 'eager'; });
    setTimeout(() => {
        const hint = document.getElementById('dlHint');
        if (hint.style.color !== 'rgb(157, 255, 176)')
            hint.textContent = 'Prêt — cliquez pour générer.';
    }, 2000);
});
