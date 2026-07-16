<?php
/**
 * COMPONENTS/NAV.PHP - Menu avec support Burger Mobile
 */
function renderNav($activePage = 'home') {
    // 1. On vérifie si l'utilisateur est connecté via la session
    $isLoggedIn = isset($_SESSION['user_id']);

    // 2. Définition de base du menu (toujours visible)
    $menu = [
        'home' => ['label' => 'Découvrir', 'url' => 'home.php'],
    ];

    if ($isLoggedIn) {
        //$menu['communaute']      = ['label' => 'Communauté',     'url' => 'communaute.php'];
        $menu['rooms']           = ['label' => 'Sessions',       'url' => 'rooms.php'];
        $menu['recommendations'] = ['label' => 'Séquençage ADN', 'url' => 'recommendations.php'];
        $menu['mood']            = ['label' => 'Ce soir...',     'url' => 'mood.php'];
    }

    $menu['Archives'] = ['label' => 'Archives', 'url' => 'archives.php'];Ò
    ?>
    <nav class="main-nav" style="position: relative;">
        <div class="nav-container">
            <button class="menu-toggle" id="menuToggle" aria-label="Ouvrir le menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>

            <div class="nav-menu" id="navMenu">
                <?php foreach ($menu as $id => $item): ?>
                    <a href="<?= h($item['url']) ?>"
                       class="nav-link <?= $activePage === $id ? 'active' : '' ?>">
                        <?= h($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>
    <?php
}