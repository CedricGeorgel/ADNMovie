<?php
/**
 * WATCH-PROVIDERS.PHP
 * - YouTube : lien direct via YouTube Data API v3 (cache BDD, sentinel 'NONE')
 * - Tous les autres providers : page JustWatch spécifique au film/série
 */

// ── Whitelist providers depuis providers_config ───────────────────────────────

function _get_provider_whitelist(): array {
    static $whitelist = null;
    if ($whitelist !== null) return $whitelist;
    $rows = db_fetch_all('SELECT provider_id, type FROM providers_config WHERE is_active = 1');
    $whitelist = [];
    foreach ($rows as $row) {
        $whitelist[(int)$row['provider_id']] = $row['type'];
    }
    return $whitelist;
}

function _filter_providers(array $providers): array {
    $whitelist = _get_provider_whitelist();
    foreach (['flatrate', 'buy', 'rent'] as $type) {
        if (isset($providers[$type])) {
            $providers[$type] = array_values(array_filter(
                $providers[$type],
                fn($p) => isset($whitelist[(int)$p['provider_id']])
            ));
        }
    }
    return $providers;
}

// ── Colonne BDD youtube_id ────────────────────────────────────────────────────
function _ensure_provider_columns(string $table = 'movies'): void {
    static $checked = [];
    if (isset($checked[$table])) return;
    $checked[$table] = true;

    $exists = db_fetch_one(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'youtube_id'",
        [$table]
    );
    if (!$exists) {
        getPDO()->exec("ALTER TABLE `{$table}` ADD COLUMN youtube_id VARCHAR(20) NULL DEFAULT NULL");
    }
}

// ── YouTube Data API v3 ───────────────────────────────────────────────────────
// NULL  = jamais cherché → fetch
// 'NONE'= cherché, introuvable → fallback recherche YouTube
// 'ID'  = video_id valide → lien direct watch?v=ID

function _fetch_and_cache_youtube_id(int $tmdbId, string $title, int $year = 0, string $table = 'movies'): string {
    if (!defined('API_KEY') || empty(API_KEY)) {
        db_execute("UPDATE `{$table}` SET youtube_id = 'NONE' WHERE tmdb_id = ?", [$tmdbId]);
        return 'NONE';
    }

    $q   = urlencode($title . ($year ? " {$year}" : ''));
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&q={$q}"
         . "&type=video&videoType=movie&maxResults=3&key=" . API_KEY;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_USERAGENT      => 'Moovie-App/2.0',
        CURLOPT_HTTPHEADER     => ['Referer: https://adnmovie.fr/'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $videoId = 'NONE';
    if ($httpCode === 200 && $response) {
        $data  = json_decode($response, true);
        $videoId = $data['items'][0]['id']['videoId'] ?? 'NONE';
    }

    db_execute("UPDATE `{$table}` SET youtube_id = ? WHERE tmdb_id = ?", [$videoId, $tmdbId]);
    return $videoId;
}

// ── JustWatch slug ────────────────────────────────────────────────────────────

function _justwatch_url(string $title, string $contentType = 'movie'): string {
    $slug = mb_strtolower($title, 'UTF-8');
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
    $slug = preg_replace("/[^a-z0-9\s]/", '', $slug);   // retire tout sauf lettres, chiffres, espaces
    $slug = preg_replace('/\s+/', '-', trim($slug));
    $slug = preg_replace('/-+/', '-', $slug);
    $type = $contentType === 'tv' ? 'serie' : 'film';
    return "https://www.justwatch.com/fr/{$type}/{$slug}";
}

// ── Deeplink ──────────────────────────────────────────────────────────────────

function _provider_deeplink(int $id, string $title, string $youtubeId = 'NONE', string $jwUrl = '', string $contentType = 'movie'): string {
    $q = urlencode($title);

    // YouTube : lien direct si trouvé sur YouTube Movies, sinon recherche YouTube
    if ($id === 188 || $id === 192) {
        return $youtubeId !== 'NONE'
            ? "https://www.youtube.com/watch?v={$youtubeId}"
            : "https://www.youtube.com/results?search_query={$q}";
    }

    // Apple TV : recherche directe films ou séries
    if ($id === 2 || $id === 350) {
        return $contentType === 'tv'
            ? "https://tv.apple.com/fr/collection/tv-et-series/uts.col.search.SH?searchTerm={$q}"
            : "https://tv.apple.com/fr/collection/films/uts.col.search.MV?searchTerm={$q}";
    }

    // Amazon : recherche directe
    if ($id === 10 || $id === 119) {
        return "https://www.amazon.fr/s?k={$q}";
    }

    // Tous les autres → page JustWatch spécifique au film/série
    return $jwUrl;
}

// ── Render helper ─────────────────────────────────────────────────────────────

function _render_provider_link(array $p, string $title, string $youtubeId = 'NONE', string $jwUrl = '', string $contentType = 'movie', array $userPlatformIds = [], bool $isFlatrate = false): void {
    $url  = _provider_deeplink((int)$p['provider_id'], $title, $youtubeId, $jwUrl, $contentType);
    $logo = 'https://image.tmdb.org/t/p/original' . h($p['logo_path']);
    $name = h($p['provider_name']);

    $owned = $isFlatrate && !empty($userPlatformIds) && in_array((int)$p['provider_id'], $userPlatformIds);
    echo "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\" title=\"{$name}\" class=\"provider-link" . ($owned ? ' provider-link--owned' : '') . "\">"
       . "<img src=\"{$logo}\" alt=\"{$name}\" class=\"provider-logo\">"
       . ($owned ? '<span class="provider-owned-badge">✓</span>' : '')
       . "</a>";
}

// ── renderWatchProviders (films) ──────────────────────────────────────────────

function renderWatchProviders(int $tmdbId, string $title = '', array $userPlatformIds = []): void {
    _ensure_provider_columns('movies');

    $row = db_fetch_one(
        'SELECT title, year, providers_data, youtube_id FROM movies WHERE tmdb_id = ?',
        [$tmdbId]
    );

    if (!$title && $row) $title = $row['title'] ?? '';
    $year = (int)($row['year'] ?? 0);

    $providers = null;
    if ($row && $row['providers_data'] !== null) {
        $providers = json_decode($row['providers_data'], true) ?: [];
    } else {
        $providers = _fetch_providers_from_tmdb($tmdbId);
        _save_providers_to_db($tmdbId, $providers);
    }

    $providers = _filter_providers($providers ?? []);

    if (empty($providers) || (!isset($providers['flatrate']) && !isset($providers['buy']))) {
        echo '<p class="no-providers">Non disponible en streaming actuellement.</p>';
        return;
    }

    // YouTube ID
    $youtubeId  = 'NONE';
    $allIds     = array_map(fn($p) => (int)$p['provider_id'],
                    array_merge($providers['flatrate'] ?? [], $providers['buy'] ?? []));
    if (!empty(array_intersect($allIds, [188, 192]))) {
        $youtubeId = ($row['youtube_id'] !== null)
            ? $row['youtube_id']
            : _fetch_and_cache_youtube_id($tmdbId, $title, $year, 'movies');
    }

    // URL JustWatch spécifique au film
    $jwUrl = _justwatch_url($title, 'movie');

    // Trier flatrate : possédés en premier
    if (!empty($providers['flatrate']) && !empty($userPlatformIds)) {
        usort($providers['flatrate'], fn($a, $b) =>
            in_array((int)$b['provider_id'], $userPlatformIds) <=> in_array((int)$a['provider_id'], $userPlatformIds)
        );
    }
    ?>
    <div class="watch-providers">
        <h3>OÙ REGARDER ?</h3>

        <?php if (!empty($providers['flatrate'])): ?>
            <div class="provider-section">
                <span class="provider-type">Streaming</span>
                <div class="provider-logos">
                    <?php foreach ($providers['flatrate'] as $p):
                        _render_provider_link($p, $title, $youtubeId, $jwUrl, 'movie', $userPlatformIds, true);
                    endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($providers['buy'])): ?>
            <div class="provider-section">
                <span class="provider-type">Achat / Location</span>
                <div class="provider-logos">
                    <?php foreach ($providers['buy'] as $p):
                        _render_provider_link($p, $title, $youtubeId, $jwUrl, 'movie', [], false);
                    endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="justwatch-attribution">
            <small>Données fournies par JustWatch via TMDB</small>
        </div>
    </div>
    <?php
}

// ── Helpers TMDB ─────────────────────────────────────────────────────────────

function _fetch_providers_from_tmdb(int $tmdbId): array {
    if (!defined('TMDB_API_KEY') || empty(TMDB_API_KEY)) return [];

    $url = "https://api.themoviedb.org/3/movie/{$tmdbId}/watch/providers?api_key=" . TMDB_API_KEY;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_USERAGENT => 'Moovie-App/2.0']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return [];
    $data = json_decode($response, true);
    return $data['results']['FR'] ?? [];
}

function _save_providers_to_db(int $tmdbId, array $providers): void {
    db_execute(
        'UPDATE movies SET providers_data = ?, providers_updated_at = NOW() WHERE tmdb_id = ?',
        [json_encode($providers, JSON_UNESCAPED_UNICODE), $tmdbId]
    );
}
