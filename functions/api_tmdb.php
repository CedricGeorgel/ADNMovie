<?php
/**
 * API_TMDB.PHP
 * Centralise les requêtes brutes vers l'API TMDB.
 *
 * Ce fichier expose uniquement :
 *   - fetch_tmdb_movie($tmdbId)  → données brutes d'un film
 *   - search_tmdb($query)        → résultats de recherche TMDB
 *
 * La logique BDD (get_movie_smart, search_movies_hybrid) est dans utils.php.
 */

/**
 * Récupère les détails complets d'un film depuis TMDB.
 * Retourne un tableau normalisé prêt à être inséré en BDD, ou null.
 *
 * @param int $tmdbId
 * @return array|null
 */
function fetch_tmdb_movie(int $tmdbId): ?array {
    $url = "https://api.themoviedb.org/3/movie/{$tmdbId}"
         . "?api_key=" . TMDB_API_KEY
         . "&language=fr-FR&append_to_response=credits";

    $response = @file_get_contents($url);
    if (!$response) return null;

    $data = json_decode($response, true);
    if (empty($data['id'])) return null;

    $director = 'Inconnu';
    foreach ($data['credits']['crew'] ?? [] as $crew) {
        if ($crew['job'] === 'Director') { $director = $crew['name']; break; }
    }

    $cast = [];
    foreach (array_slice($data['credits']['cast'] ?? [], 0, 10) as $actor) {
        $cast[] = [
            'name'         => $actor['name'],
            'character'    => $actor['character'],
            'profile_path' => $actor['profile_path'] ?? null,
        ];
    }

    return [
        'id'           => $data['id'],
        'title'        => $data['title'],
        'year'         => !empty($data['release_date']) ? (int)substr($data['release_date'], 0, 4) : null,
        'director'     => $director,
        'runtime'      => $data['runtime'] ?? 0,
        'poster'       => $data['poster_path']
                            ? "https://image.tmdb.org/t/p/w500" . $data['poster_path']
                            : 'assets/no-poster.svg',
        'synopsis'     => $data['overview'] ?? 'Aucun résumé disponible.',
        'genres'       => array_column($data['genres'] ?? [], 'name'),
        'cast'         => $cast,
        'vote_average' => isset($data['vote_average']) ? (float)$data['vote_average'] : null,
        'vote_count'   => isset($data['vote_count'])   ? (int)$data['vote_count']     : 0,
    ];
}

/**
 * Recherche des films sur TMDB.
 * Retourne un tableau de résultats normalisés, ou [].
 *
 * @param string $query
 * @return array
 */
function search_tmdb(string $query): array {
    $url = "https://api.themoviedb.org/3/search/movie"
         . "?api_key=" . TMDB_API_KEY
         . "&query=" . urlencode($query)
         . "&language=fr-FR";

    $response = @file_get_contents($url);
    if (!$response) return [];

    $data = json_decode($response, true);
    $results = [];

    foreach ($data['results'] ?? [] as $m) {
        $results[] = [
            'id'     => $m['id'],
            'title'  => $m['title'],
            'poster' => !empty($m['poster_path'])
                            ? "https://image.tmdb.org/t/p/w500" . $m['poster_path']
                            : 'assets/no-poster.svg',
            'year'   => !empty($m['release_date']) ? (int)substr($m['release_date'], 0, 4) : null,
            'source' => 'tmdb',
        ];
    }

    return $results;
}

/**
 * Récupère les fournisseurs de streaming pour un film (JustWatch via TMDB).
 *
 * @param int $tmdbId
 * @return array
 */
function fetch_tmdb_watch_providers(int $tmdbId): array {
    $url = "https://api.themoviedb.org/3/movie/{$tmdbId}/watch/providers"
         . "?api_key=" . TMDB_API_KEY;

    $response = @file_get_contents($url);
    if (!$response) return [];

    $data = json_decode($response, true);
    return $data['results']['FR'] ?? [];
}

/**
 * Récupère les détails complets d'une série TV depuis TMDB.
 * Retourne un tableau normalisé, ou null.
 *
 * @param int $tmdbId
 * @return array|null
 */
function fetch_tmdb_series(int $tmdbId): ?array {
    $url = "https://api.themoviedb.org/3/tv/{$tmdbId}"
         . "?api_key=" . TMDB_API_KEY
         . "&language=fr-FR&append_to_response=credits";

    $response = @file_get_contents($url);
    if (!$response) return null;

    $data = json_decode($response, true);
    if (empty($data['id'])) return null;

    $creator = !empty($data['created_by'][0]['name'])
        ? $data['created_by'][0]['name']
        : 'Inconnu';

    $cast = [];
    foreach (array_slice($data['credits']['cast'] ?? [], 0, 10) as $actor) {
        $cast[] = [
            'name'         => $actor['name'],
            'character'    => $actor['character'],
            'profile_path' => $actor['profile_path'] ?? null,
        ];
    }

    $seasons = [];
    foreach ($data['seasons'] ?? [] as $season) {
        if (($season['season_number'] ?? 0) === 0) continue;
        $seasons[] = [
            'season_number' => $season['season_number'],
            'name'          => $season['name'],
            'episode_count' => $season['episode_count'],
            'poster_path'   => !empty($season['poster_path'])
                                   ? "https://image.tmdb.org/t/p/w500" . $season['poster_path']
                                   : 'assets/no-poster.svg',
            'overview'      => $season['overview'] ?? '',
        ];
    }

    return [
        'id'             => $data['id'],
        'title'          => $data['name'],
        'year'           => !empty($data['first_air_date']) ? (int)substr($data['first_air_date'], 0, 4) : null,
        'creator'        => $creator,
        'synopsis'       => $data['overview'] ?? 'Aucun résumé disponible.',
        'genres'         => array_column($data['genres'] ?? [], 'name'),
        'cast'           => $cast,
        'poster'         => !empty($data['poster_path'])
                                ? "https://image.tmdb.org/t/p/w500" . $data['poster_path']
                                : 'assets/no-poster.svg',
        'total_seasons'  => $data['number_of_seasons'] ?? 0,
        'seasons'        => $seasons,
        'status'         => $data['status'] ?? null,
        'content_type'   => 'tv',
    ];
}

/**
 * Récupère les détails d'une saison d'une série TV depuis TMDB.
 * Retourne un tableau normalisé, ou null.
 *
 * @param int $seriesId
 * @param int $seasonNumber
 * @return array|null
 */
function fetch_tmdb_season(int $seriesId, int $seasonNumber): ?array {
    $url = "https://api.themoviedb.org/3/tv/{$seriesId}/season/{$seasonNumber}"
         . "?api_key=" . TMDB_API_KEY
         . "&language=fr-FR";

    $response = @file_get_contents($url);
    if (!$response) return null;

    $data = json_decode($response, true);
    if (empty($data['season_number']) && $data['season_number'] !== 0) return null;

    $episodes = [];
    foreach ($data['episodes'] ?? [] as $ep) {
        $episodes[] = [
            'episode_number' => $ep['episode_number'],
            'name'           => $ep['name'],
            'overview'       => $ep['overview'] ?? '',
            'still_path'     => !empty($ep['still_path'])
                                    ? "https://image.tmdb.org/t/p/w300" . $ep['still_path']
                                    : 'assets/no-poster.svg',
            'air_date'       => $ep['air_date'] ?? null,
            'runtime'        => $ep['runtime'] ?? null,
        ];
    }

    return [
        'series_id'     => $seriesId,
        'season_number' => $data['season_number'],
        'name'          => $data['name'],
        'synopsis'      => $data['overview'] ?? '',
        'poster'        => !empty($data['poster_path'])
                               ? "https://image.tmdb.org/t/p/w500" . $data['poster_path']
                               : 'assets/no-poster.svg',
        'episodes'      => $episodes,
        'content_type'  => 'season',
    ];
}

/**
 * Récupère les détails d'un épisode d'une série TV depuis TMDB.
 * Retourne un tableau normalisé, ou null.
 *
 * @param int $seriesId
 * @param int $seasonNumber
 * @param int $episodeNumber
 * @return array|null
 */
function fetch_tmdb_episode(int $seriesId, int $seasonNumber, int $episodeNumber): ?array {
    $url = "https://api.themoviedb.org/3/tv/{$seriesId}/season/{$seasonNumber}/episode/{$episodeNumber}"
         . "?api_key=" . TMDB_API_KEY
         . "&language=fr-FR"
         . "&append_to_response=credits";

    $response = @file_get_contents($url);
    if (!$response) return null;

    $data = json_decode($response, true);
    if (empty($data['episode_number']) && $data['episode_number'] !== 0) return null;

    // Casting : guest_stars en priorité, complété par credits.cast
    $cast = [];
    $seen = [];
    $sources = array_merge(
        $data['guest_stars'] ?? [],
        $data['credits']['cast'] ?? []
    );
    foreach (array_slice($sources, 0, 10) as $actor) {
        $id = $actor['id'] ?? null;
        if ($id && isset($seen[$id])) continue;
        if ($id) $seen[$id] = true;
        $cast[] = [
            'name'         => $actor['name'] ?? '',
            'character'    => $actor['character'] ?? '',
            'profile_path' => !empty($actor['profile_path'])
                                  ? "https://image.tmdb.org/t/p/w185" . $actor['profile_path']
                                  : null,
        ];
    }

    return [
        'series_id'      => $seriesId,
        'season_number'  => $data['season_number'],
        'episode_number' => $data['episode_number'],
        'name'           => $data['name'],
        'synopsis'       => $data['overview'] ?? '',
        'still'          => !empty($data['still_path'])
                                ? "https://image.tmdb.org/t/p/w780" . $data['still_path']
                                : 'assets/no-poster.svg',
        'air_date'       => $data['air_date'] ?? null,
        'runtime'        => $data['runtime'] ?? null,
        'cast'           => $cast,
        'content_type'   => 'episode',
    ];
}

/**
 * Récupère les fournisseurs de streaming pour une série TV (JustWatch via TMDB).
 *
 * @param int $seriesId
 * @return array
 */
function fetch_tmdb_series_watch_providers(int $seriesId): array {
    $url = "https://api.themoviedb.org/3/tv/{$seriesId}/watch/providers"
         . "?api_key=" . TMDB_API_KEY;

    $response = @file_get_contents($url);
    if (!$response) return [];

    $data = json_decode($response, true);
    return $data['results']['FR'] ?? [];
}

/**
 * Recherche multi-type (films + séries) sur TMDB.
 * Retourne un tableau de résultats normalisés (films et séries), ou [].
 *
 * @param string $query
 * @return array
 */
function search_tmdb_multi(string $query): array {
    $url = "https://api.themoviedb.org/3/search/multi"
         . "?api_key=" . TMDB_API_KEY
         . "&query=" . urlencode($query)
         . "&language=fr-FR";

    $response = @file_get_contents($url);
    if (!$response) return [];

    $data = json_decode($response, true);
    $results = [];

    foreach ($data['results'] ?? [] as $item) {
        $mediaType = $item['media_type'] ?? '';

        if ($mediaType === 'movie') {
            $results[] = [
                'id'           => $item['id'],
                'title'        => $item['title'],
                'poster'       => !empty($item['poster_path'])
                                      ? "https://image.tmdb.org/t/p/w500" . $item['poster_path']
                                      : 'assets/no-poster.svg',
                'year'         => !empty($item['release_date']) ? (int)substr($item['release_date'], 0, 4) : null,
                'source'       => 'tmdb',
                'content_type' => 'movie',
            ];
        } elseif ($mediaType === 'tv') {
            $results[] = [
                'id'           => $item['id'],
                'title'        => $item['name'],
                'poster'       => !empty($item['poster_path'])
                                      ? "https://image.tmdb.org/t/p/w500" . $item['poster_path']
                                      : 'assets/no-poster.svg',
                'year'         => !empty($item['first_air_date']) ? (int)substr($item['first_air_date'], 0, 4) : null,
                'source'       => 'tmdb',
                'content_type' => 'tv',
            ];
        }
    }

    return $results;
}