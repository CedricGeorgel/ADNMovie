(function () {
    const messages = [
        // visite 1 — message par défaut, pas de badge
        null,
        // visite 2
        ["2ème incident détecté", "Cette page n'existe pas. Deuxième constat. Le dossier commence à s'épaissir."],
        // visite 3
        ["Récidive confirmée", "Vous revenez souvent ici… Le département archives note votre passage."],
        // visite 4
        ["Incident #4 — Signalement automatique", "Peut-être cherchez-vous quelque chose qui n'existe pas ? Certaines choses sont mieux oubliées."],
        // visite 5
        ["Dossier classé confidentiel", "Au bout du cinquième incident, on se demande si c'est la page qui est perdue ou vous."],
        // visite 6
        ["Anomalie persistante", "Cette séquence a été coupée au montage. Définitivement."],
        // visite 7
        ["Niveau d'alerte : élevé", "Sept passages. Le projectionniste commence à vous reconnaître dans le noir."],
        // visite 8
        ["Vous faites partie du décor maintenant", "Cette page n'existe pas, mais vous, si. C'est peut-être suffisant."],
        // visite 9
        ["Protocole fantôme activé", "Neuf visites. Il est possible que vous soyez vous-même une scène coupée."],
        // visite 10+
        ["Accès interdit aux archives perdues", "Vous connaissez cet endroit mieux que ses créateurs. Ce n'est pas rassurant."],
    ];

    const count = parseInt(localStorage.getItem('moovie_404_count') || '0', 10) + 1;
    localStorage.setItem('moovie_404_count', count);

    const idx = Math.min(count - 1, messages.length - 1);
    const entry = messages[idx];

    if (entry) {
        const badge = document.getElementById('incidentBadge');
        const msg   = document.getElementById('errorMsg');
        badge.textContent = entry[0];
        badge.style.display = 'inline-block';
        msg.textContent = entry[1];
    }
})();
