<?php
// functions/recommendation_logic.php

require_once __DIR__ . '/../functions/core_db.php';

const RECO_LIMIT   = 6;

// ── Lissage bayésien ─────────────────────────────────────────────────────────
const BAYESIAN_M = 5.0;
const BAYESIAN_C = 0.0; 

// ── V3 : Paramètres de Variance ──────────────────────────────────────────────
const VARIANCE_SMOOTHING = 0.75; // Évite l'exigence infinie (division par zéro)

// ── V3 : Paramètres d'Inertie (Momentum) ─────────────────────────────────────
const STABILITY_THRESHOLD = 1.5; // Distance max avant de considérer un changement de phase
const EPSILON_BOOST_MAX   = 0.30; // Jusqu'à +30% d'exploration si l'utilisateur change

// ── Saillance ────────────────────────────────────────────────────────────────
const SALIENCY_THRESHOLD  = 2.0;
const SALIENCY_WEIGHT_MAX = 0.35;

// ── Axes à veto de tolérance (Sécurité Viscérale) ────────────────────────────
const TOLERANCE_AXES  = ['malaise'];
const TOLERANCE_VETO  = 0.15;  
const TOLERANCE_MIN   = 5.0;   

/**
 * V3 : Score par Probabilité Gaussienne.
 * Calcule si le film "rentre" dans la zone de confort (ou d'éclectisme) de l'utilisateur.
 */
function calculateSimilarityWithReasons(array $userDnaData, array $movieDnaMap): array {
    $baseTotal      = 0.0;
    $saliencyTotal  = 0.0;
    $criteriaCount  = 0;
    $userSalientCount = 0;
    $contributions  = [];

    foreach ($userDnaData as $criterion => $uData) {
        if (!isset($movieDnaMap[$criterion])) continue;

        $userAvg = (float)$uData['avg'];
        $userVar = (float)$uData['var'];
        $userMin = (float)($uData['min'] ?? -10);
        $userMax = (float)($uData['max'] ?? 10);

        $rawAvg     = (float)$movieDnaMap[$criterion]['avg'];
        $sampleSize = (int)($movieDnaMap[$criterion]['sample_size'] ?? 1);

        // Lissage Bayésien du film
        $filmAvg = ($sampleSize * $rawAvg + BAYESIAN_M * BAYESIAN_C) / ($sampleSize + BAYESIAN_M);

        // --- V3 : LOGIQUE DE LA CLOCHE DE GAUSS ---
        // Formule : Proximity = e^( -(film - user)^2 / (2 * (var + smooth)) )
        $varianceFactor = 2 * ($userVar + VARIANCE_SMOOTHING);
        $diffSq         = pow($filmAvg - $userAvg, 2);
        $proximity      = exp(-$diffSq / $varianceFactor);

        // Veto Historique : Si le film sort violemment des bornes min/max connues
        if ($filmAvg < ($userMin - 2) || $filmAvg > ($userMax + 2)) {
            $proximity *= 0.5; // On pénalise ce qui est "trop" inconnu
        }

        // Veto de Sécurité (Malaise)
        if (in_array($criterion, TOLERANCE_AXES)) {
            if (abs($userAvg) > TOLERANCE_MIN && abs($filmAvg) > TOLERANCE_MIN && ($userAvg * $filmAvg) < 0) {
                $proximity *= TOLERANCE_VETO;
            }
        }

        $baseTotal += $proximity;
        $criteriaCount++;

        // Saillance (Traits de caractère)
        $userSalient = abs($userAvg) > SALIENCY_THRESHOLD;
        $filmSalient = abs($filmAvg) > SALIENCY_THRESHOLD;
        $sameSign    = ($userAvg * $filmAvg) > 0;

        if ($userSalient) $userSalientCount++;

        $saliencyBonus = 0.0;
        if ($userSalient && $filmSalient && $sameSign) {
            $saliencyBonus = min(abs($userAvg), abs($filmAvg)) / 10.0;
        }
        $saliencyTotal += $saliencyBonus;

        $totalContrib = $proximity + $saliencyBonus;
        if ($proximity > 0.2 || $saliencyBonus > 0.05) {
            $contributions[] = [
                'criterio'     => $criterion,
                'contribution' => $totalContrib,
                'user_val'     => $userAvg,
                'film_val'     => $filmAvg,
            ];
        }
    }

    $score = 0.0;
    if ($criteriaCount > 0) {
        $saliencyRatio  = $userSalientCount / $criteriaCount;
        $saliencyWeight = $saliencyRatio * SALIENCY_WEIGHT_MAX;

        $normalizedBase     = $baseTotal / $criteriaCount;
        $normalizedSaliency = $saliencyTotal / $criteriaCount;

        $score = $normalizedBase * (1.0 - $saliencyWeight) + $normalizedSaliency * $saliencyWeight;
    }

    usort($contributions, fn($a, $b) => $b['contribution'] <=> $a['contribution']);

    return [
        'score'   => $score,
        'reasons' => array_slice($contributions, 0, 3),
    ];
}

/**
 * V3 : Calcul de la Stabilité (Inertie)
 * Compare l'ADN historique et l'ADN récent pour ajuster la curiosité.
 */
function calculateUserStability(array $userDna, array $recentDna): float {
    if (empty($recentDna)) return 1.0; // Stable par défaut

    $totalDiff = 0.0;
    $count = 0;

    foreach ($recentDna as $crit => $recentVal) {
        if (isset($userDna[$crit])) {
            $totalDiff += abs($userDna[$crit]['avg'] - $recentVal);
            $count++;
        }
    }

    if ($count === 0) return 1.0;

    $avgDiff = $totalDiff / $count;
    // Plus la différence est grande, plus la stabilité est proche de 0
    return max(0.0, 1.0 - ($avgDiff / STABILITY_THRESHOLD));
}

/**
 * Justification textuelle.
 */
function buildReasonPhrase(array $reasons, bool $isExploration = false): string {
    if ($isExploration) return "Zone d'exploration, au-delà de vos certitudes";
    if (empty($reasons)) return '';

    $labels = [
        'complexite'    => 'la complexité narrative',
        'previsibilite' => 'la prévisibilité du récit',
        'intensite'     => 'la charge émotionnelle',
        'malaise'       => 'la tension et l\'inconfort',
        'stylisation'   => 'la recherche esthétique',
        'dynamique'     => 'l\'intensité du rythme',
        'depaysement'   => 'la soif d\'évasion',
        'coherence'     => 'l\'ancrage dans le réel',
    ];

    $top   = $reasons[0];
    $label = $labels[$top['criterio']] ?? $top['criterio'];

    if ($top['contribution'] > 0) {
        if ($top['user_val'] > 5)      return "Fait écho à votre attrait pour {$label}";
        elseif ($top['user_val'] < -5) return "Concorde avec votre rejet de {$label}";
        else                           return "Résonne avec votre profil sur {$label}";
    }
    return "Signal faible — correspondance partielle sur la {$label}";
}

/**
 * Critères absents ou neutres du DNA utilisateur.
 */
function getUnderrepresentedCriteria(array $userDna): array {
    $all = ['complexite', 'previsibilite', 'intensite', 'malaise', 'stylisation', 'dynamique', 'depaysement', 'coherence'];
    return array_values(array_filter($all, fn($c) => !isset($userDna[$c]) || abs($userDna[$c]['avg']) < 1.5));
}

/**
 * Scoring "Cheval de Troie".
 */
function computeExplorationScore(array $film, array $underrepresented, array $strongestCriteria): float {
    $trojanMultiplier = 0.0;
    foreach ($strongestCriteria as $strongC) {
        if (!isset($film['dna'][$strongC])) continue;
        $d = $film['dna'][$strongC];
        $bayesianVal = ($d['sample_size'] * (float)$d['avg'] + BAYESIAN_M * BAYESIAN_C) / ($d['sample_size'] + BAYESIAN_M);
        if (abs($bayesianVal) > SALIENCY_THRESHOLD) {
            $trojanMultiplier += abs($bayesianVal) / 10.0;
        }
    }

    if ($trojanMultiplier === 0.0) return 0.0;

    $explorationScore = 0.0;
    foreach ($underrepresented as $c) {
        if (!isset($film['dna'][$c])) continue;
        $d = $film['dna'][$c];
        $bayesianVal = ($d['sample_size'] * abs((float)$d['avg'])) / ($d['sample_size'] + BAYESIAN_M);
        $explorationScore += $bayesianVal * $trojanMultiplier;
    }
    return $explorationScore;
}

/**
 * Supprime la FK movie_id → movies.tmdb_id qui bloquerait les movie_id négatifs (séries).
 * Les séries sont stockées avec movie_id = -tmdb_id pour éviter tout changement de PK.
 */
function _ensure_reco_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $pdo = getPDO();

    // Supprime la FK movie_id → movies.tmdb_id (bloque les valeurs négatives pour les séries)
    try {
        $pdo->exec("ALTER TABLE recommendation_feedback DROP FOREIGN KEY recommendation_feedback_ibfk_2");
    } catch (\Throwable $e) {}

    // Aligne toutes les tables impliquées dans les JOINs sur utf8mb4_unicode_ci
    foreach (['recommendation_feedback', 'series_ratings', 'series', 'ratings', 'users'] as $t) {
        try {
            $pdo->exec("ALTER TABLE `{$t}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable $e) {}
    }
}

/**
 * Construit le format userDna [criterio => [avg, var, min, max]]
 * depuis user_dna ou user_series_dna.
 */
function buildDnaFromTable(string $userId, string $table): array {
    $rows = db_fetch_all(
        "SELECT criterio, avg_score, variance, min_score, max_score FROM {$table} WHERE user_id = ?",
        [$userId]
    );
    if (empty($rows)) return [];
    $dna = [];
    foreach ($rows as $row) {
        $dna[$row['criterio']] = [
            'avg' => (float)$row['avg_score'],
            'var' => (float)$row['variance'],
            'min' => (float)$row['min_score'],
            'max' => (float)$row['max_score'],
        ];
    }
    return $dna;
}

/**
 * Point d'entrée séries — retourne les recos persistées ou en calcule de nouvelles.
 */
function getSeriesRecommendations(string $userId, array $userDna, int $limit = RECO_LIMIT): array {
    _ensure_reco_schema();
    if (empty($userDna)) return [];

    $activeRecos = db_fetch_all(
        "SELECT rf.movie_id, rf.score_at_reco, rf.is_exploration, rf.slot_position, rf.reason_phrase,
                s.title, s.poster, s.year
         FROM recommendation_feedback rf
         JOIN series s ON s.tmdb_id = -rf.movie_id
         WHERE rf.user_id = ? AND rf.movie_id < 0 AND rf.is_active = 1 AND rf.outcome = 'pending'
         ORDER BY rf.slot_position ASC",
        [$userId]
    );

    if (!empty($activeRecos)) {
        return array_map(fn($r) => [
            'id'             => (int)abs($r['movie_id']),
            'title'          => $r['title'],
            'poster'         => $r['poster'],
            'year'           => $r['year'],
            'score'          => (float)$r['score_at_reco'],
            'is_exploration' => (bool)$r['is_exploration'],
            'slot_position'  => (int)$r['slot_position'],
            'reason'         => $r['reason_phrase'] ?? '',
        ], $activeRecos);
    }

    return _computeAndPersistSeriesRecommendations($userId, $limit, $userDna);
}

/**
 * Calcul V3 séries : même algorithme epsilon-greedy que les films.
 */
function _computeAndPersistSeriesRecommendations(string $userId, int $limit, array $userDna, array $extraExcludeIds = [], int $slotOffset = 0): array {
    // Epsilon dynamique (exploration_rate utilisateur + miss feedback séries)
    $user        = db_fetch_one('SELECT exploration_rate FROM users WHERE id = ?', [$userId]);
    $baseEpsilon = (float)($user['exploration_rate'] ?? 0.15);

    $missRows = db_fetch_all(
        "SELECT rf.score_at_reco, sr.like_score
         FROM recommendation_feedback rf
         JOIN series s  ON s.tmdb_id = -rf.movie_id
         JOIN series_ratings sr ON sr.series_id = s.id AND sr.user_id = rf.user_id
         WHERE rf.user_id = ? AND rf.movie_id < 0
           AND rf.outcome = 'rated' AND rf.score_at_reco > 0.7
         ORDER BY rf.recommended_at DESC LIMIT 20",
        [$userId]
    );
    $missBoost = 0.0;
    if (!empty($missRows)) {
        $misses    = array_filter($missRows, fn($m) => (int)$m['like_score'] < 0);
        $missBoost = (count($misses) / count($missRows)) * 0.15;
    }
    $dynamicEpsilon = min(0.5, $baseEpsilon + $missBoost);

    // Séries déjà notées + skippées → exclure
    $ratedIds   = array_column(db_fetch_all('SELECT series_id FROM series_ratings WHERE user_id = ?', [$userId]), 'series_id') ?: [0];
    $skippedIds = array_map('abs', array_column(db_fetch_all(
        "SELECT movie_id FROM recommendation_feedback WHERE user_id = ? AND movie_id < 0 AND outcome = 'skipped'",
        [$userId]
    ), 'movie_id')) ?: [];

    $excl = implode(',', array_fill(0, count($ratedIds), '?'));
    $rows = db_fetch_all(
        "SELECT s.tmdb_id, s.title, s.poster, s.year, s.polarization_index, sr.scores, sr.rating_weight
         FROM series s
         JOIN series_ratings sr ON sr.series_id = s.id
         WHERE s.id NOT IN ({$excl}) AND sr.scores IS NOT NULL AND s.total_votes > 0",
        $ratedIds
    );
    if (empty($rows)) return [];

    // Agréger l'ADN par série (weighted average sur les votes)
    $map = [];
    foreach ($rows as $row) {
        $id = (int)$row['tmdb_id'];
        if (in_array($id, $skippedIds) || in_array($id, $extraExcludeIds)) continue;
        if (!isset($map[$id])) {
            $map[$id] = [
                'title'        => $row['title'],   'poster'  => $row['poster'],
                'year'         => $row['year'],    'polarization' => (float)$row['polarization_index'],
                'sums'         => array_fill_keys(CRITERIA, 0.0),
                'wsum'         => array_fill_keys(CRITERIA, 0.0),
            ];
        }
        $scores = json_decode($row['scores'], true);
        if (!is_array($scores)) continue;
        $w = max(0.01, (float)$row['rating_weight']);
        foreach (CRITERIA as $c) {
            if (isset($scores[$c]) && $scores[$c] !== null) {
                $map[$id]['sums'][$c] += (float)$scores[$c] * $w;
                $map[$id]['wsum'][$c] += $w;
            }
        }
    }

    $seriesPool = [];
    foreach ($map as $tmdbId => $s) {
        $dna = [];
        foreach (CRITERIA as $c) {
            if ($s['wsum'][$c] > 0) {
                $dna[$c] = ['avg' => $s['sums'][$c] / $s['wsum'][$c], 'sample_size' => $s['wsum'][$c]];
            }
        }
        if (count($dna) < 4) continue;
        $seriesPool[] = ['id' => $tmdbId, 'title' => $s['title'], 'poster' => $s['poster'],
                         'year' => $s['year'], 'polarization' => $s['polarization'], 'dna' => $dna];
    }
    if (empty($seriesPool)) return [];

    // Critères sous-représentés dans l'ADN utilisateur
    $underrepresented = getUnderrepresentedCriteria($userDna);
    if (empty($underrepresented)) {
        $leastRows = db_fetch_all(
            'SELECT criterio FROM user_series_dna WHERE user_id = ? ORDER BY vote_count ASC LIMIT 3',
            [$userId]
        );
        $underrepresented = array_column($leastRows, 'criterio') ?: getUnderrepresentedCriteria([]);
    }
    $absUserDna = [];
    foreach ($userDna as $c => $d) $absUserDna[$c] = abs($d['avg']);
    arsort($absUserDna);
    $strongestCriteria = array_slice(array_keys($absUserDna), 0, 2);

    // Scoring
    $candidates = [];
    foreach ($seriesPool as $s) {
        $result       = calculateSimilarityWithReasons($userDna, $s['dna']);
        $polarNorm    = min(1.0, $s['polarization'] / 20.0);
        $finalScore   = $result['score'] * (1.0 - $polarNorm * (1.0 - $dynamicEpsilon));
        $candidates[] = [
            'id'                => $s['id'],    'title'          => $s['title'],
            'poster'            => $s['poster'], 'year'           => $s['year'],
            'score'             => $finalScore,
            'exploration_score' => computeExplorationScore($s, $underrepresented, $strongestCriteria),
            'is_exploration'    => false,
            'raw_reasons'       => $result['reasons'],
        ];
    }
    usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

    // Sélection main + exploration (même logique que films)
    $explorationSlots = max(1, (int)round($limit * $dynamicEpsilon));
    $mainSlots  = $limit - $explorationSlots;
    $mainRecos  = array_slice($candidates, 0, $mainSlots);
    $usedIds    = array_column($mainRecos, 'id');

    $explorationCandidates = array_values(array_filter($candidates, fn($c) => !in_array($c['id'], $usedIds) && $c['exploration_score'] > 0));
    usort($explorationCandidates, fn($a, $b) => $b['exploration_score'] <=> $a['exploration_score']);
    $explorationRecos = array_slice($explorationCandidates, 0, $explorationSlots);
    foreach ($explorationRecos as &$e) { $e['is_exploration'] = true; }

    if (count($explorationRecos) < $explorationSlots) {
        $usedIdsExplo = array_merge($usedIds, array_column($explorationRecos, 'id'));
        $fallback     = array_values(array_filter($candidates, fn($c) => !in_array($c['id'], $usedIdsExplo)));
        foreach (array_slice($fallback, 0, $explorationSlots - count($explorationRecos)) as $f) {
            $f['is_exploration'] = true;
            $explorationRecos[]  = $f;
        }
    }

    $final = $mainRecos;
    if (!empty($explorationRecos)) {
        $step = max(1, (int)floor(($limit - 1) / count($explorationRecos)));
        foreach ($explorationRecos as $i => $e) {
            array_splice($final, min(1 + $i * $step, count($final)), 0, [$e]);
        }
    }
    $final = array_slice($final, 0, $limit);

    // Anti-fatigue sémantique
    $reasonTracker = [];
    foreach ($final as &$reco) {
        if ($reco['is_exploration']) { $reco['reason'] = buildReasonPhrase([], true); continue; }
        $topReason = $reco['raw_reasons'][0] ?? null;
        if (!$topReason) { $reco['reason'] = ''; continue; }
        $selectedReason = $topReason;
        foreach ($reco['raw_reasons'] as $r) {
            if ($r['contribution'] >= ($topReason['contribution'] * 0.8)) {
                if (($reasonTracker[$r['criterio']] ?? 0) < ($reasonTracker[$selectedReason['criterio']] ?? 0)) {
                    $selectedReason = $r;
                }
            }
        }
        $reasonTracker[$selectedReason['criterio']] = ($reasonTracker[$selectedReason['criterio']] ?? 0) + 1;
        $reco['reason'] = buildReasonPhrase([$selectedReason], false);
        unset($reco['raw_reasons']);
    }
    unset($reco);

    // Persistance dans recommendation_feedback (movie_id négatif = série)
    // En mode top-up ($extraExcludeIds non vide), on ne touche pas aux pending existants
    if (empty($extraExcludeIds)) {
        db_execute("UPDATE recommendation_feedback SET is_active = 0 WHERE user_id = ? AND movie_id < 0", [$userId]);
    }
    foreach ($final as $pos => $reco) {
        db_execute(
            "INSERT INTO recommendation_feedback
                 (user_id, movie_id, recommended_at, score_at_reco, is_exploration, slot_position, is_active, outcome, reason_phrase)
             VALUES (?, ?, NOW(), ?, ?, ?, 1, 'pending', ?)
             ON DUPLICATE KEY UPDATE
                 recommended_at = NOW(), score_at_reco = VALUES(score_at_reco),
                 is_exploration = VALUES(is_exploration), slot_position = VALUES(slot_position),
                 is_active = 1, outcome = 'pending', outcome_at = NULL,
                 reason_phrase = VALUES(reason_phrase)",
            [$userId, -(int)$reco['id'], (float)$reco['score'], $reco['is_exploration'] ? 1 : 0, $slotOffset + $pos, $reco['reason']]
        );
    }

    return $final;
}

function getRecommendations(string $userId, int $limit = RECO_LIMIT, ?array $overrideDna = null): array {
    _ensure_reco_schema();

    $activeRecos = db_fetch_all(
        "SELECT rf.movie_id, rf.score_at_reco, rf.is_exploration, rf.slot_position, rf.reason_phrase,
                m.title, m.poster, m.year
         FROM recommendation_feedback rf
         JOIN movies m ON m.tmdb_id = rf.movie_id
         WHERE rf.user_id = ? AND rf.movie_id > 0 AND rf.is_active = 1 AND rf.outcome = 'pending'
         ORDER BY rf.slot_position ASC",
        [$userId]
    );

    if (!empty($activeRecos) && $overrideDna === null) {
        return array_map(fn($r) => [
            'id'             => $r['movie_id'],
            'title'          => $r['title'],
            'poster'         => $r['poster'],
            'score'          => (float)$r['score_at_reco'],
            'is_exploration' => (bool)$r['is_exploration'],
            'slot_position'  => (int)$r['slot_position'],
            'reason'         => $r['reason_phrase'] ?? '',
        ], $activeRecos);
    }

    return _computeAndPersistRecommendations($userId, $limit, $overrideDna);
}

/**
 * Recalcul V3 : Inertie & Variance.
 */
function _computeAndPersistRecommendations(string $userId, int $limit, ?array $overrideDna = null, array $extraExcludeIds = [], int $slotOffset = 0): array {
    // 1. Chargement de l'ADN Global (avec Variance) — ou utilisation de l'ADN injecté
    $userDna = $overrideDna;
    if ($userDna === null) {
        $dnaRows = db_fetch_all('SELECT criterio, avg_score, variance, min_score, max_score FROM user_dna WHERE user_id = ?', [$userId]);
        if (empty($dnaRows)) return [];
        $userDna = [];
        foreach ($dnaRows as $row) {
            $userDna[$row['criterio']] = [
                'avg' => (float)$row['avg_score'], 'var' => (float)$row['variance'],
                'min' => (float)$row['min_score'], 'max' => (float)$row['max_score'],
            ];
        }
    }
    if (empty($userDna)) return [];

    // 2. Chargement de l'ADN Récent (pour l'Inertie)
    $recentRows = db_fetch_all('SELECT criterio, recent_avg FROM user_dna_recent WHERE user_id = ?', [$userId]);
    $recentDna = array_column($recentRows, 'recent_avg', 'criterio');

    // 3. Calcul de l'Epsilon Dynamique (Momentum + Feedback Loop)
    $user = db_fetch_one('SELECT exploration_rate FROM users WHERE id = ?', [$userId]);
    $baseEpsilon = (float)($user['exploration_rate'] ?? 0.15);
    $stability   = calculateUserStability($userDna, $recentDna);

    // ── Feedback Loop ─────────────────────────────────────────────────────────
    // Si l'algo a recommandé avec confiance (score > 0.7) mais que l'utilisateur
    // a mal noté le film (like_score < 0), c'est un "miss".
    // Un taux de miss élevé = l'algo se plante = on booste l'exploration.
    $missRows = db_fetch_all(
        "SELECT rf.score_at_reco, r.like_score
         FROM recommendation_feedback rf
         JOIN ratings r ON r.movie_id = rf.movie_id AND r.user_id = rf.user_id
         WHERE rf.user_id = ? AND rf.outcome = 'rated' AND rf.score_at_reco > 0.7
         ORDER BY rf.recommended_at DESC LIMIT 20",
        [$userId]
    );

    $missBoost = 0.0;
    if (!empty($missRows)) {
        $misses   = array_filter($missRows, fn($m) => (int)$m['like_score'] < 0);
        $missRate = count($misses) / count($missRows);
        // Jusqu'à +15% d'exploration si l'algo se plante systématiquement
        $missBoost = $missRate * 0.15;
    }
    // ─────────────────────────────────────────────────────────────────────────

    // Si stabilité = 0, on booste l'exploration
    $dynamicEpsilon = $baseEpsilon + ((1.0 - $stability) * EPSILON_BOOST_MAX) + $missBoost;
    $dynamicEpsilon = min(0.5, $dynamicEpsilon);

    // 4. Filtrage des films exclus
    $seenIds = array_column(db_fetch_all('SELECT movie_id FROM ratings WHERE user_id = ?', [$userId]), 'movie_id');
    $skippedIds = array_column(db_fetch_all("SELECT movie_id FROM recommendation_feedback WHERE user_id=? AND outcome='skipped'", [$userId]), 'movie_id');
    $excludedIds = array_unique(array_merge($seenIds, $skippedIds, $extraExcludeIds));
    $placeholder = empty($excludedIds) ? '(SELECT 0)' : '(' . implode(',', array_fill(0, count($excludedIds), '?')) . ')';

    $movieRows = db_fetch_all(
        "SELECT m.tmdb_id, m.title, m.poster, m.year, m.polarization_index, md.criterio, md.avg_score, md.sample_size
         FROM movies m
         JOIN movie_dna md ON md.movie_id = m.tmdb_id
         WHERE m.tmdb_id NOT IN $placeholder AND m.total_votes > 0",
        $excludedIds
    );
    if (empty($movieRows)) return [];

    $films = [];
    foreach ($movieRows as $row) {
        $id = $row['tmdb_id'];
        if (!isset($films[$id])) {
            $films[$id] = [
                'id'          => $id,
                'title'       => $row['title'],
                'poster'      => $row['poster'],
                'year'        => $row['year'],
                'polarization'=> (float)$row['polarization_index'],
                'dna'         => [],
            ];
        }
        $films[$id]['dna'][$row['criterio']] = ['avg' => (float)$row['avg_score'], 'sample_size' => (int)$row['sample_size']];
    }

    $underrepresented = getUnderrepresentedCriteria($userDna);

    // Fallback : si tous les critères sont marqués (utilisateur très actif),
    // on prend les 3 critères avec le moins de votes — les moins solides statistiquement.
    if (empty($underrepresented)) {
        $leastVotedRows = db_fetch_all(
            'SELECT criterio FROM user_dna WHERE user_id = ? ORDER BY vote_count ASC LIMIT 3',
            [$userId]
        );
        $underrepresented = array_column($leastVotedRows, 'criterio');
    }

    $absUserDna = [];
    foreach($userDna as $c => $d) { $absUserDna[$c] = abs($d['avg']); }
    arsort($absUserDna);
    $strongestCriteria = array_slice(array_keys($absUserDna), 0, 2);

    $candidates = [];
    foreach ($films as $film) {
        $result = calculateSimilarityWithReasons($userDna, $film['dna']);

        // ── Pénalité Polarisation (Option B/C) ───────────────────────────────
        // Film polarisant + profil stable → score chute fortement
        // Film polarisant + profil explorateur → quasi pas pénalisé
        $polarNorm    = min(1.0, $film['polarization'] / 20.0);
        $polarPenalty = $polarNorm * (1.0 - $dynamicEpsilon);
        $finalScore   = $result['score'] * (1.0 - $polarPenalty);
        // ─────────────────────────────────────────────────────────────────────

        $candidates[] = [
            'id'               => $film['id'],
            'title'            => $film['title'],
            'poster'           => $film['poster'],
            'score'            => $finalScore,
            'exploration_score'=> computeExplorationScore($film, $underrepresented, $strongestCriteria),
            'is_exploration'   => false,
            'raw_reasons'      => $result['reasons'],
        ];
    }

    usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

    // 5. Sélection et insertion Exploration
    $explorationSlots = max(1, (int)round($limit * $dynamicEpsilon));
    $mainSlots = $limit - $explorationSlots;
    $mainRecos = array_slice($candidates, 0, $mainSlots);
    $usedIds = array_column($mainRecos, 'id');

    $explorationCandidates = array_values(array_filter($candidates, fn($c) => !in_array($c['id'], $usedIds) && $c['exploration_score'] > 0));
    usort($explorationCandidates, fn($a, $b) => $b['exploration_score'] <=> $a['exploration_score']);

    $explorationRecos = array_slice($explorationCandidates, 0, $explorationSlots);
    foreach($explorationRecos as &$e) { $e['is_exploration'] = true; }

    // Fallback Exploration si nécessaire
    if (count($explorationRecos) < $explorationSlots) {
        $usedIdsExplo = array_merge($usedIds, array_column($explorationRecos, 'id'));
        $fallback = array_values(array_filter($candidates, fn($c) => !in_array($c['id'], $usedIdsExplo)));
        $missingCount = $explorationSlots - count($explorationRecos);
        foreach (array_slice($fallback, 0, $missingCount) as $f) {
            $f['is_exploration'] = true;
            $explorationRecos[] = $f;
        }
    }

    $final = $mainRecos;
    if (!empty($explorationRecos)) {
        $step = max(1, (int)floor(($limit - 1) / count($explorationRecos)));
        foreach ($explorationRecos as $i => $e) {
            array_splice($final, min(1 + $i * $step, count($final)), 0, [$e]);
        }
    }
    $final = array_slice($final, 0, $limit);

    // 6. Anti-fatigue sémantique
    $reasonTracker = [];
    foreach ($final as &$reco) {
        if ($reco['is_exploration']) {
            $reco['reason'] = buildReasonPhrase([], true);
            continue;
        }

        $topReason = $reco['raw_reasons'][0] ?? null;
        if (!$topReason) { $reco['reason'] = ''; continue; }

        $selectedReason = $topReason;
        foreach ($reco['raw_reasons'] as $r) {
            if ($r['contribution'] >= ($topReason['contribution'] * 0.8)) {
                $countCurrent = $reasonTracker[$selectedReason['criterio']] ?? 0;
                $countCandidate = $reasonTracker[$r['criterio']] ?? 0;
                if ($countCandidate < $countCurrent) { $selectedReason = $r; }
            }
        }

        $reasonTracker[$selectedReason['criterio']] = ($reasonTracker[$selectedReason['criterio']] ?? 0) + 1;
        $reco['reason'] = buildReasonPhrase([$selectedReason], false);
        unset($reco['raw_reasons']);
    }

    // 7. Persistance (movie_id positif = film)
    // En mode top-up ($extraExcludeIds non vide), on ne touche pas aux pending existants
    if (empty($extraExcludeIds)) {
        db_execute("UPDATE recommendation_feedback SET is_active = 0 WHERE user_id = ? AND movie_id > 0", [$userId]);
    }
    foreach ($final as $pos => $reco) {
        db_execute(
            "INSERT INTO recommendation_feedback
                 (user_id, movie_id, recommended_at, score_at_reco, is_exploration, slot_position, is_active, outcome, reason_phrase)
             VALUES (?, ?, NOW(), ?, ?, ?, 1, 'pending', ?)
             ON DUPLICATE KEY UPDATE
                 recommended_at = NOW(), score_at_reco = VALUES(score_at_reco),
                 is_exploration = VALUES(is_exploration), slot_position = VALUES(slot_position),
                 is_active = 1, outcome = 'pending', outcome_at = NULL,
                 reason_phrase = VALUES(reason_phrase)",
            [$userId, $reco['id'], (float)$reco['score'], $reco['is_exploration'] ? 1 : 0, $slotOffset + $pos, $reco['reason']]
        );
    }

    return $final;
}