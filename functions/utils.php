<?php
/**
 * UTILS.PHP
 */

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/core_db.php';
require_once __DIR__ . '/api_tmdb.php';
require_once __DIR__ . '/formatting.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ratings_logic.php';
require_once __DIR__ . '/../api/auth_helpers.php';
require_once __DIR__ . '/auth_role_helper.php';
require_once __DIR__ . '/../api/api_oracle.php';
start_persistent_session();

/**
 * Charge les détails complets d'un film depuis la BDD.
 * Retourne null si inexistant.
 */
function get_movie_details(int $tmdbId): ?array {
    $row = db_fetch_one('SELECT * FROM movies WHERE tmdb_id = ?', [$tmdbId]);
    if (!$row) return null;

    // Désérialise les champs JSON
    $row['genres']    = $row['genres']    ? json_decode($row['genres'],    true) : [];
    $row['cast']      = $row['cast_data'] ? json_decode($row['cast_data'], true) : [];
    return $row;
}

/**
 * Charge ou crée un film : BDD d'abord, TMDB en fallback.
 * Sauvegarde tous les détails en BDD au premier accès.
 */
function get_movie_smart(int $tmdbId): ?array {
    // 1. Cherche en BDD avec tous les détails
    $movie = db_fetch_one('SELECT * FROM movies WHERE tmdb_id = ?', [$tmdbId]);

    if ($movie) {
        // Si les détails complets sont déjà là
        if (!empty($movie['synopsis'])) {
            $movie['id']     = $movie['tmdb_id'];
            $movie['genres'] = $movie['genres']    ? json_decode($movie['genres'],    true) : [];
            $movie['cast']   = $movie['cast_data'] ? json_decode($movie['cast_data'], true) : [];

            // Poster local manquant sur disque → re-télécharge depuis TMDB et met à jour la BDD
            $posterPath = $movie['poster'] ?? '';
            if (
                $posterPath &&
                str_starts_with($posterPath, 'assets/movie_posters/') &&
                !file_exists(__DIR__ . '/../' . $posterPath)
            ) {
                $tmdbForPoster = fetch_tmdb_movie($tmdbId);
                $remoteUrl     = $tmdbForPoster['poster'] ?? null;
                if ($remoteUrl && str_starts_with($remoteUrl, 'http')) {
                    $posterDir  = __DIR__ . '/../assets/movie_posters/';
                    $posterFile = $posterDir . $tmdbId . '.jpg';
                    if (!is_dir($posterDir)) mkdir($posterDir, 0755, true);
                    $data = @file_get_contents($remoteUrl, false, stream_context_create(['http' => ['timeout' => 8]]));
                    if ($data && strlen($data) > 1000) {
                        file_put_contents($posterFile, $data);
                        // Chemin déjà correct en BDD, pas besoin de UPDATE
                    } else {
                        // Téléchargement échoué : fallback URL TMDB directe (pas de UPDATE BDD)
                        $movie['poster'] = $remoteUrl;
                    }
                }
            }

            return $movie;
        }
    }

    // 2. Fallback TMDB (film inconnu OU détails manquants)
    $isNewMovie = ($movie === null); // true uniquement si le film n'existait pas du tout
    $tmdb = fetch_tmdb_movie($tmdbId);
    if (!$tmdb) return $movie ? array_merge($movie, ['id' => $tmdbId]) : null;

    // 3. Cache local du poster
    $posterUrl = $tmdb['poster'] ?? null;
    if ($posterUrl && str_starts_with($posterUrl, 'http')) {
        $posterDir  = __DIR__ . '/../assets/movie_posters/';
        $posterFile = $posterDir . $tmdbId . '.jpg';
        if (!is_dir($posterDir)) mkdir($posterDir, 0755, true);
        if (!file_exists($posterFile)) {
            $data = @file_get_contents($posterUrl, false, stream_context_create(['http' => ['timeout' => 8]]));
            if ($data && strlen($data) > 1000) {
                file_put_contents($posterFile, $data);
                $posterUrl = 'assets/movie_posters/' . $tmdbId . '.jpg';
            }
        } else {
            $posterUrl = 'assets/movie_posters/' . $tmdbId . '.jpg';
        }
    }

    // 4. Upsert en BDD avec tous les détails
    db_execute(
        'INSERT INTO movies (tmdb_id, title, poster, year, director, runtime, synopsis, genres, cast_data, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             title     = VALUES(title),
             poster    = VALUES(poster),
             year      = VALUES(year),
             director  = VALUES(director),
             runtime   = VALUES(runtime),
             synopsis  = VALUES(synopsis),
             genres    = VALUES(genres),
             cast_data = VALUES(cast_data)',
        [
            $tmdbId,
            $tmdb['title']    ?? null,
            $posterUrl,
            $tmdb['year']     ?? null,
            $tmdb['director'] ?? null,
            $tmdb['runtime']  ?? null,
            $tmdb['synopsis'] ?? null,
            json_encode($tmdb['genres'] ?? []),
            json_encode($tmdb['cast']   ?? []),
        ]
    );

    // 5. Oracle — seulement sur un film vraiment nouveau
    if ($isNewMovie) {
        $genreNames = array_map(
            fn($g) => is_array($g) ? ($g['name'] ?? '') : $g,
            $tmdb['genres'] ?? []
        );
        apply_oracle_judgment($tmdbId, array_filter($genreNames));
    }

    return array_merge($tmdb, ['id' => $tmdbId, 'tmdb_id' => $tmdbId]);
}

/**
 * Charge ou crée une série : vérifie si le poster local est présent sur disque,
 * re-télécharge depuis TMDB si nécessaire, et upsert title/poster/year en BDD.
 *
 * À appeler depuis fiche.php (type=tv) pour garder series.poster à jour.
 * Retourne le chemin poster résolu (local si possible, URL TMDB en fallback).
 */
function sync_series_poster(int $tmdbId, string $tmdbPosterUrl): string
{
    $posterDir  = __DIR__ . '/../assets/series_posters/';
    $posterFile = $posterDir . $tmdbId . '.jpg';
    $localPath  = 'assets/series_posters/' . $tmdbId . '.jpg';

    // Fichier local déjà présent → rien à faire
    if (file_exists($posterFile)) {
        return $localPath;
    }

    // Pas de fichier → tente de télécharger depuis TMDB
    if ($tmdbPosterUrl && str_starts_with($tmdbPosterUrl, 'http')) {
        if (!is_dir($posterDir)) mkdir($posterDir, 0755, true);
        $data = @file_get_contents($tmdbPosterUrl, false, stream_context_create(['http' => ['timeout' => 8]]));
        if ($data && strlen($data) > 1000) {
            file_put_contents($posterFile, $data);
            return $localPath;
        }
    }

    // Téléchargement échoué → fallback URL TMDB directe
    return $tmdbPosterUrl ?: 'assets/no-poster.svg';
}

/**
 * Met à jour title / poster / year dans la table series depuis les données TMDB.
 * Idempotent — ne touche qu'aux champs manquants ou au poster si le fichier local est absent.
 */
function upsert_series_metadata(int $tmdbId, array $tmdbData): void
{
    $posterUrl = sync_series_poster($tmdbId, $tmdbData['poster'] ?? '');

    db_execute(
        'INSERT INTO series (tmdb_id, title, poster, year, created_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             title  = IF(title  = "" OR title  IS NULL, VALUES(title),  title),
             poster = VALUES(poster),
             year   = IF(year   IS NULL,                VALUES(year),   year)',
        [
            $tmdbId,
            $tmdbData['title'] ?? '',
            $posterUrl,
            $tmdbData['year']  ?? null,
        ]
    );
}

/**
 * Recherche hybride : BDD en premier (films + séries), TMDB en complément.
 */
function search_movies_hybrid(string $q, int $minLocal = 3): array {
    // Films locaux
    $localMovies = db_fetch_all(
        'SELECT tmdb_id AS id, title, poster, year FROM movies
         WHERE title LIKE ?
         ORDER BY total_votes DESC
         LIMIT 10',
        ['%' . $q . '%']
    );
    foreach ($localMovies as &$m) {
        $m['source']       = 'local';
        $m['content_type'] = 'movie';
    }
    unset($m);

    // Séries locales
    $localSeries = db_fetch_all(
        'SELECT tmdb_id AS id, title, poster, year FROM series
         WHERE title LIKE ?
         ORDER BY total_votes DESC
         LIMIT 5',
        ['%' . $q . '%']
    );
    foreach ($localSeries as &$s) {
        $s['source']       = 'local';
        $s['content_type'] = 'tv';
    }
    unset($s);

    $local    = array_merge($localMovies, $localSeries);
    $localIds = array_column($local, 'id');

    if (count($local) < $minLocal) {
        $tmdbResults = search_tmdb_multi($q);
        foreach ($tmdbResults as $t) {
            if (in_array($t['id'], $localIds)) continue;
            $local[] = [
                'id'           => $t['id'],
                'title'        => $t['title']        ?? null,
                'poster'       => $t['poster']        ?? null,
                'year'         => $t['year']          ?? null,
                'source'       => 'tmdb',
                'content_type' => $t['content_type']  ?? 'movie',
            ];
        }
    }

    return $local;
}

/**
 * Rendu markdown inline : bold, italic, code, ##slug, #id.
 * Reçoit du texte brut (non-escapé). Gère l'échappement en interne
 * via un système de placeholders pour éviter que les regex s'appliquent
 * sur le HTML déjà généré (ex: couleurs CSS #6ee7b7 matchées par #\d+).
 */
function inline_md(string $text): string {
    $tokens = [];
    $i = 0;
    $tok = function(string $html) use (&$tokens, &$i): string {
        $key = "\x02" . $i++ . "\x03";
        $tokens[$key] = $html;
        return $key;
    };
    $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Échappement backslash : \* \_ \` \# \/ \- \> \\ → caractère littéral
    $text = preg_replace_callback('/\\\\([*_`#\/\\-\\\\>])/', fn($m) => $tok($esc($m[1])), $text);

    // Bold **text** ou __text__
    $text = preg_replace_callback('/\*\*(.+?)\*\*/s', fn($m) =>
        $tok('<strong style="color:var(--text-main);">' . $esc($m[1]) . '</strong>'), $text);
    $text = preg_replace_callback('/__(.+?)__/s', fn($m) =>
        $tok('<strong style="color:var(--text-main);">' . $esc($m[1]) . '</strong>'), $text);

    // Italic *text* ou _text_
    $text = preg_replace_callback('/\*([^*\n]+?)\*/', fn($m) =>
        $tok('<em>' . $esc($m[1]) . '</em>'), $text);
    $text = preg_replace_callback('/_([^_\n]+?)_/', fn($m) =>
        $tok('<em>' . $esc($m[1]) . '</em>'), $text);

    // Inline code
    $text = preg_replace_callback('/`([^`]+)`/', fn($m) =>
        $tok('<code style="background:rgba(255,255,255,0.07);padding:2px 6px;border-radius:4px;font-size:0.85em;font-family:monospace;">' . $esc($m[1]) . '</code>'), $text);

    // [texte](url) → hyperlien
    $text = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/', function ($m) use ($tok, $esc) {
        $label = $esc($m[1]);
        $url   = $esc($m[2]);
        return $tok('<a href="' . $url . '" target="_blank" rel="noopener noreferrer" style="color:var(--pastel-blue);text-decoration:underline;text-underline-offset:3px;">' . $label . '</a>');
    }, $text);

    // #slug → lien archive vert  (espace/tab avant — inline_md reçoit toujours un espace préfixé)
    $text = preg_replace_callback('/([ \t])#([\w-]+)/', function ($m) use ($tok, $esc) {
        $slug  = $m[2];
        $row   = db_fetch_one('SELECT title FROM user_content WHERE slug = ? AND is_public = 1', [$slug]);
        $label = $row ? $esc($row['title']) : $slug;
        return $m[1] . $tok('<a href="content.php?slug=' . $esc($slug) . '" style="color:var(--pastel-green);font-weight:bold;">#' . $label . '</a>');
    }, $text);

    // //tv-digits → mini carte série (avant //digits et /tv-)
    $text = preg_replace_callback('/([ \t])\/\/tv-(\d+)/', function ($m) use ($tok, $esc) {
        $id  = (int)$m[2];
        $row = db_fetch_one('SELECT title, poster, year FROM series WHERE tmdb_id = ?', [$id]);
        if (!$row) return $m[1] . $tok('<a href="fiche.php?id='.$id.'&type=tv" style="color:var(--color-amber);font-weight:bold;">/Série #'.$id.'</a>');
        $poster = $esc($row['poster'] ?? '');
        $title  = $esc($row['title']  ?? '');
        $year   = $esc($row['year']   ?? '');
        $img    = $poster ? '<img src="'.$poster.'" style="width:26px;height:38px;object-fit:cover;border-radius:4px;flex-shrink:0;">' : '';
        $yr     = $year ? ' <span style="color:var(--text-dim);font-weight:400;">('.$year.')</span>' : '';
        return $m[1] . $tok(
            '<a href="fiche.php?id='.$id.'&type=tv" style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:8px;padding:4px 10px 4px 4px;text-decoration:none;color:var(--text-main);font-weight:700;font-size:0.82rem;vertical-align:middle;transition:background 0.15s;" onmouseover="this.style.background=\'rgba(255,255,255,0.08)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.04)\'">'
            .$img.'<span>'.$title.$yr.'</span></a>'
        );
    }, $text);

    // //digits → mini carte film (avant /digits)
    $text = preg_replace_callback('/([ \t])\/\/(\d+)/', function ($m) use ($tok, $esc) {
        $id  = (int)$m[2];
        $row = db_fetch_one('SELECT title, poster, year FROM movies WHERE tmdb_id = ?', [$id]);
        if (!$row) return $m[1] . $tok('<a href="fiche.php?id='.$id.'" style="color:var(--color-amber);font-weight:bold;">/Film #'.$id.'</a>');
        $poster = $esc($row['poster'] ?? '');
        $title  = $esc($row['title']  ?? '');
        $year   = $esc($row['year']   ?? '');
        $img    = $poster ? '<img src="'.$poster.'" style="width:26px;height:38px;object-fit:cover;border-radius:4px;flex-shrink:0;">' : '';
        $yr     = $year ? ' <span style="color:var(--text-dim);font-weight:400;">('.$year.')</span>' : '';
        return $m[1] . $tok(
            '<a href="fiche.php?id='.$id.'" style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:8px;padding:4px 10px 4px 4px;text-decoration:none;color:var(--text-main);font-weight:700;font-size:0.82rem;vertical-align:middle;transition:background 0.15s;" onmouseover="this.style.background=\'rgba(255,255,255,0.08)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.04)\'">'
            .$img.'<span>'.$title.$yr.'</span></a>'
        );
    }, $text);

    // /tv-digits → lien série amber (avant /digits)
    $text = preg_replace_callback('/([ \t])\/tv-(\d+)/', function ($m) use ($tok, $esc) {
        $id  = (int)$m[2];
        $row = db_fetch_one('SELECT title FROM series WHERE tmdb_id = ?', [$id]);
        $label = $row ? $esc($row['title']) : "Série #{$id}";
        return $m[1] . $tok('<a href="fiche.php?id='.$id.'&type=tv" style="color:var(--color-amber);font-weight:bold;">/'.$label.'</a>');
    }, $text);

    // /digits → lien film amber
    $text = preg_replace_callback('/([ \t])\/(\d+)/', function ($m) use ($tok, $esc) {
        $id  = (int)$m[2];
        $row = db_fetch_one('SELECT title FROM movies WHERE tmdb_id = ?', [$id]);
        $label = $row ? $esc($row['title']) : "Film #{$id}";
        return $m[1] . $tok('<a href="fiche.php?id='.$id.'" style="color:var(--color-amber);font-weight:bold;">/'.$label.'</a>');
    }, $text);

    // Échappe le texte restant (hors placeholders)
    $text = $esc($text);

    // Restaure les placeholders
    return strtr($text, $tokens);
}

/**
 * Transforme un body de contenu en HTML : paragraphes, titres, séparateurs,
 * blockquotes, listes, et inline_md pour le reste.
 * Reçoit du texte brut — l'échappement est délégué à inline_md().
 */
function render_md(string $raw): string {
    // Normalise \r\n (Windows) et \r (vieux Mac) → \n pour que le split fonctionne
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);

    // Force les titres (# ## ###) et séparateurs (--- ***) sur leur propre paragraphe
    // même si l'utilisateur n'a fait qu'un seul retour à la ligne (Shift+Enter)
    $lines  = explode("\n", $raw);
    $out2   = [];
    foreach ($lines as $line) {
        $isBlock = preg_match('/^#{1,3} /', $line) || preg_match('/^[-*_]{3,}$/', trim($line));
        if ($isBlock) {
            // Ajouter une ligne vide avant si la dernière ligne n'est pas déjà vide
            if (end($out2) !== '') $out2[] = '';
            $out2[] = $line;
            $out2[] = '';
        } else {
            $out2[] = $line;
        }
    }
    $raw = implode("\n", $out2);

    $paragraphs = preg_split('/\n{2,}/', trim($raw));
    $out = [];

    foreach ($paragraphs as $para) {
        $para = trim(str_replace("\r", '', $para));
        if ($para === '') continue;

        // Tableau Markdown : lignes débutant par |
        if (preg_match('/^\|.+\|/m', $para)) {
            $lines  = array_filter(array_map('trim', explode("\n", $para)), fn($l) => $l !== '');
            $isHead = true;
            $html   = '<div style="overflow-x:auto;margin:0;"><table style="width:100%;border-collapse:collapse;font-size:0.83rem;">';
            foreach ($lines as $line) {
                // Ligne séparateur |---|---|
                if (preg_match('/^\|[-| :]+\|$/', $line)) {
                    if ($isHead) { $html .= '</thead><tbody>'; $isHead = false; }
                    continue;
                }
                $cells = array_map('trim', explode('|', trim($line, '|')));
                if ($isHead) {
                    $html .= '<thead><tr>';
                    foreach ($cells as $cell) {
                        $html .= '<th style="padding:8px 14px;border-bottom:2px solid var(--border);color:var(--text-main);font-weight:800;font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;text-align:left;white-space:nowrap;">' . inline_md(' ' . $cell) . '</th>';
                    }
                    $html .= '</tr>';
                } else {
                    $html .= '<tr>';
                    foreach ($cells as $cell) {
                        $html .= '<td style="padding:8px 14px;border-bottom:1px solid rgba(255,255,255,0.05);color:var(--text-muted);">' . inline_md(' ' . $cell) . '</td>';
                    }
                    $html .= '</tr>';
                }
            }
            if ($isHead) $html .= '</thead>'; // tableau sans séparateur
            $html .= '</tbody></table></div>';
            $out[] = $html;
            continue;
        }

        // Séparateur --- / *** / ___
        if (preg_match('/^[-*_]{3,}$/', $para)) {
            $out[] = '<hr style="border:none;border-top:1px solid var(--border);margin:4px 0;">';
            continue;
        }

        // Titres # ## ###
        if (preg_match('/^(#{1,3}) (.+)$/', $para, $m)) {
            $level   = strlen($m[1]);
            $sizes   = [1 => '1.35rem', 2 => '1.1rem', 3 => '0.95rem'];
            $weights = [1 => '900',     2 => '800',     3 => '700'];
            $out[]   = '<p style="font-size:' . $sizes[$level] . ';font-weight:' . $weights[$level] . ';color:var(--text-main);margin:0;">' . inline_md(' ' . $m[2]) . '</p>';
            continue;
        }

        // Blockquote > texte
        if (str_starts_with($para, '>')) {
            $lines = explode("\n", $para);
            $inner = implode('<br>', array_map(
                fn($l) => inline_md(' ' . ltrim(ltrim($l, '>'), ' ')),
                $lines
            ));
            $out[] = '<blockquote style="border-left:3px solid var(--border);padding:6px 0 6px 16px;margin:0;color:var(--text-dim);font-style:italic;">' . $inner . '</blockquote>';
            continue;
        }

        // Liste - item ou * item (avec sous-listes indentées)
        if (preg_match('/^[ \t]*[-*] /m', $para)) {
            $items    = '';
            $inSub    = false;
            foreach (explode("\n", $para) as $line) {
                $indent = strlen($line) - strlen(ltrim($line));
                $isSub  = $indent >= 2;
                if (preg_match('/^[ \t]*[-*] (.+)$/', $line, $m)) {
                    if ($isSub && !$inSub) { $items .= '<ul style="padding-left:18px;margin:4px 0;">'; $inSub = true; }
                    if (!$isSub && $inSub) { $items .= '</ul>'; $inSub = false; }
                    $items .= '<li style="margin-bottom:4px;">' . inline_md(' ' . $m[1]) . '</li>';
                }
            }
            if ($inSub) $items .= '</ul>';
            $out[] = '<ul style="padding-left:20px;margin:0;color:var(--text-muted);">' . $items . '</ul>';
            continue;
        }

        // Paragraphe normal — sauts de ligne simples → <br>
        $lines = explode("\n", $para);
        $html  = implode('<br>', array_map(fn($l) => inline_md(' ' . trim($l)), $lines));
        $out[] = '<p style="margin:0;color:var(--text-muted);">' . $html . '</p>';
    }

    return implode('<div style="height:1em;"></div>', $out);
}