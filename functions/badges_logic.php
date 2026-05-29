<?php
/**
 * FUNCTIONS/BADGES_LOGIC.PHP
 * Répertoire central des accréditations et anomalies comportementales.
 */

function get_badge_library() {
    return [
        // ── HISTORIQUE & STATUTS ──────────────────────────────────────────────────────
        'EARLY_ADOPTER' => [
            'label' => 'Pionnier', 'color' => '#9dffb0', 'icon' => 'fa-rocket',
            'description' => "Sujet identifié parmi les 50 premiers spécimens du réseau. Une souche originelle d'une valeur inestimable.\nParmi les 50 premiers inscrits."
        ],
        
        'VIP' => [
            'label' => 'Premium', 'color' => '#ffd700', 'icon' => 'fa-crown',
            'description' => "Accès de niveau supérieur autorisé.\nAttribution manuelle."
        ],
        'IDENTIFIED' => [
            'label' => 'Identité Complète', 'color' => '#40e0d0', 'icon' => 'fa-user-check',
            'description' => "Avatar et identifiant paramétrés. Vous n'êtes plus une simple adresse IP anonyme.\nAvatar personnalisé + pseudo + description définis."
        ],

        // ── SESSIONS & MULTIJOUEUR ────────────────────────────────────────────────────
        'HOST_CLUSTER' => [
            'label' => 'Hôte du Cluster', 'color' => '#a7c7e7', 'icon' => 'fa-network-wired',
            'description' => "Organisme parasite dominant. Vous avez créé des nids de visionnage pour y attirer d'autres congénères.\n2 sessions de visionnage créées."
        ],
        'SOCIAL_UNIT' => [
            'label' => 'Unité Sociale', 'color' => '#9dffb0', 'icon' => 'fa-people-group',
            'description' => "Instinct grégaire surdéveloppé. Le sujet s'épanouit au contact répété d'autres colonies.\n10 sessions rejointes."
        ],
        'STOWAWAY' => [
            'label' => 'Passager Clandestin', 'color' => '#ff4d4d', 'icon' => 'fa-user-ninja',
            'description' => "Organisme passif. Vous survivez dans le groupe sans produire d'énergie créative ni de retour synaptique.\nRejoindre une session sans voter malgré des propositions actives."
        ],

        // ── ALGORITHME & COMPORTEMENT ─────────────────────────────────────────────────
        'CONSENSUS' => [
            'label' => 'Influenceur', 'color' => '#7fffd4', 'icon' => 'fa-users-rays',
            'description' => "Vos données ont influencé les sessions de visionnage du cluster. Leader d'opinion détecté.\nAvoir noté un film proposé lors d'une session."
        ],
        'POLARIZED' => [
            'label' => 'Anticonformiste', 'color' => '#ffa500', 'icon' => 'fa-meteor',
            'description' => "Réponse immunitaire violente face à la norme. Le sujet ne s'alimente que d'œuvres provoquant la division.\n5 films aimés à fort indice de polarisation (> 1.5)."
        ],
        'MASTER_CONSENSUS' => [
            'label' => 'Maître du Consensus', 'color' => '#B8A7E7', 'icon' => 'fa-check-to-slot',
            'description' => "Alignement parfait sur la norme. Votre algorithme de pensée s'intègre sans friction.\n5 films aimés à fort indice de consensus (< 0.5)."
        ],
        'PERFECT_RESONANCE' => [
            'label' => 'Résonance Parfaite', 'color' => '#ffd700', 'icon' => 'fa-bullseye',
            'description' => "L'algorithme a prédit chacune de vos impulsions nerveuses. Vous et la machine ne faites plus qu'un seul système nerveux.\nScore de correspondance ≥ 99% sur un film recommandé."
        ],
        'CHAOS_EXPLORER' => [
            'label' => 'Explorateur de Failles', 'color' => '#ff0055', 'icon' => 'fa-shuffle',
            'description' => "Appétit pour les mutations génétiques. Vous vous êtes nourri d'une donnée issue du Facteur Chaos.\nAvoir noté un film issu de la sélection Chaos."
        ],

        // ── PALIERS DE VOLUME ─────────────────────────────────────────────────────────
        'NOVICE' => [
            'label' => 'Sujet Éveillé', 'color' => '#a7c7e7', 'icon' => 'fa-eye',
            'description' => "Ouverture des paupières. Les fonctions vitales sont stables, le sujet commence à observer son environnement.\n1 analyse effectuée."
        ],
        'VOL_25' => [
            'label' => 'Séquenceur Assidu', 'color' => '#9dffb0', 'icon' => 'fa-vial',
            'description' => "Le métabolisme s'accélère. Une conscience rudimentaire commence à se former dans vos analyses.\n25 analyses effectuées."
        ],
        'MASS_ANALYST' => [
            'label' => 'Maître des Données', 'color' => '#ffd700', 'icon' => 'fa-database',
            'description' => "Développement cérébral hypertrophié. Le sujet est devenu un centre nerveux de stockage.\n100 analyses effectuées."
        ],
        'ARCHIVIST' => [
            'label' => 'Archiviste', 'color' => '#E8C07A', 'icon' => 'fa-folder-open',
            'description' => "Classification en cours. Vos biais cognitifs deviennent statistiquement prévisibles.\n50 analyses effectuées."
        ],
        'VOL_100_LIKES' => [
            'label' => 'Grand Archiviste', 'color' => '#B8A7E7', 'icon' => 'fa-book-journal-whills',
            'description' => "Masse critique d'archives validée. Votre empreinte profil est désormais indélébile.\n150 analyses effectuées."
        ],
        'VOL_200_SEEN' => [
            'label' => 'Mémoire Vive', 'color' => '#40e0d0', 'icon' => 'fa-microchip',
            'description' => "Capacité d'absorption visuelle terrifiante. Une fragmentation neuronale est inévitable à ce stade.\n200 analyses effectuées."
        ],

        // ── TEMPORALITÉ & SAISONS ─────────────────────────────────────────────────────
        'NIGHT_OWL' => [
            'label' => 'Noctambule', 'color' => '#B8A7E7', 'icon' => 'fa-moon',
            'description' => "Prédateur nocturne. Vos fonctions cognitives n'atteignent leur apogée qu'une fois le soleil couché.\nPlus de 50% des analyses entre 22h et 4h (min. 10 analyses)."
        ],
        'EARLY_BIRD' => [
            'label' => 'Au Chant du Coq', 'color' => '#E8C07A', 'icon' => 'fa-sun',
            'description' => "Métabolisme matinal. Vos récepteurs s'activent avant l'aube, quand le reste de la colonie dort encore.\nPlus de 50% des analyses entre 4h et 9h (min. 10 analyses)."
        ],
        'GHOST_SYSTEM' => [
            'label' => 'Fantôme du Système', 'color' => '#c0c0c0', 'icon' => 'fa-ghost',
            'description' => "Longévité exceptionnelle. Ce sujet survit dans nos éprouvettes depuis plus de 6 mois sans aucun signe de rejet.\nCompte de +6 mois avec 10 analyses dans les 3 derniers mois."
        ],
        'SEASON_WINTER' => [
            'label' => 'Cinéphile d\'Hiver', 'color' => '#a7c7e7', 'icon' => 'fa-snowflake',
            'description' => "Pas d'hibernation constatée. Une frénésie d'analyse s'empare du sujet dès que les températures chutent.\n40 analyses en hiver (déc., jan., fév.) sur une même année."
        ],
        'SEASON_SPRING' => [
            'label' => 'Cinéphile de Printemps', 'color' => '#9dffb0', 'icon' => 'fa-seedling',
            'description' => "Réveil printanier. On observe une éclosion massive de nouvelles données synaptiques dans vos registres.\n40 analyses au printemps (mar., avr., mai) sur une même année."
        ],
        'SEASON_SUMMER' => [
            'label' => 'Cinéphile d\'Été', 'color' => '#ffd700', 'icon' => 'fa-sun',
            'description' => "Résistance à la chaleur. Le sujet maintient une activité intense malgré la surchauffe ambiante.\n40 analyses en été (juin, juil., août) sur une même année."
        ],
        'SEASON_AUTUMN' => [
            'label' => 'Cinéphile d\'Automne', 'color' => '#E8C07A', 'icon' => 'fa-leaf',
            'description' => "Constitution de réserves. Vous accumulez les archives avant la période de dormance cyclique.\n40 analyses en automne (sep., oct., nov.) sur une même année."
        ],

        // ── ADN : TRAITS DE CARACTÈRE ─────────────────────────────────────────────────
        'DNA_COMPLEX_HIGH' => [
            'label' => 'Chercheur de Sens', 'color' => '#ff00ff', 'icon' => 'fa-brain',
            'description' => "Obsession pour les structures alambiquées. Le sujet ne s'épanouit que lorsque son cerveau frôle la surchauffe.\nScore Complexité ≥ 9 (après 5 analyses)."
        ],
        'DNA_COMPLEX_LOW' => [
            'label' => 'Esprit Linéaire', 'color' => '#a7c7e7', 'icon' => 'fa-arrow-right-long',
            'description' => "Besoin de séquences simples. Toute ambiguïté narrative est traitée comme une toxine par votre organisme.\nScore Complexité ≤ −9 (après 5 analyses)."
        ],
        'DNA_REAL_HIGH' => [
            'label' => 'Ancrage Terrestre', 'color' => '#E8C07A', 'icon' => 'fa-earth-europe',
            'description' => "Besoin vital de réalisme. Votre système refuse catégoriquement de quitter le plan physique.\nScore Vraisemblance ≥ 9 (après 5 analyses)."
        ],
        'DNA_REAL_LOW' => [
            'label' => 'Vibreur de l\'Étrange', 'color' => '#B8A7E7', 'icon' => 'fa-alien-8bit',
            'description' => "Tolérance maximale aux anomalies. La gravité est pour vous une variable facultative.\nScore Vraisemblance ≤ −9 (après 5 analyses)."
        ],
        'DNA_FEAR_HIGH' => [
            'label' => 'Somnambule', 'color' => '#ff4d4d', 'icon' => 'fa-skull',
            'description' => "Addiction aux stimuli anxiogènes. Vos glandes surrénales semblent produire du cortisol en continu.\nScore Effroi ≥ 9 (après 5 analyses)."
        ],
        'DNA_FEAR_LOW' => [
            'label' => 'Zone de Confort', 'color' => '#9dffb0', 'icon' => 'fa-mug-hot',
            'description' => "Instinct de préservation. Évitement strict de tout pic d'adrénaline pour maintenir un pouls stable.\nScore Effroi ≤ −9 (après 5 analyses)."
        ],
        'DNA_ADV_HIGH' => [
            'label' => 'Explorateur Orbital', 'color' => '#ffd700', 'icon' => 'fa-shuttle-space',
            'description' => "Besoin d'expansion constant. Le sujet ne supporte pas le confinement et cherche sans cesse de nouveaux horizons.\nScore Aventure ≥ 9 (après 5 analyses)."
        ],
        'DNA_ADV_LOW' => [
            'label' => 'Gardien du Sas', 'color' => '#c0c0c0', 'icon' => 'fa-door-closed',
            'description' => "Sédentarité sécuritaire. L'organisme juge tout changement d'environnement comme une menace potentielle.\nScore Aventure ≤ −9 (après 5 analyses)."
        ],
        'ADRENALINE' => [
            'label' => 'Adrénaline Junkie', 'color' => '#ff4500', 'icon' => 'fa-bolt',
            'description' => "Dépendance aux rythmes cardiaques élevés. Votre métabolisme réclame des doses massives de tension.\nScore Rythme ≥ 9 (après 5 analyses)."
        ],
        'DNA_PACE_LOW' => [
            'label' => 'Chrono-Dilatateur', 'color' => '#a7c7e7', 'icon' => 'fa-hourglass-half',
            'description' => "Appréciation des cycles lents. Le sujet observe le temps s'écouler.\nScore Rythme ≤ −9 (après 5 analyses)."
        ],
        'DNA_SUSP_HIGH' => [
            'label' => 'Chasseur d\'Entropie', 'color' => '#B8A7E7', 'icon' => 'fa-dice-d20',
            'description' => "Instinct de traqueur. Le sujet est fasciné par l'incertitude et la résolution de mystères complexes.\nScore Suspense ≥ 9 (après 5 analyses)."
        ],
        'DNA_SUSP_LOW' => [
            'label' => 'Algorithme Stable', 'color' => '#E8C07A', 'icon' => 'fa-check-double',
            'description' => "Besoin de prédictibilité. Toute imprévu est ressenti comme une erreur de codage génétique.\nScore Suspense ≤ −9 (après 5 analyses)."
        ],
        'DNA_VIBE_HIGH' => [
            'label' => 'Anomalie Confortable', 'color' => '#ff0055', 'icon' => 'fa-biohazard',
            'description' => "Goût pour la dissonance. Le sujet trouve un réconfort suspect dans les ambiances les plus dérangeantes.\nScore Vibe ≥ 9 (après 5 analyses)."
        ],
        'DNA_VIBE_LOW' => [
            'label' => 'Filtre Pastel', 'color' => '#ffb6c1', 'icon' => 'fa-cloud-sun',
            'description' => "Exigence de douceur. Votre organisme réclame un plaid et des ondes positives pour fonctionner.\nScore Vibe ≤ −9 (après 5 analyses)."
        ],
        'ESTHETE' => [
            'label' => 'Esthète Radical', 'color' => '#40e0d0', 'icon' => 'fa-eye',
            'description' => "Hypertrophie du nerf optique. Le sujet pardonne toute carence narrative si la beauté du plan sature sa rétine.\nScore Esthétique ≥ 9 (après 5 analyses)."
        ],
        'DNA_AES_LOW' => [
            'label' => 'Puriste du Code', 'color' => '#c0c0c0', 'icon' => 'fa-terminal',
            'description' => "Rejet de l'artifice. Seule la structure brute et la lumière naturelle parviennent à stimuler vos récepteurs.\nScore Esthétique ≤ −9 (après 5 analyses)."
        ],
        'DNA_FEEL_HIGH' => [
            'label' => 'Émetteur Empathique', 'color' => '#ff6b6b', 'icon' => 'fa-heart-pulse',
            'description' => "Surcharge émotionnelle chronique. Vous absorbez la douleur et la joie des autres comme une éponge biologique.\nScore Sentiment ≥ 9 (après 5 analyses)."
        ],
        'DNA_FEEL_LOW' => [
            'label' => 'Cœur de Silicium', 'color' => '#a7c7e7', 'icon' => 'fa-microchip',
            'description' => "Isolation thermique parfaite. Votre système limbique semble être protégé par une couche de métal froid.\nScore Sentiment ≤ −9 (après 5 analyses)."
        ],

        // ── ANOMALIES SYSTÈME ─────────────────────────────────────────────────────────
        'ANOMALY_X3' => [
            'label' => 'Vision Trouble', 'color' => '#ff00ff', 'icon' => 'fa-bug',
            'description' => "Exposition répétée aux radiations de l'Anomalie. Vos capteurs visuels présentent des signes de corruption irréversibles.\n3 visites de la page Anomalie."
        ],
        'KONAMI' => [
            'label' => 'Anomalie Détectée', 'color' => '#ff0055', 'icon' => 'fa-radiation',
            'description' => "Un signal inconnu a traversé l'interface. Vous avez trouvé la fréquence interdite.\nCode de corruption"
        ],
    ];
}

function render_badge_icon($badgeKey) {
    $lib = get_badge_library();
    if (!isset($lib[$badgeKey])) return '';
    $b = $lib[$badgeKey];
    return '<i class="fa-solid ' . h($b['icon']) . '" title="' . h($b['label']) . '"></i>';
}
