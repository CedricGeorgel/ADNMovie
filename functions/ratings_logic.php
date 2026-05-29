<?php
/**
 * RATINGS_LOGIC.PHP - V3 HYBRIDE
 * Restaure la compatibilité totale avec l'interface et intègre la Variance/Momentum.
 */

require_once __DIR__ . '/../functions/core_db.php';

const CRITERIA = ['complexite', 'previsibilite', 'intensite', 'malaise', 'stylisation', 'dynamique', 'depaysement', 'coherence'];

const TEMPORAL_LAMBDA = 0.01;

// ─────────────────────────────────────────────────────────────────────────────
// FILM
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Récupère les stats d'un film (Moyennes, Variance, Polarisation)
 */
function get_movie_stats($movieId): array {
    $stats = [
        'averages'        => array_fill(0, 8, 0),
        'count'           => 0,
        'user_note'       => null,
        'user_note_assoc' => null,
        'polarization'    => 0,
    ];

    $movie = db_fetch_one(
        'SELECT total_votes, polarization_index FROM movies WHERE tmdb_id = ?',
        [$movieId]
    );

    if ($movie) {
        $stats['count']       = $movie['total_votes'];
        $stats['polarization']= $movie['polarization_index'];
    }

    $dnaRows = db_fetch_all(
        'SELECT criterio, avg_score FROM movie_dna WHERE movie_id = ?',
        [$movieId]
    );

    $dnaMap = array_column($dnaRows, 'avg_score', 'criterio');
    foreach (CRITERIA as $index => $c) {
        $stats['averages'][$index] = $dnaMap[$c] ?? 0;
    }

    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $rating = db_fetch_one(
            'SELECT scores FROM ratings WHERE user_id = ? AND movie_id = ?',
            [$userId, $movieId]
        );

        if ($rating) {
            $scores = json_decode($rating['scores'], true);
            $stats['user_note_assoc'] = $scores;
            $stats['user_note'] = [];
            foreach (CRITERIA as $c) {
                $stats['user_note'][] = $scores[$c] ?? 0;
            }
        }
    }

    return $stats;
}

/**
 * V3 : Mise à jour de l'ADN du FILM
 */
function update_movie_dna($movieId) {
    $ratings = db_fetch_all(
        "SELECT scores FROM ratings WHERE movie_id = ? AND scores IS NOT NULL",
        [$movieId]
    );
    if (empty($ratings)) return false;

    $stats = [];
    foreach ($ratings as $r) {
        $data = json_decode($r['scores'], true);
        if ($data) {
            foreach (CRITERIA as $c) {
                if (isset($data[$c]) && $data[$c] !== null) {
                    $stats[$c][] = (float) $data[$c];
                }
            }
        }
    }

    foreach (CRITERIA as $c) {
        if (!isset($stats[$c])) continue;
        $vals = $stats[$c];
        $n    = count($vals);
        $avg  = array_sum($vals) / $n;
        $sumSq = 0;
        foreach ($vals as $v) $sumSq += pow($v - $avg, 2);
        $variance = $sumSq / $n;

        db_execute(
            "INSERT INTO movie_dna (movie_id, criterio, avg_score, variance, sample_size)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
             avg_score = VALUES(avg_score), variance = VALUES(variance), sample_size = VALUES(sample_size)",
            [$movieId, $c, $avg, $variance, $n]
        );
    }
    return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// UTILISATEUR
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Récupère le profil ADN série de l'utilisateur (user_series_dna).
 * Même structure de retour que get_user_adn().
 */
function get_user_series_adn(string $userId): array {
    $rows = db_fetch_all(
        'SELECT criterio, avg_score, strength, vote_count, variance, min_score, max_score
         FROM user_series_dna WHERE user_id = ?',
        [$userId]
    );
    if (empty($rows)) return ['scores' => array_fill(0, 8, 0), 'count' => 0, 'stats' => []];

    $dnaMap = [];
    $stats  = [];
    foreach ($rows as $row) {
        $dnaMap[$row['criterio']] = $row;
        $stats[$row['criterio']]  = [
            'avg' => (float)$row['avg_score'],
            'min' => (float)$row['min_score'],
            'max' => (float)$row['max_score'],
        ];
    }

    $scores = [];
    foreach (CRITERIA as $c) {
        $scores[] = $dnaMap[$c]['avg_score'] ?? 0;
    }

    return [
        'scores' => $scores,
        'count'  => max(array_column($rows, 'vote_count')),
        'stats'  => $stats,
    ];
}

/**
 * Récupère le profil ADN de l'utilisateur.
 * Retourne scores, variances, stats (min/avg/max par critère), meta.
 */
function get_user_adn($userId): array {
    $rows = db_fetch_all(
        'SELECT criterio, avg_score, strength, vote_count, variance, min_score, max_score
         FROM user_dna
         WHERE user_id = ?',
        [$userId]
    );

    if (empty($rows)) {
        return ['scores' => array_fill(0, 8, 0), 'count' => 0, 'meta' => [], 'variances' => [], 'stats' => []];
    }

    $dnaMap    = [];
    $variances = [];
    $stats     = [];

    foreach ($rows as $row) {
        $dnaMap[$row['criterio']] = $row;
        $variances[$row['criterio']] = $row['variance'];
        $stats[$row['criterio']] = [
            'avg' => (float) $row['avg_score'],
            'min' => (float) $row['min_score'],
            'max' => (float) $row['max_score'],
        ];
    }

    $scores = [];
    foreach (CRITERIA as $c) {
        $scores[] = $dnaMap[$c]['avg_score'] ?? 0;
    }

    $totalRated = max(array_column($rows, 'vote_count'));

    $primaryDna   = [];
    $secondaryDna = [];
    foreach ($dnaMap as $criterio => $row) {
        if ($row['strength'] === 'primary') {
            $primaryDna[$criterio] = $row['avg_score'];
        } else {
            $secondaryDna[$criterio] = $row['avg_score'];
        }
    }

    return [
        'scores'    => $scores,
        'count'     => $totalRated,
        'variances' => $variances,
        'stats'     => $stats,
        'meta'      => [
            'primary_dna'   => $primaryDna,
            'secondary_dna' => $secondaryDna,
            'total_rated'   => $totalRated,
        ],
    ];
}

/**
 * V3 : Mise à jour de l'ADN UTILISATEUR avec décroissance temporelle.
 * Formule : poids = e^(-TEMPORAL_LAMBDA * jours_écoulés)
 */
function update_user_dna($userId) {
    $ratings = db_fetch_all(
        "SELECT scores, rated_at FROM ratings WHERE user_id = ? AND scores IS NOT NULL",
        [$userId]
    );
    if (empty($ratings)) return false;

    $stats = [];
    $now   = time();

    foreach ($ratings as $r) {
        $data = json_decode($r['scores'], true);
        if (!$data) continue;

        $daysSince = ($now - strtotime($r['rated_at'])) / 86400;
        $weight    = exp(-TEMPORAL_LAMBDA * $daysSince);

        foreach (CRITERIA as $c) {
            if (isset($data[$c]) && $data[$c] !== null) {
                $stats[$c][] = ['val' => (float) $data[$c], 'w' => $weight];
            }
        }
    }

    foreach (CRITERIA as $c) {
        if (!isset($stats[$c])) continue;

        $entries     = $stats[$c];
        $n           = count($entries);
        $totalWeight = array_sum(array_column($entries, 'w'));

        $avg = 0.0;
        foreach ($entries as $e) { $avg += $e['val'] * $e['w']; }
        $avg /= $totalWeight;

        $sumSq = 0.0;
        foreach ($entries as $e) { $sumSq += $e['w'] * pow($e['val'] - $avg, 2); }
        $variance = $sumSq / $totalWeight;

        $allVals  = array_column($entries, 'val');
        $strength = abs($avg) >= 4 ? 'primary' : 'secondary';

        db_execute(
            "INSERT INTO user_dna (user_id, criterio, avg_score, strength, vote_count, variance, min_score, max_score, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
             avg_score = VALUES(avg_score), strength = VALUES(strength), vote_count = VALUES(vote_count),
             variance = VALUES(variance), min_score = VALUES(min_score), max_score = VALUES(max_score), updated_at = NOW()",
            [$userId, $c, $avg, $strength, $n, $variance, min($allVals), max($allVals)]
        );
    }

    // Momentum (10 derniers votes)
    $recent = db_fetch_all(
        "SELECT scores FROM ratings WHERE user_id = ? ORDER BY rated_at DESC LIMIT 10",
        [$userId]
    );
    $recent_data = [];
    foreach ($recent as $r) {
        $data = json_decode($r['scores'], true);
        if ($data) {
            foreach (CRITERIA as $c) {
                if (isset($data[$c]) && $data[$c] !== null) {
                    $recent_data[$c][] = (float) $data[$c];
                }
            }
        }
    }

    foreach (CRITERIA as $c) {
        if (!empty($recent_data[$c])) {
            $recent_avg = array_sum($recent_data[$c]) / count($recent_data[$c]);
            db_execute(
                "INSERT INTO user_dna_recent (user_id, criterio, recent_avg, sample_size)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE recent_avg = VALUES(recent_avg), sample_size = VALUES(sample_size)",
                [$userId, $c, $recent_avg, count($recent_data[$c])]
            );
        }
    }
    return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// RATINGS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Récupère toutes les notes d'un utilisateur.
 */
function get_user_ratings($userId): array {
    $rows = db_fetch_all(
        'SELECT movie_id, scores, like_score, is_liked, rating_weight, context_period, context_slot, rated_at
         FROM ratings WHERE user_id = ?',
        [$userId]
    );
    $result = [];
    foreach ($rows as $row) {
        $row['scores'] = json_decode($row['scores'], true);
        $result[$row['movie_id']] = $row;
    }
    return $result;
}

// ─────────────────────────────────────────────────────────────────────────────
// SÉRIE & SAISON
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Récupère les stats d'une série à partir de son tmdb_id.
 * Même structure que get_movie_stats() pour être compatible avec renderRadarChart().
 */
function get_series_stats_by_tmdb(int $tmdbId): array {
    $stats = [
        'averages'        => array_fill(0, 8, 0),
        'count'           => 0,
        'user_note'       => null,
        'user_note_assoc' => null,
        'polarization'    => 0,
    ];

    $series = db_fetch_one(
        'SELECT id, total_votes, polarization_index FROM series WHERE tmdb_id = ?',
        [$tmdbId]
    );
    if (!$series) return $stats;

    $seriesId              = (int)$series['id'];
    $stats['count']        = (int)$series['total_votes'];
    $stats['polarization'] = (float)$series['polarization_index'];

    // Calcul des moyennes à la volée depuis series_ratings
    $ratings = db_fetch_all(
        'SELECT scores FROM series_ratings WHERE series_id = ? AND scores IS NOT NULL',
        [$seriesId]
    );

    if (!empty($ratings)) {
        $sums = array_fill_keys(CRITERIA, 0.0);
        $cnts = array_fill_keys(CRITERIA, 0);
        foreach ($ratings as $r) {
            $data = json_decode($r['scores'], true);
            if (!is_array($data)) continue;
            foreach (CRITERIA as $c) {
                if (isset($data[$c]) && $data[$c] !== null) {
                    $sums[$c] += (float)$data[$c];
                    $cnts[$c]++;
                }
            }
        }
        foreach (CRITERIA as $i => $c) {
            $stats['averages'][$i] = $cnts[$c] > 0 ? $sums[$c] / $cnts[$c] : 0;
        }
    }

    // Note de l'utilisateur connecté
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $rating = db_fetch_one(
            'SELECT scores FROM series_ratings WHERE user_id = ? AND series_id = ?',
            [$userId, $seriesId]
        );
        if ($rating) {
            $scores = json_decode($rating['scores'], true);
            $stats['user_note_assoc'] = $scores;
            $stats['user_note'] = [];
            foreach (CRITERIA as $c) {
                $stats['user_note'][] = $scores[$c] ?? 0;
            }
        }
    }

    return $stats;
}

/**
 * Récupère les stats d'une saison spécifique.
 * Indispensable pour l'affichage de la fiche saison (radar chart).
 */
function get_season_stats(int $seriesId, int $seasonNumber): array {
    $stats = [
        'averages'        => array_fill(0, 8, 0),
        'count'           => 0,
        'polarization'    => 0,
    ];

    $ratings = db_fetch_all(
        'SELECT scores, rating_weight, like_score FROM season_ratings WHERE series_id = ? AND season_number = ? AND scores IS NOT NULL',
        [$seriesId, $seasonNumber]
    );

    if (empty($ratings)) {
        return $stats;
    }

    $stats['count'] = count($ratings);

    $sums = array_fill_keys(CRITERIA, 0.0);
    $cnts = array_fill_keys(CRITERIA, 0);
    $likes = [];

    foreach ($ratings as $r) {
        $data = json_decode($r['scores'], true);
        if (!is_array($data)) continue;

        $w = (float)($r['rating_weight'] ?? 1.0);
        if ($w <= 0) $w = 1.0;

        foreach (CRITERIA as $c) {
            if (isset($data[$c]) && $data[$c] !== null) {
                $sums[$c] += (float)$data[$c] * $w;
                $cnts[$c] += $w;
            }
        }
        if ($r['like_score'] !== null) {
            $likes[] = (int)$r['like_score'];
        }
    }

    foreach (CRITERIA as $i => $c) {
        $stats['averages'][$i] = $cnts[$c] > 0 ? round($sums[$c] / $cnts[$c], 1) : 0;
    }

    if (count($likes) > 1) {
        $meanLike = array_sum($likes) / count($likes);
        $varSum = 0;
        foreach ($likes as $l) {
            $varSum += pow($l - $meanLike, 2);
        }
        $stats['polarization'] = round(sqrt($varSum / count($likes)), 2);
    }

    return $stats;
}

function get_episode_stats(int $seriesId, int $seasonNumber, int $episodeNumber): array {
    $stats = ['averages' => array_fill(0, 8, 0), 'count' => 0, 'polarization' => 0];

    $ratings = db_fetch_all(
        'SELECT scores, like_score FROM episode_ratings
         WHERE series_id = ? AND season_number = ? AND episode_number = ? AND scores IS NOT NULL',
        [$seriesId, $seasonNumber, $episodeNumber]
    );

    if (empty($ratings)) return $stats;

    $stats['count'] = count($ratings);
    $sums = array_fill_keys(CRITERIA, 0.0);
    $cnts = array_fill_keys(CRITERIA, 0);
    $likes = [];

    foreach ($ratings as $r) {
        $data = json_decode($r['scores'], true);
        if (!is_array($data)) continue;
        foreach (CRITERIA as $c) {
            if (isset($data[$c]) && $data[$c] !== null) {
                $sums[$c] += (float)$data[$c];
                $cnts[$c]++;
            }
        }
        if ($r['like_score'] !== null) $likes[] = (int)$r['like_score'];
    }

    foreach (CRITERIA as $i => $c) {
        $stats['averages'][$i] = $cnts[$c] > 0 ? round($sums[$c] / $cnts[$c], 1) : 0;
    }

    if (count($likes) > 1) {
        $mean = array_sum($likes) / count($likes);
        $varSum = array_sum(array_map(fn($l) => pow($l - $mean, 2), $likes));
        $stats['polarization'] = round(sqrt($varSum / count($likes)), 2);
    }

    return $stats;
}

/**
 * Met à jour l'ADN série d'un utilisateur (user_series_dna).
 * Miroir de update_user_dna() mais pour les séries.
 */
function update_user_series_dna(string $userId): void {
    $ratings = db_fetch_all(
        'SELECT scores, rating_weight FROM series_ratings WHERE user_id = ? AND scores IS NOT NULL',
        [$userId]
    );
    if (empty($ratings)) return;

    $vals_by_crit = array_fill_keys(CRITERIA, []);
    $w_by_crit    = array_fill_keys(CRITERIA, []);

    foreach ($ratings as $r) {
        $data = json_decode($r['scores'], true);
        if (!is_array($data)) continue;
        $w = (float)($r['rating_weight'] ?? 1.0);
        foreach (CRITERIA as $c) {
            if (isset($data[$c]) && $data[$c] !== null) {
                $vals_by_crit[$c][] = (float)$data[$c];
                $w_by_crit[$c][]    = $w;
            }
        }
    }

    foreach (CRITERIA as $c) {
        $vals = $vals_by_crit[$c];
        $ws   = $w_by_crit[$c];
        $n    = count($vals);
        if ($n === 0) continue;

        $wSum = array_sum($ws);
        $avg  = array_sum(array_map(fn($v, $w) => $v * $w, $vals, $ws)) / $wSum;
        $mean = array_sum($vals) / $n;
        $variance = $n > 1
            ? array_sum(array_map(fn($v) => ($v - $mean) ** 2, $vals)) / $n
            : 0.0;
        $strength = abs($avg) >= 5 ? 'primary' : 'secondary';

        db_execute(
            'INSERT INTO user_series_dna
                 (user_id, criterio, avg_score, strength, vote_count, variance, min_score, max_score)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 avg_score  = VALUES(avg_score),  strength   = VALUES(strength),
                 vote_count = VALUES(vote_count),  variance   = VALUES(variance),
                 min_score  = VALUES(min_score),   max_score  = VALUES(max_score)',
            [$userId, $c, round($avg, 4), $strength, $n, round($variance, 4), min($vals), max($vals)]
        );
    }
}

/**
 * Récupère toutes les notes d'un film.
 */
function get_movie_ratings($movieId): array {
    $rows = db_fetch_all(
        'SELECT user_id, scores, like_score, is_liked, rating_weight, rated_at
         FROM ratings WHERE movie_id = ?',
        [$movieId]
    );
    $result = [];
    foreach ($rows as $row) {
        $row['scores'] = json_decode($row['scores'], true);
        $result[$row['user_id']] = $row;
    }
    return $result;
}

function refresh_user_backdrop(string $userId): void {
    $row = db_fetch_one(
        "SELECT m.poster FROM ratings r
         JOIN movies m ON m.tmdb_id = r.movie_id
         WHERE r.user_id = ? AND m.poster IS NOT NULL AND m.poster != ''
         ORDER BY COALESCE(r.like_score, 0) DESC, r.rated_at DESC
         LIMIT 1",
        [$userId]
    );
    db_execute(
        "UPDATE users SET backdrop_poster = ? WHERE id = ?",
        [$row['poster'] ?? null, $userId]
    );
}