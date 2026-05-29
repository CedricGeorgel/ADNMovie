/**
 * ANOMALY.JS - Protocole de corruption visuelle (Easter Egg)
 */
document.addEventListener('DOMContentLoaded', () => {
    // Déclenchement d'un "kernel panic" visuel aléatoire
    setTimeout(() => {
        document.body.style.transform = "scale(1.05) rotate(1deg)";
        document.body.style.filter = "invert(100%) hue-rotate(180deg)";
        
        setTimeout(() => {
            document.body.style.transform = "none";
            document.body.style.filter = "none";
        }, 150); // Le glitch dure une fraction de seconde
    }, 2000);
});