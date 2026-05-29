<div class="dna-canvas-wrapper" style="position:relative;width:100%;height:100%;display:flex;justify-content:center;align-items:center;overflow:hidden;">
    <canvas id="dnaCanvas" style="pointer-events:none;touch-action:none;"></canvas>
</div>

<script>
(function() {
    const canvas = document.getElementById('dnaCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const cols = 80;
    const rows = 40;
    const charWidth = 10;
    const charHeight = 14;
    
    canvas.width = cols * charWidth;
    canvas.height = rows * charHeight;

    const totalLines = 80;
    const dnaWidth = 14;
    const buildZone = 20;
    let globalPhase = 0;

    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    const getChar = () => chars[Math.floor(Math.random() * chars.length)];

    const lines = Array.from({ length: totalLines }, (_, i) => ({
        y: i - (totalLines - rows),
        side: Math.random() > 0.5 ? 1 : -1,
        c1: getChar(),
        c2: getChar(),
        spawnOffset: (Math.random() - 0.5) * cols * 0.8
    }));

    const rootStyles = getComputedStyle(document.documentElement);
    const pastelBlueHex = rootStyles.getPropertyValue('--pastel-blue').trim() || '#A7C7E7';
    
    const hexToRgb = (hex) => {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : '167, 199, 231';
    };
    const pastelBlueRgb = hexToRgb(pastelBlueHex);

    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.font = "14px monospace";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";

        globalPhase += 0.008; 
        const scrollSpeed = 0.08; 

        lines.forEach((line) => {
            line.y += scrollSpeed;

            if (line.y >= rows) {
                line.y -= totalLines;
                line.side = Math.random() > 0.5 ? 1 : -1;
                line.c1 = getChar();
                line.c2 = getChar();
                line.spawnOffset = (Math.random() - 0.5) * cols * 0.8;
            }

            const screenY = line.y; 

            if (screenY >= -5 && screenY < rows + 5) {
                const t = Math.max(0, Math.min(1, line.y / buildZone));
                const ease = 1 - Math.pow(1 - t, 3); 
                const localPhase = globalPhase - (line.y * 0.15);
                
                const targetX1 = (cols / 2) + Math.cos(localPhase) * dnaWidth;
                const targetX2 = (cols / 2) + Math.cos(localPhase + Math.PI) * dnaWidth;

                const startX = line.side > 0 ? cols + line.spawnOffset : -line.spawnOffset;
                const currentX1 = startX + (targetX1 - startX) * ease;
                const currentX2 = (cols - startX) + (targetX2 - (cols - startX)) * ease;

                const z1 = Math.sin(localPhase);
                const z2 = Math.sin(localPhase + Math.PI);
                const alpha1 = (z1 + 1) / 2 * 0.85 + 0.15;
                const alpha2 = (z2 + 1) / 2 * 0.85 + 0.15;

                const pixelY = screenY * charHeight;

                if (t > 0.9) {
                    const alphaBar = ((alpha1 + alpha2) / 2) * 0.3; 
                    ctx.fillStyle = `rgba(${pastelBlueRgb}, ${alphaBar})`;
                    const startBar = Math.floor(Math.min(currentX1, currentX2));
                    const endBar = Math.ceil(Math.max(currentX1, currentX2));
                    for (let j = startBar + 1; j < endBar; j++) {
                        ctx.fillText("-", j * charWidth, pixelY);
                    }
                }

                const p1 = { x: currentX1 * charWidth, a: alpha1, c: line.c1 };
                const p2 = { x: currentX2 * charWidth, a: alpha2, c: line.c2 };
                const points = z1 > z2 ? [p2, p1] : [p1, p2];

                points.forEach(p => {
                    ctx.fillStyle = `rgba(238, 238, 238, ${p.a})`;
                    ctx.fillText(p.c, p.x, pixelY);
                });
            }
        });

        requestAnimationFrame(animate);
    }
    animate();
})();
</script>