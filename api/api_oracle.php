<?php
/**
 * API_ORACLE.PHP
 * Notation Oracle (IA) à l'import d'un film ou d'une série.
 * Scoring : genres (0.35) + keywords TMDB (0.65).
 */

if (!defined('ORACLE_USER_ID')) define('ORACLE_USER_ID', 'IA-ORACLE-001');
if (!defined('ORACLE_WEIGHT'))  define('ORACLE_WEIGHT', 0.1);
if (!defined('TMDB_API_KEY') && file_exists(__DIR__ . '/../config/settings.php'))
    require_once __DIR__ . '/../config/settings.php';

// ── Données partagées ─────────────────────────────────────────────────────────

function oracle_genre_dna(): array {
    return [
        "Action"         => ["complexite"=>-2,"previsibilite"=> 6,"intensite"=> 5,"malaise"=> 2,"stylisation"=> 5,"dynamique"=> 9,"depaysement"=> 4,"coherence"=>-2],
        "Aventure"       => ["complexite"=> 0,"previsibilite"=> 4,"intensite"=> 5,"malaise"=> 1,"stylisation"=> 5,"dynamique"=> 7,"depaysement"=> 9,"coherence"=>-1],
        "Animation"      => ["complexite"=> 0,"previsibilite"=> 5,"intensite"=> 5,"malaise"=>-3,"stylisation"=> 8,"dynamique"=> 4,"depaysement"=> 7,"coherence"=>-4],
        "Comédie"        => ["complexite"=>-3,"previsibilite"=> 6,"intensite"=> 3,"malaise"=>-5,"stylisation"=> 2,"dynamique"=> 3,"depaysement"=> 2,"coherence"=> 2],
        "Crime"          => ["complexite"=> 5,"previsibilite"=> 3,"intensite"=> 4,"malaise"=> 4,"stylisation"=> 3,"dynamique"=> 5,"depaysement"=>-1,"coherence"=> 6],
        "Documentaire"   => ["complexite"=> 6,"previsibilite"=> 7,"intensite"=> 4,"malaise"=> 0,"stylisation"=> 3,"dynamique"=>-2,"depaysement"=> 3,"coherence"=> 9],
        "Drame"          => ["complexite"=> 5,"previsibilite"=> 4,"intensite"=> 8,"malaise"=> 4,"stylisation"=> 5,"dynamique"=>-2,"depaysement"=> 1,"coherence"=> 7],
        "Fantastique"    => ["complexite"=> 3,"previsibilite"=> 3,"intensite"=> 4,"malaise"=> 2,"stylisation"=> 7,"dynamique"=> 5,"depaysement"=> 8,"coherence"=>-5],
        "Familial"       => ["complexite"=>-2,"previsibilite"=> 7,"intensite"=> 4,"malaise"=>-6,"stylisation"=> 5,"dynamique"=> 3,"depaysement"=> 5,"coherence"=> 1],
        "Histoire"       => ["complexite"=> 6,"previsibilite"=> 6,"intensite"=> 5,"malaise"=> 3,"stylisation"=> 5,"dynamique"=>-1,"depaysement"=> 5,"coherence"=> 8],
        "Horreur"        => ["complexite"=> 2,"previsibilite"=> 3,"intensite"=> 5,"malaise"=> 9,"stylisation"=> 3,"dynamique"=> 5,"depaysement"=>-1,"coherence"=> 2],
        "Musique"        => ["complexite"=> 2,"previsibilite"=> 5,"intensite"=> 7,"malaise"=>-1,"stylisation"=> 7,"dynamique"=> 5,"depaysement"=> 4,"coherence"=> 3],
        "Mystère"        => ["complexite"=> 7,"previsibilite"=>-5,"intensite"=> 4,"malaise"=> 4,"stylisation"=> 4,"dynamique"=> 3,"depaysement"=> 2,"coherence"=> 4],
        "Romance"        => ["complexite"=> 1,"previsibilite"=> 6,"intensite"=> 8,"malaise"=>-3,"stylisation"=> 5,"dynamique"=>-1,"depaysement"=> 3,"coherence"=> 4],
        "Science-Fiction"=> ["complexite"=> 7,"previsibilite"=> 2,"intensite"=> 4,"malaise"=> 2,"stylisation"=> 7,"dynamique"=> 5,"depaysement"=> 8,"coherence"=>-3],
        "Thriller"       => ["complexite"=> 4,"previsibilite"=>-4,"intensite"=> 5,"malaise"=> 6,"stylisation"=> 3,"dynamique"=> 7,"depaysement"=> 1,"coherence"=> 5],
        "Guerre"         => ["complexite"=> 4,"previsibilite"=> 4,"intensite"=> 6,"malaise"=> 7,"stylisation"=> 4,"dynamique"=> 6,"depaysement"=> 4,"coherence"=> 6],
        "Western"        => ["complexite"=> 3,"previsibilite"=> 4,"intensite"=> 4,"malaise"=> 2,"stylisation"=> 5,"dynamique"=> 4,"depaysement"=> 7,"coherence"=> 4],
    ];
}

function oracle_keyword_dna(): array {
    return [
        'nonlinear narrative'      => ['complexite'=>9,  'previsibilite'=>-7],
        'multiple storylines'      => ['complexite'=>7],
        'unreliable narrator'      => ['complexite'=>8,  'previsibilite'=>-7],
        'plot twist'               => ['previsibilite'=>-6,'complexite'=>4],
        'mystery'                  => ['complexite'=>5,  'previsibilite'=>-4],
        'ambiguous ending'         => ['complexite'=>7,  'previsibilite'=>-5],
        'mindfuck'                 => ['complexite'=>9,  'previsibilite'=>-8],
        'philosophical'            => ['complexite'=>8],
        'meta-fiction'             => ['complexite'=>8,  'previsibilite'=>-5],
        'psychedelic'              => ['complexite'=>7,  'stylisation'=>8, 'depaysement'=>7],
        'political'                => ['complexite'=>6,  'coherence'=>6],
        'based on true story'      => ['coherence'=>7,   'previsibilite'=>3],
        'biographical'             => ['coherence'=>8,   'previsibilite'=>4],
        'fairy tale'               => ['previsibilite'=>6,'depaysement'=>5],
        'superhero'                => ['previsibilite'=>5,'dynamique'=>8,  'depaysement'=>7],
        'redemption arc'           => ['previsibilite'=>5,'intensite'=>7],
        'happy ending'             => ['previsibilite'=>7,'malaise'=>-4],
        'psychological'            => ['intensite'=>8,   'malaise'=>5,    'complexite'=>6],
        'emotional'                => ['intensite'=>8],
        'tear-jerker'              => ['intensite'=>9],
        'coming-of-age'            => ['intensite'=>7,   'coherence'=>6],
        'grief'                    => ['intensite'=>8,   'coherence'=>7],
        'loss'                     => ['intensite'=>8,   'coherence'=>6],
        'trauma'                   => ['intensite'=>8,   'malaise'=>6],
        'love'                     => ['intensite'=>7],
        'friendship'               => ['intensite'=>6,   'coherence'=>6],
        'war'                      => ['intensite'=>8,   'malaise'=>7,    'coherence'=>5],
        'world war ii'             => ['intensite'=>9,   'malaise'=>7,    'coherence'=>6, 'depaysement'=>3],
        'world war i'              => ['intensite'=>8,   'malaise'=>7,    'coherence'=>6, 'depaysement'=>3],
        'holocaust'                => ['intensite'=>10,  'malaise'=>9,    'coherence'=>7],
        'nazi'                     => ['intensite'=>8,   'malaise'=>8],
        'nazi germany'             => ['intensite'=>8,   'malaise'=>8,    'coherence'=>5],
        'genocide'                 => ['intensite'=>10,  'malaise'=>9],
        'concentration camp'       => ['intensite'=>10,  'malaise'=>9,    'coherence'=>7],
        'antisemitism'             => ['intensite'=>8,   'malaise'=>8],
        'survival'                 => ['intensite'=>8,   'dynamique'=>7,  'malaise'=>5],
        'gore'                     => ['malaise'=>9,     'intensite'=>7],
        'torture'                  => ['malaise'=>10,    'intensite'=>8],
        'rape'                     => ['malaise'=>9,     'intensite'=>8],
        'sexual violence'          => ['malaise'=>9,     'intensite'=>8],
        'sexual abuse'             => ['malaise'=>9,     'intensite'=>8],
        'child abuse'              => ['malaise'=>9,     'intensite'=>8],
        'disturbing'               => ['malaise'=>9],
        'dark'                     => ['malaise'=>6,     'intensite'=>6],
        'dark comedy'              => ['malaise'=>4,     'complexite'=>5],
        'dark humor'               => ['malaise'=>4],
        'nihilism'                 => ['malaise'=>7,     'complexite'=>6],
        'violence'                 => ['malaise'=>6,     'intensite'=>7],
        'body horror'              => ['malaise'=>10],
        'psychological horror'     => ['malaise'=>8,     'complexite'=>7],
        'serial killer'            => ['malaise'=>7,     'intensite'=>6],
        'serial murder'            => ['malaise'=>7,     'intensite'=>6],
        'murder'                   => ['malaise'=>6,     'intensite'=>6],
        'terrorism'                => ['malaise'=>7,     'intensite'=>7],
        'social commentary'        => ['malaise'=>4,     'complexite'=>7],
        'drug use'                 => ['malaise'=>5,     'intensite'=>5],
        'drugs'                    => ['malaise'=>5,     'intensite'=>5],
        'addiction'                => ['malaise'=>6,     'intensite'=>7],
        'suicide'                  => ['malaise'=>7,     'intensite'=>8],
        'abuse'                    => ['malaise'=>7,     'intensite'=>7],
        'racism'                   => ['malaise'=>6,     'intensite'=>6,  'coherence'=>7],
        'exploitation'             => ['malaise'=>8],
        'paranoia'                 => ['malaise'=>6,     'complexite'=>6, 'dynamique'=>5],
        'prison'                   => ['malaise'=>5,     'intensite'=>5,  'dynamique'=>-2],
        'revenge'                  => ['malaise'=>5,     'intensite'=>7,  'dynamique'=>6],
        'visually stunning'        => ['stylisation'=>9],
        'beautiful cinematography' => ['stylisation'=>9],
        'cinematography'           => ['stylisation'=>8],
        'art house'                => ['stylisation'=>9, 'complexite'=>7],
        'surrealism'               => ['stylisation'=>9, 'depaysement'=>8,'complexite'=>8],
        'musical'                  => ['stylisation'=>7, 'intensite'=>6],
        'black and white'          => ['stylisation'=>8],
        'neo-noir'                 => ['stylisation'=>8, 'malaise'=>5,    'dynamique'=>5],
        'noir'                     => ['stylisation'=>7, 'malaise'=>5],
        'expressionism'            => ['stylisation'=>8, 'malaise'=>4],
        'stop motion'              => ['stylisation'=>8, 'depaysement'=>6],
        'anime'                    => ['stylisation'=>7, 'depaysement'=>7],
        'animation'                => ['stylisation'=>7],
        'atmospheric'              => ['stylisation'=>7, 'malaise'=>3],
        'costume drama'            => ['stylisation'=>7, 'depaysement'=>6,'coherence'=>5],
        'music'                    => ['stylisation'=>6, 'intensite'=>6],
        'dance'                    => ['stylisation'=>7, 'intensite'=>6],
        'espionage'                => ['dynamique'=>7,   'complexite'=>5, 'malaise'=>3],
        'spy'                      => ['dynamique'=>7,   'malaise'=>3],
        'detective'                => ['dynamique'=>5,   'complexite'=>6],
        'action'                   => ['dynamique'=>8,   'previsibilite'=>4],
        'fast-paced'               => ['dynamique'=>8],
        'slow burn'                => ['dynamique'=>-6,  'complexite'=>6],
        'slow cinema'              => ['dynamique'=>-7,  'stylisation'=>8],
        'chase'                    => ['dynamique'=>8],
        'thriller'                 => ['dynamique'=>7,   'malaise'=>5],
        'suspense'                 => ['dynamique'=>6,   'malaise'=>5],
        'tension'                  => ['dynamique'=>6,   'malaise'=>6],
        'heist'                    => ['dynamique'=>7,   'complexite'=>5],
        'martial arts'             => ['dynamique'=>9,   'stylisation'=>5],
        'car chase'                => ['dynamique'=>9],
        'race against time'        => ['dynamique'=>8,   'malaise'=>4],
        'escape'                   => ['dynamique'=>7,   'intensite'=>6],
        'fantasy'                  => ['depaysement'=>9, 'stylisation'=>6],
        'science fiction'          => ['depaysement'=>8, 'complexite'=>6],
        'space'                    => ['depaysement'=>8, 'stylisation'=>7],
        'space opera'              => ['depaysement'=>9, 'stylisation'=>7, 'dynamique'=>7],
        'post-apocalyptic'         => ['depaysement'=>7, 'malaise'=>6],
        'dystopia'                 => ['depaysement'=>7, 'malaise'=>6,    'complexite'=>6],
        'dystopian'                => ['depaysement'=>7, 'malaise'=>6,    'complexite'=>6],
        'mythology'                => ['depaysement'=>7, 'stylisation'=>6],
        'period piece'             => ['depaysement'=>6, 'coherence'=>5],
        'historical'               => ['depaysement'=>5, 'coherence'=>7],
        'historical fiction'       => ['depaysement'=>5, 'coherence'=>6],
        'middle ages'              => ['depaysement'=>7, 'stylisation'=>5],
        'road movie'               => ['depaysement'=>6, 'intensite'=>5],
        'adventure'                => ['depaysement'=>7, 'dynamique'=>6],
        'travel'                   => ['depaysement'=>6],
        'exotic'                   => ['depaysement'=>7],
        'alien'                    => ['depaysement'=>8, 'complexite'=>5],
        'magic'                    => ['depaysement'=>8, 'stylisation'=>6],
        'time travel'              => ['depaysement'=>7, 'complexite'=>7, 'previsibilite'=>-4],
        'time loop'                => ['depaysement'=>5, 'complexite'=>8, 'previsibilite'=>-5],
        'artificial intelligence'  => ['depaysement'=>6, 'complexite'=>7],
        'robot'                    => ['depaysement'=>6, 'complexite'=>5],
        'monster'                  => ['malaise'=>5,     'intensite'=>5,  'depaysement'=>5],
        'vampire'                  => ['malaise'=>5,     'depaysement'=>6, 'stylisation'=>5],
        'zombie'                   => ['malaise'=>6,     'intensite'=>5,  'depaysement'=>4],
        'apocalypse'               => ['malaise'=>7,     'intensite'=>6,  'depaysement'=>6],
        'found footage'            => ['stylisation'=>6, 'malaise'=>5],
        'realism'                  => ['coherence'=>9,   'depaysement'=>-4],
        'slice of life'            => ['coherence'=>8,   'dynamique'=>-4],
        'family drama'             => ['coherence'=>7,   'intensite'=>6],
        'social realism'           => ['coherence'=>8,   'malaise'=>4],
        'workplace'                => ['coherence'=>6],
        'independent film'         => ['coherence'=>7,   'stylisation'=>5],
        'naturalism'               => ['coherence'=>9,   'stylisation'=>-3],
        'mockumentary'             => ['coherence'=>7,   'complexite'=>5],
        'documentary'              => ['coherence'=>9,   'depaysement'=>3],
        'based on novel'           => ['coherence'=>6,   'previsibilite'=>2],
        'based on true events'     => ['coherence'=>7,   'previsibilite'=>3],
        'based on real events'     => ['coherence'=>7,   'previsibilite'=>3],
        'based on comic book'      => ['previsibilite'=>4,'depaysement'=>5,'dynamique'=>5],
        'anti-hero'                => ['complexite'=>6,  'malaise'=>3],
        'feel-good'                => ['intensite'=>-3,  'malaise'=>-6,   'previsibilite'=>5],
        'comedy'                   => ['malaise'=>-4,    'previsibilite'=>4,'dynamique'=>3],
        'romantic comedy'          => ['malaise'=>-5,    'previsibilite'=>6,'intensite'=>5],
        'uplifting'                => ['intensite'=>6,   'malaise'=>-5],
        'family-friendly'          => ['malaise'=>-7,    'previsibilite'=>6],
        'slapstick'                => ['malaise'=>-5,    'previsibilite'=>5,'dynamique'=>5],
        'satire'                   => ['complexite'=>6,  'malaise'=>3],
    ];
}

function oracle_fetch_keywords(int $tmdbId, string $type): array {
    $apiKey   = defined('TMDB_API_KEY') ? TMDB_API_KEY : '';
    $endpoint = $type === 'tv'
        ? "https://api.themoviedb.org/3/tv/{$tmdbId}/keywords?api_key={$apiKey}"
        : "https://api.themoviedb.org/3/movie/{$tmdbId}/keywords?api_key={$apiKey}";
    $res  = @file_get_contents($endpoint);
    if (!$res) return [];
    $data = json_decode($res, true);
    $list = $data['keywords'] ?? $data['results'] ?? [];
    return array_map(fn($k) => strtolower($k['name']), $list);
}

function oracle_compute_scores(array $keywords, array $genres): array {
    $criteria   = ['complexite','previsibilite','intensite','malaise','stylisation','dynamique','depaysement','coherence'];
    $genreDna   = oracle_genre_dna();
    $keywordDna = oracle_keyword_dna();

    $genreScores = array_fill_keys($criteria, 0.0);
    $validGenres = array_filter($genres, fn($g) => isset($genreDna[$g]));
    if (!empty($validGenres)) {
        foreach ($validGenres as $g)
            foreach ($criteria as $c)
                $genreScores[$c] += $genreDna[$g][$c] ?? 0;
        foreach ($criteria as $c)
            $genreScores[$c] /= count($validGenres);
    }

    $kwScores  = array_fill_keys($criteria, 0.0);
    $kwMatches = 0;
    foreach ($keywords as $kw) {
        if (isset($keywordDna[$kw])) {
            foreach ($keywordDna[$kw] as $c => $v)
                $kwScores[$c] += $v;
            $kwMatches++;
        }
    }

    $scores = [];
    foreach ($criteria as $c) {
        $base = $kwMatches > 0
            ? $genreScores[$c] * 0.35 + max(-10, min(10, $kwScores[$c] / max(1, $kwMatches / 2))) * 0.65
            : $genreScores[$c];
        $scores[$c] = round(max(-10, min(10, $base + (mt_rand(-8, 8) / 10))), 1);
    }
    return $scores;
}

// ── Fonctions publiques ───────────────────────────────────────────────────────

function apply_oracle_judgment(int $tmdbId, array $genres): bool {
    $pdo = getPDO();
    try {
        _oracle_ensure_user($pdo);

        $keywords = oracle_fetch_keywords($tmdbId, 'movie');
        $scores   = oracle_compute_scores($keywords, $genres);

        $pdo->prepare("INSERT IGNORE INTO ratings (user_id, movie_id, scores, rating_weight, rated_at)
                       VALUES (?, ?, ?, ?, NOW())")
            ->execute([ORACLE_USER_ID, $tmdbId, json_encode($scores), ORACLE_WEIGHT]);

        foreach ($scores as $criterio => $val) {
            $pdo->prepare("INSERT INTO movie_dna (movie_id, criterio, avg_score, variance, sample_size)
                           VALUES (?, ?, ?, 0, 1)
                           ON DUPLICATE KEY UPDATE avg_score = VALUES(avg_score)")
                ->execute([$tmdbId, $criterio, $val]);
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Notation Oracle d'une série — saison par saison.
 *
 * Pour chaque saison :
 *   - Scores ADN calculés depuis les genres + keywords de la série (niveau série,
 *     TMDB ne fournit pas de keywords par saison), avec bruit ±0.3 entre saisons
 *   - like_score dérivé du vote_average TMDB de la saison
 *   - INSERT IGNORE → ne jamais écraser un vote humain existant
 *
 * Après tous les votes de saison :
 *   - Recalcul de series_ratings (agrégat) pour l'oracle
 *   - Recalcul de series_dna (ADN collectif de la série)
 *   - Mise à jour de series.total_votes
 */
function apply_oracle_judgment_series(int $tmdbId, array $genres): bool {
    $pdo = getPDO();
    try {
        _oracle_ensure_user($pdo);

        // Résoudre l'id local
        $seriesRow = $pdo->prepare("SELECT id, total_seasons FROM series WHERE tmdb_id = ?");
        $seriesRow->execute([$tmdbId]);
        $series = $seriesRow->fetch(\PDO::FETCH_ASSOC);
        if (!$series) return false;
        $seriesId     = (int)$series['id'];
        $totalSeasons = (int)$series['total_seasons'];

        if ($totalSeasons <= 0) return false;

        // Scores ADN de base (communs à toutes les saisons)
        $keywords   = oracle_fetch_keywords($tmdbId, 'tv');
        $baseScores = oracle_compute_scores($keywords, $genres);

        $seasonsDone = 0;

        for ($sn = 1; $sn <= $totalSeasons; $sn++) {
            // Données TMDB de la saison pour récupérer le vote_average
            $seasonData = _oracle_fetch_season_tmdb($tmdbId, $sn);

            $voteAvg   = isset($seasonData['vote_average']) ? (float)$seasonData['vote_average'] : null;
            $voteCount = (int)($seasonData['vote_count'] ?? 0);
            $likeScore = _oracle_vote_to_like_score($voteAvg, $voteCount);
            $isLiked   = ($likeScore !== null && $likeScore > 0) ? 1 : 0;

            // Variation légère des scores ADN par saison (évite les clones parfaits)
            $seasonScores = [];
            foreach ($baseScores as $c => $v) {
                $seasonScores[$c] = round(max(-10, min(10, $v + (mt_rand(-3, 3) / 10))), 1);
            }

            // INSERT IGNORE : ne jamais écraser un vote humain
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO season_ratings
                    (user_id, series_id, season_number, scores, like_score, is_liked, rating_weight, rated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                ORACLE_USER_ID, $seriesId, $sn,
                json_encode($seasonScores), $likeScore, $isLiked, ORACLE_WEIGHT,
            ]);

            if ($stmt->rowCount() > 0) $seasonsDone++;

            // Petite pause entre appels TMDB
            if ($sn < $totalSeasons) usleep(100000); // 100ms
        }

        if ($seasonsDone === 0) return true; // tout déjà noté, pas une erreur

        // Recalcul agrégat series_ratings pour l'oracle
        _oracle_recalc_series_aggregate($pdo, ORACLE_USER_ID, $seriesId);

        // Recalcul series_dna
        _oracle_update_series_dna($pdo, $seriesId);

        // Mise à jour total_votes
        $cnt = $pdo->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM season_ratings
             WHERE series_id = ? AND scores IS NOT NULL"
        );
        $cnt->execute([$seriesId]);
        $pdo->prepare("UPDATE series SET total_votes = ? WHERE id = ?")
            ->execute([$cnt->fetchColumn(), $seriesId]);

        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ── Helpers internes oracle séries ────────────────────────────────────────────

/**
 * Récupère les données TMDB d'une saison (vote_average, vote_count).
 * Retourne un tableau vide si indisponible.
 */
function _oracle_fetch_season_tmdb(int $tmdbId, int $seasonNumber): array {
    $apiKey = defined('TMDB_API_KEY') ? TMDB_API_KEY : '';
    $url    = "https://api.themoviedb.org/3/tv/{$tmdbId}/season/{$seasonNumber}?api_key={$apiKey}";
    $res    = @file_get_contents($url);
    if (!$res) return [];
    $data = json_decode($res, true);
    return (is_array($data) && !isset($data['status_code'])) ? $data : [];
}

/**
 * Traduit un vote_average TMDB (0–10) en like_score ADNMovie (−10 à +10).
 * Retourne null si la note est insuffisamment représentative (< 5 votes).
 * Centrage sur 5.0 (médiane TMDB) avec légère amplification.
 */
function _oracle_vote_to_like_score(?float $voteAvg, int $voteCount): ?int {
    if ($voteAvg === null || $voteAvg <= 0 || $voteCount < 5) return null;
    $raw   = ($voteAvg - 5.0) * 2.0;              // [0,10] → [−10,+10], centré sur 5
    $raw  += mt_rand(-5, 5) / 10.0;               // bruit ±0.5
    return (int)round(max(-10, min(10, $raw)));
}

/**
 * Recalcule series_ratings pour un user/série à partir de season_ratings.
 * Moyenne simple des scores par critère sur toutes les saisons notées.
 */
function _oracle_recalc_series_aggregate(\PDO $pdo, string $userId, int $seriesId): void {
    $stmt = $pdo->prepare(
        "SELECT scores, like_score, is_liked, rating_weight
         FROM season_ratings
         WHERE user_id = ? AND series_id = ? AND scores IS NOT NULL"
    );
    $stmt->execute([$userId, $seriesId]);
    $seasons = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($seasons)) {
        $pdo->prepare("DELETE FROM series_ratings WHERE user_id = ? AND series_id = ?")
            ->execute([$userId, $seriesId]);
        return;
    }

    $sumC = []; $cntC = [];
    $sumLike = 0; $cntLike = 0;
    $sumW = 0; $isLikedAny = 0;

    foreach ($seasons as $row) {
        $sc = json_decode($row['scores'], true) ?? [];
        foreach ($sc as $c => $v) {
            if ($v !== null) {
                $sumC[$c] = ($sumC[$c] ?? 0) + (float)$v;
                $cntC[$c] = ($cntC[$c] ?? 0) + 1;
            }
        }
        if ($row['like_score'] !== null) { $sumLike += (int)$row['like_score']; $cntLike++; }
        if ($row['is_liked']) $isLikedAny = 1;
        $sumW += (float)$row['rating_weight'];
    }

    $agg = [];
    foreach ($sumC as $c => $s) $agg[$c] = round($s / $cntC[$c], 4);

    $aggLike   = $cntLike > 0 ? (int)round($sumLike / $cntLike) : null;
    $aggWeight = round($sumW / count($seasons), 4);

    $pdo->prepare(
        "INSERT INTO series_ratings
            (user_id, series_id, scores, like_score, is_liked, rating_weight, rated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             scores = VALUES(scores), like_score = VALUES(like_score),
             is_liked = VALUES(is_liked), rating_weight = VALUES(rating_weight), rated_at = NOW()"
    )->execute([$userId, $seriesId, json_encode($agg), $aggLike, $isLikedAny, $aggWeight]);
}

/**
 * Recalcule series_dna (ADN collectif) depuis tous les votes de season_ratings
 * pour une série donnée (tous utilisateurs confondus).
 */
function _oracle_update_series_dna(\PDO $pdo, int $seriesId): void {
    $stmt = $pdo->prepare(
        "SELECT scores, rating_weight FROM season_ratings
         WHERE series_id = ? AND scores IS NOT NULL"
    );
    $stmt->execute([$seriesId]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($rows)) {
        $pdo->prepare("DELETE FROM series_dna WHERE series_id = ?")->execute([$seriesId]);
        return;
    }

    $data = [];
    foreach ($rows as $row) {
        $sc = json_decode($row['scores'], true) ?? [];
        $w  = max(0.01, (float)$row['rating_weight']);
        foreach ($sc as $c => $v) {
            if ($v !== null) {
                $data[$c]['values'][]  = (float)$v;
                $data[$c]['weights'][] = $w;
            }
        }
    }

    foreach ($data as $c => $d) {
        $wSum = array_sum($d['weights']);
        $wavg = 0;
        foreach ($d['values'] as $i => $v) $wavg += $v * $d['weights'][$i];
        $wavg = $wSum > 0 ? $wavg / $wSum : 0;
        $wvar = 0;
        foreach ($d['values'] as $i => $v) $wvar += $d['weights'][$i] * pow($v - $wavg, 2);
        $wvar = $wSum > 0 ? $wvar / $wSum : 0;

        $pdo->prepare(
            "INSERT INTO series_dna (series_id, criterio, avg_score, variance, sample_size)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 avg_score = VALUES(avg_score), variance = VALUES(variance),
                 sample_size = VALUES(sample_size)"
        )->execute([$seriesId, $c, round($wavg, 4), round($wvar, 4), count($d['values'])]);
    }
}

function _oracle_ensure_user(\PDO $pdo): void {
    $check = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $check->execute([ORACLE_USER_ID]);
    if (!$check->fetch()) {
        $pdo->prepare("INSERT INTO users (id, username, email, avatar, provider, created_at, last_login)
                       VALUES (?, 'IA Oracle', 'oracle@adnmovie.fr', '', 'oracle', NOW(), NOW())")
            ->execute([ORACLE_USER_ID]);
    }
}