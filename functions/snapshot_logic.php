<?php
/**
 * FUNCTIONS/SNAPSHOT_LOGIC.PHP
 * Fonctions pour les snapshots ADN, le radar temporel et la carte de partage.
 * Le snapshot lui-même est déclenché par cron_snapshot.php le 25 de chaque mois.
 */

// ── Utilitaires période ───────────────────────────────────────

function get_current_period_key(string $type = 'monthly'): string {
    $now = new DateTime();
    return match($type) {
        'quarterly' => $now->format('Y') . '-Q' . ceil((int)$now->format('m') / 3),
        'yearly'    => $now->format('Y'),
        default     => $now->format('Y-m'),
    };
}

function get_previous_period_key(string $type = 'monthly'): string {
    $now = new DateTime();
    if ($type === 'monthly') {
        return (new DateTime('first day of last month'))->format('Y-m');
    } elseif ($type === 'quarterly') {
        $q    = (int)ceil((int)$now->format('m') / 3);
        $prevQ = $q === 1 ? 4 : $q - 1;
        $year  = $q === 1 ? (int)$now->format('Y') - 1 : (int)$now->format('Y');
        return "{$year}-Q{$prevQ}";
    }
    return (string)((int)$now->format('Y') - 1);
}

/**
 * Retourne [dateStart, dateEnd] pour la période EN COURS.
 */
function get_period_bounds_for_type(string $type = 'monthly'): array {
    $now = new DateTime();
    if ($type === 'monthly') {
        return [
            (new DateTime('first day of this month'))->format('Y-m-d 00:00:00'),
            (new DateTime('last day of this month'))->format('Y-m-d 23:59:59'),
        ];
    } elseif ($type === 'quarterly') {
        $q      = (int)ceil((int)$now->format('m') / 3);
        $startM = ($q - 1) * 3 + 1;
        $endM   = $q * 3;
        $year   = $now->format('Y');
        return [
            "$year-" . str_pad($startM, 2, '0', STR_PAD_LEFT) . "-01 00:00:00",
            (new DateTime("$year-$endM-01"))->modify('last day of this month')->format('Y-m-d 23:59:59'),
        ];
    }
    // yearly
    $year = $now->format('Y');
    return ["$year-01-01 00:00:00", "$year-12-31 23:59:59"];
}

/**
 * Libellé lisible de la période.
 */
function get_period_label(string $type = 'monthly'): string {
    $now = new DateTime();
    $months_fr = ['', 'Janvier','Février','Mars','Avril','Mai','Juin',
                  'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    if ($type === 'monthly') {
        return $months_fr[(int)$now->format('m')] . ' ' . $now->format('Y');
    } elseif ($type === 'quarterly') {
        $q = (int)ceil((int)$now->format('m') / 3);
        return "T{$q} " . $now->format('Y');
    }
    return $now->format('Y');
}

/**
 * La carte est visible à partir du jour $showFromDay du mois/trimestre/an.
 */
function is_card_visible(string $type = 'monthly', int $showFromDay = 25): bool {
    $now = new DateTime();
    if ($type === 'monthly') {
        return (int)$now->format('d') >= $showFromDay;
    } elseif ($type === 'quarterly') {
        // Visible à partir du 25 du dernier mois du trimestre
        $q      = (int)ceil((int)$now->format('m') / 3);
        $lastM  = $q * 3;
        $curM   = (int)$now->format('m');
        return $curM === $lastM && (int)$now->format('d') >= $showFromDay;
    }
    // yearly : visible à partir du 25 décembre
    return (int)$now->format('m') === 12 && (int)$now->format('d') >= $showFromDay;
}

// ── Données pour la carte ─────────────────────────────────────

/**
 * Toutes les données de la période pour la carte de partage.
 */
function get_period_card_data(string $userId, string $type = 'monthly'): array {
    [$dateStart, $dateEnd] = get_period_bounds_for_type($type);

    // Films notés pendant la période avec like_score
    $movies = db_fetch_all(
        "SELECT r.movie_id, r.like_score, r.rated_at, m.title, m.poster
         FROM ratings r
         JOIN movies m ON m.tmdb_id = r.movie_id
         WHERE r.user_id = ? AND r.rated_at BETWEEN ? AND ?
           AND r.like_score IS NOT NULL
         ORDER BY r.like_score DESC",
        [$userId, $dateStart, $dateEnd]
    );

    $mostLoved = !empty($movies) ? $movies[0] : null;
    $mostHated = count($movies) > 1 ? end($movies) : null;
    // Ne pas afficher le même film deux fois
    if ($mostHated && $mostLoved && $mostHated['movie_id'] === $mostLoved['movie_id']) {
        $mostHated = null;
    }

    // Taux de mutation vs snapshot du mois précédent
    $prevKey      = get_previous_period_key($type);
    $mutationRate = get_dna_mutation_rate($userId, $prevKey, $type);

    // Compter tous les films vus (avec ou sans like_score)
    $totalSeen = (int)db_fetch_one(
        "SELECT COUNT(*) AS c FROM ratings WHERE user_id=? AND rated_at BETWEEN ? AND ?",
        [$userId, $dateStart, $dateEnd]
    )['c'];

    return [
        'period_key'    => get_current_period_key($type),
        'period_label'  => get_period_label($type),
        'movies_count'  => $totalSeen,
        'most_loved'    => $mostLoved,
        'most_hated'    => $mostHated,
        'mutation_rate' => $mutationRate,
    ];
}

// ── Mutation rate ─────────────────────────────────────────────

/**
 * Distance euclidienne normalisée entre snapshot précédent et DNA actuel.
 * Retourne 0.0 à 100.0 (%).
 */
function get_dna_mutation_rate(string $userId, string $prevKey, string $type = 'monthly'): float {
    $CRITERIA = ['complexite','previsibilite','intensite','malaise','stylisation','dynamique','depaysement','coherence'];

    // Snapshot précédent
    $snapRows = db_fetch_all(
        "SELECT criterio, avg_score FROM user_dna_history WHERE user_id=? AND period_key=? AND snapshot_type=?",
        [$userId, $prevKey, $type]
    );
    if (empty($snapRows)) return 0.0;
    $dnaSnap = array_column($snapRows, 'avg_score', 'criterio');

    // DNA actuel
    $curRows = db_fetch_all('SELECT criterio, avg_score FROM user_dna WHERE user_id=?', [$userId]);
    $dnaCur  = array_column($curRows, 'avg_score', 'criterio');
    if (empty($dnaCur)) return 0.0;

    $sumSq = 0.0;
    foreach ($CRITERIA as $c) {
        $a = (float)($dnaSnap[$c] ?? 0);
        $b = (float)($dnaCur[$c]  ?? 0);
        $sumSq += pow($b - $a, 2);
    }
    $maxDist = sqrt(count($CRITERIA) * pow(20, 2)); // ~56.6
    return round(min(100, (sqrt($sumSq) / $maxDist) * 100), 1);
}

// ── Historique pour le radar temporel ────────────────────────

/**
 * Retourne l'historique des snapshots groupé par période.
 * Format : ['2026-03' => ['complexite' => 4.2, ...], '2026-04' => [...]]
 */
function get_dna_history_grouped(string $userId, string $type = 'monthly'): array {
    $rows = db_fetch_all(
        "SELECT period_key, criterio, avg_score
         FROM user_dna_history
         WHERE user_id=? AND snapshot_type=?
         ORDER BY period_key ASC",
        [$userId, $type]
    );

    $grouped = [];
    foreach ($rows as $row) {
        $grouped[$row['period_key']][$row['criterio']] = (float)$row['avg_score'];
    }
    return $grouped;
}