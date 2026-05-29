<?php
/**
 * FUNCTIONS/MODERATION.PHP
 * Censure automatique des gros mots dans les commentaires.
 * La liste est lue depuis config/censored_words.json.
 */

/**
 * Retourne la liste des mots censurés depuis le fichier JSON.
 * Résultat mis en cache statique pour la durée de la requête.
 *
 * @return string[]
 */
function get_censored_words(): array {
    static $words = null;
    if ($words !== null) return $words;

    $path = __DIR__ . '/../config/censored_words.json';
    if (!file_exists($path)) return $words = [];

    $decoded = json_decode(file_get_contents($path), true);
    $words   = is_array($decoded) ? array_values(array_filter($decoded, 'strlen')) : [];
    return $words;
}

/**
 * Censure les mots vulgaires d'un texte.
 *
 * @return array{content: string, was_censored: bool, original: string}
 */
function censor_content(string $text): array {
    $original    = $text;
    $wasCensored = false;

    $words = get_censored_words();
    if (empty($words)) return ['content' => $text, 'was_censored' => false, 'original' => $text];

    // Trier par longueur décroissante : les expressions multi-mots passent avant les mots seuls
    usort($words, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

    foreach ($words as $word) {
        $escaped = preg_quote(trim($word), '/');
        // \b ne fonctionne pas sur les chars Unicode — délimiteurs manuels
        $pattern  = '/(?<![a-zA-ZÀ-ÿ0-9])' . $escaped . '(?![a-zA-ZÀ-ÿ0-9])/iu';
        $replaced = preg_replace_callback($pattern, function ($m) use (&$wasCensored) {
            $wasCensored = true;
            $chars = ['*', '%', '#', '@', '&', '!'];
            $out   = '';
            $len   = mb_strlen($m[0]);
            for ($i = 0; $i < $len; $i++) {
                $out .= $chars[$i % count($chars)];
            }
            return $out;
        }, $text);
        if ($replaced !== null) $text = $replaced;
    }

    return [
        'content'      => $text,
        'was_censored' => $wasCensored,
        'original'     => $original,
    ];
}
