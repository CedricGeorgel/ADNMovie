<?php
/**
 * FUNCTIONS/ACHIEVEMENTS_LOGIC.PHP
 * Le cerveau qui analyse le comportement pour délivrer les accréditations.
 */

require_once __DIR__ . '/../functions/core_db.php';

/**
 * Vérifie et attribue les badges mérités à un utilisateur.
 *
 * @param string $userId
 * @return array  Liste des nouveaux badges attribués
 */
function check_and_award_achievements(string $userId): array {

    $existing = db_fetch_all(
        'SELECT badge_id FROM user_badges WHERE user_id = ?',
        [$userId]
    );
    $ownedBadges = array_column($existing, 'badge_id');

    $user = db_fetch_one(
        'SELECT avatar, username, created_at, is_description_modified FROM users WHERE id = ?',
        [$userId]
    );

    $totalAnalyses = (int) db_fetch_one(
        'SELECT COUNT(*) AS cnt FROM ratings WHERE user_id = ?',
        [$userId]
    )['cnt'];

    $newBadges = [];

    $award = function(string $badgeId) use ($userId, &$ownedBadges, &$newBadges): void {
        if (in_array($badgeId, $ownedBadges)) return;
        db_execute(
            'INSERT IGNORE INTO user_badges (user_id, badge_id, earned_at)
             VALUES (?, ?, NOW())',
            [$userId, $badgeId]
        );
        $ownedBadges[] = $badgeId;
        $newBadges[]   = $badgeId;
    };

    // ── IDENTITÉ & HISTORIQUE ─────────────────────────────────────────────────
    if (!in_array('EARLY_ADOPTER', $ownedBadges)) {
        $rank = (int) db_fetch_one(
            'SELECT COUNT(*) AS `rank` FROM users WHERE created_at <= ?',
            [$user['created_at']]
        )['rank'];
        if ($rank <= 50) $award('EARLY_ADOPTER');
    }

    $hasCustomAvatar = !empty($user['avatar']) && strpos($user['avatar'], 'assets/avatars/') !== false;
    if ($hasCustomAvatar && !empty($user['username']) && !empty($user['is_description_modified'])) {
        $award('IDENTIFIED');
    }

    // ── ANOMALIE X3 ───────────────────────────────────────────────────────────
    // ATTENTION : Requiert la création de la colonne `anomaly_visits` dans `users`
    if (!in_array('ANOMALY_X3', $ownedBadges)) {
        $visits = db_fetch_one('SELECT anomaly_visits FROM users WHERE id = ?', [$userId]);
        if ($visits && (int)$visits['anomaly_visits'] >= 3) {
            $award('ANOMALY_X3');
        }
    }

    // ── PALIERS DE VOLUME ─────────────────────────────────────────────────────
    if ($totalAnalyses >= 1)   $award('NOVICE');
    if ($totalAnalyses >= 25)  $award('VOL_25');
    if ($totalAnalyses >= 50)  $award('ARCHIVIST');
    if ($totalAnalyses >= 100) $award('MASS_ANALYST');
    if ($totalAnalyses >= 150) $award('VOL_100_LIKES');
    if ($totalAnalyses >= 200) $award('VOL_200_SEEN');

    // ── SESSIONS & MULTIJOUEUR ────────────────────────────────────────────────
    if (!in_array('HOST_CLUSTER', $ownedBadges)) {
        $hosted = (int) db_fetch_one('SELECT COUNT(*) AS cnt FROM rooms WHERE host_id = ?', [$userId])['cnt'];
        if ($hosted >= 2) $award('HOST_CLUSTER');
    }

    if (!in_array('SOCIAL_UNIT', $ownedBadges)) {
        $joined = (int) db_fetch_one('SELECT COUNT(*) AS cnt FROM room_members WHERE user_id = ?', [$userId])['cnt'];
        if ($joined >= 10) $award('SOCIAL_UNIT');
    }

    if (!in_array('STOWAWAY', $ownedBadges)) {
        $clandestin = db_fetch_one(
            'SELECT 1 FROM room_members rm
             WHERE rm.user_id = ?
             AND NOT EXISTS (SELECT 1 FROM room_votes rv WHERE rv.room_id = rm.room_id AND rv.user_id = ?)
             AND EXISTS (SELECT 1 FROM room_proposals rp WHERE rp.room_id = rm.room_id)
             LIMIT 1',
            [$userId, $userId]
        );
        if ($clandestin) $award('STOWAWAY');
    }

    // ── ALGORITHME & CONSENSUS ────────────────────────────────────────────────
    if (!in_array('CONSENSUS', $ownedBadges)) {
        $consensus = db_fetch_one(
            'SELECT 1 FROM ratings r JOIN room_proposals rp ON rp.movie_id = r.movie_id WHERE r.user_id = ? LIMIT 1',
            [$userId]
        );
        if ($consensus) $award('CONSENSUS');
    }

    if (!in_array('POLARIZED', $ownedBadges) || !in_array('MASTER_CONSENSUS', $ownedBadges)) {
        $polarStats = db_fetch_one(
            'SELECT 
                SUM(CASE WHEN m.polarization_index > 1.5 THEN 1 ELSE 0 END) as polar_count,
                SUM(CASE WHEN m.polarization_index < 0.5 THEN 1 ELSE 0 END) as consensus_count
             FROM ratings r JOIN movies m ON m.tmdb_id = r.movie_id
             WHERE r.user_id = ? AND r.is_liked = 1',
            [$userId]
        );
        if (($polarStats['polar_count'] ?? 0) >= 5) $award('POLARIZED');
        if (($polarStats['consensus_count'] ?? 0) >= 5) $award('MASTER_CONSENSUS');
    }

    if (!in_array('CHAOS_EXPLORER', $ownedBadges)) {
        $chaos = db_fetch_one(
            'SELECT 1 FROM recommendation_feedback rf JOIN ratings r ON r.movie_id = rf.movie_id AND r.user_id = rf.user_id WHERE rf.user_id = ? AND rf.is_exploration = 1 LIMIT 1',
            [$userId]
        );
        if ($chaos) $award('CHAOS_EXPLORER');
    }

    if (!in_array('PERFECT_RESONANCE', $ownedBadges)) {
        $resonance = db_fetch_one(
            'SELECT 1 FROM recommendation_feedback rf JOIN ratings r ON r.movie_id = rf.movie_id AND r.user_id = rf.user_id WHERE rf.user_id = ? AND rf.score_at_reco >= 99 LIMIT 1',
            [$userId]
        );
        if ($resonance) $award('PERFECT_RESONANCE');
    }

    // ── TEMPORALITÉ & SAISONS ─────────────────────────────────────────────────
    if ($totalAnalyses >= 10 && (!in_array('NIGHT_OWL', $ownedBadges) || !in_array('EARLY_BIRD', $ownedBadges))) {
        $timeStats = db_fetch_one(
            'SELECT 
                SUM(CASE WHEN HOUR(rated_at) >= 22 OR HOUR(rated_at) < 4 THEN 1 ELSE 0 END) as night_count,
                SUM(CASE WHEN HOUR(rated_at) BETWEEN 4 AND 9 THEN 1 ELSE 0 END) as morning_count
             FROM ratings WHERE user_id = ?',
            [$userId]
        );
        if (($timeStats['night_count'] / $totalAnalyses) > 0.5) $award('NIGHT_OWL');
        if (($timeStats['morning_count'] / $totalAnalyses) > 0.5) $award('EARLY_BIRD');
    }

    if (!in_array('GHOST_SYSTEM', $ownedBadges)) {
        $ageInMonths = (int) db_fetch_one('SELECT TIMESTAMPDIFF(MONTH, created_at, NOW()) AS months FROM users WHERE id = ?', [$userId])['months'];
        if ($ageInMonths >= 6) {
            $recentActivity = (int) db_fetch_one('SELECT COUNT(*) AS cnt FROM ratings WHERE user_id = ? AND rated_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)', [$userId])['cnt'];
            if ($recentActivity >= 10) $award('GHOST_SYSTEM');
        }
    }

    $seasonalData = db_fetch_all(
        "SELECT 
            YEAR(rated_at) as y,
            CASE 
                WHEN MONTH(rated_at) IN (12, 1, 2) THEN 'WINTER'
                WHEN MONTH(rated_at) IN (3, 4, 5) THEN 'SPRING'
                WHEN MONTH(rated_at) IN (6, 7, 8) THEN 'SUMMER'
                WHEN MONTH(rated_at) IN (9, 10, 11) THEN 'AUTUMN'
            END as season,
            COUNT(*) as cnt
         FROM ratings WHERE user_id = ? GROUP BY y, season HAVING cnt >= 40",
        [$userId]
    );

    foreach ($seasonalData as $seasonRow) {
        if ($seasonRow['season'] === 'WINTER') $award('SEASON_WINTER');
        if ($seasonRow['season'] === 'SPRING') $award('SEASON_SPRING');
        if ($seasonRow['season'] === 'SUMMER') $award('SEASON_SUMMER');
        if ($seasonRow['season'] === 'AUTUMN') $award('SEASON_AUTUMN');
    }

    // ── ADN : TRAITS DE CARACTÈRE (+9 / -9) ───────────────────────────────────
    if ($totalAnalyses >= 5) {
        $dnaRows = db_fetch_all('SELECT criterio, avg_score FROM user_dna WHERE user_id = ?', [$userId]);
        $dna = array_column($dnaRows, 'avg_score', 'criterio');

        if (($dna['complexite'] ?? 0) >= 9)  $award('DNA_COMPLEX_HIGH');
        if (($dna['complexite'] ?? 0) <= -9) $award('DNA_COMPLEX_LOW');

        if (($dna['vraissemblance'] ?? 0) >= 9)  $award('DNA_REAL_HIGH');
        if (($dna['vraissemblance'] ?? 0) <= -9) $award('DNA_REAL_LOW');

        if (($dna['effroi'] ?? 0) >= 9)  $award('DNA_FEAR_HIGH');
        if (($dna['effroi'] ?? 0) <= -9) $award('DNA_FEAR_LOW');

        if (($dna['aventure'] ?? 0) >= 9)  $award('DNA_ADV_HIGH');
        if (($dna['aventure'] ?? 0) <= -9) $award('DNA_ADV_LOW');

        if (($dna['rythme'] ?? 0) >= 9)  $award('ADRENALINE');
        if (($dna['rythme'] ?? 0) <= -9) $award('DNA_PACE_LOW');

        if (($dna['suspense'] ?? 0) >= 9)  $award('DNA_SUSP_HIGH');
        if (($dna['suspense'] ?? 0) <= -9) $award('DNA_SUSP_LOW');

        if (($dna['vibe'] ?? 0) >= 9)  $award('DNA_VIBE_HIGH');
        if (($dna['vibe'] ?? 0) <= -9) $award('DNA_VIBE_LOW');

        if (($dna['esthetique'] ?? 0) >= 9)  $award('ESTHETE');
        if (($dna['esthetique'] ?? 0) <= -9) $award('DNA_AES_LOW');

        if (($dna['sentiment'] ?? 0) >= 9)  $award('DNA_FEEL_HIGH');
        if (($dna['sentiment'] ?? 0) <= -9) $award('DNA_FEEL_LOW');
    }

    return $newBadges;
}