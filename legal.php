<?php
require_once 'functions/utils.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';

$currentUser = isset($_SESSION['user_id']) ? get_user_by_id($_SESSION['user_id']) : null;
$tab         = in_array($_GET['tab'] ?? '', ['cgu', 'confidentialite', 'cookies'])
               ? $_GET['tab']
               : 'cgu';
$lastUpdate  = '4 mai 2026';
$email       = LEGAL_CONTACT_EMAIL;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mentions légales - ADN Movie</title>
    <link rel="icon" href="/assets/Icons/Logo2.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/assets/Icons/Logo3.png">
    <meta property="og:site_name" content="ADN Movie">
    <meta property="og:type" content="website">
    <meta property="og:title" content="ADN Movie">
    <meta property="og:description" content="Découvrez vos résonances cinématographiques">
    <meta property="og:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
</head>
<body class="page-legal">
<div class="container">
    <?php renderHeader($currentUser); ?>
    <?php renderNav(''); ?>

    <main class="legal-wrap">

        <nav class="legal-tabs">
            <a href="legal.php?tab=cgu"
               class="legal-tab <?= $tab === 'cgu' ? 'active' : '' ?>">
               CGU
            </a>
            <a href="legal.php?tab=confidentialite"
               class="legal-tab <?= $tab === 'confidentialite' ? 'active' : '' ?>">
               Confidentialité &amp; RGPD
            </a>
            <a href="legal.php?tab=cookies"
               class="legal-tab <?= $tab === 'cookies' ? 'active' : '' ?>">
               Cookies
            </a>
        </nav>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- CGU                                                            -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <section class="legal-section <?= $tab === 'cgu' ? 'active' : '' ?>">

            <h1 class="legal-h1">Conditions Générales d'Utilisation</h1>
            <p class="legal-meta">Dernière mise à jour : <?= $lastUpdate ?></p>

            <div class="legal-article">
                <h2>Art. 1 — Présentation du service</h2>
                <p>ADN Movie (accessible à l'adresse <strong>adnmovie.fr</strong>) est une plateforme de découverte et de recommandation cinématographique éditée par <strong>Cédric Georgel</strong>, particulier domicilié à Strasbourg, France.</p>
                <p>Le service permet à ses utilisateurs de noter des films selon plusieurs critères, de générer un profil cinématographique personnel (« ADN »), de recevoir des recommandations personnalisées et de partager des sessions de visionnage avec d'autres membres.</p>
                <p>Contact : <a href="mailto:<?= h($email) ?>" class="legal-link"><?= h($email) ?></a></p>
            </div>

            <div class="legal-article">
                <h2>Art. 2 — Accès et inscription</h2>
                <p>L'accès au service est conditionné à la création d'un compte via un fournisseur d'identité tiers (Discord ou Google). En vous connectant, vous acceptez sans réserve les présentes CGU ainsi que les conditions d'utilisation du fournisseur d'identité choisi.</p>
                <p><strong>Âge minimum :</strong> L'utilisation du service est ouverte à toute personne âgée d'au moins 13 ans. Les utilisateurs de moins de 16 ans doivent avoir obtenu l'accord de leur représentant légal pour utiliser le service et pour le traitement de leurs données personnelles, conformément au RGPD.</p>
                <p>En vous inscrivant, vous déclarez avoir pris connaissance des présentes CGU et les accepter.</p>
            </div>

            <div class="legal-article">
                <h2>Art. 3 — Utilisation du service</h2>
                <p>En utilisant ADN Movie, vous vous engagez à :</p>
                <ul>
                    <li>Ne pas publier de contenus illicites, injurieux, diffamatoires, à caractère haineux, ou portant atteinte à des droits de tiers.</li>
                    <li>Ne pas usurper l'identité d'une autre personne.</li>
                    <li>Ne pas tenter d'accéder à des fonctionnalités ou données auxquelles vous n'avez pas accès.</li>
                    <li>Ne pas utiliser le service à des fins commerciales sans accord préalable.</li>
                    <li>Ne pas automatiser l'accès au service (bots, scrapers) sans autorisation écrite.</li>
                </ul>
                <p>Tout manquement à ces règles peut entraîner la suspension ou la suppression immédiate de votre compte.</p>
            </div>

            <div class="legal-article">
                <h2>Art. 4 — Contenus publiés par les utilisateurs</h2>
                <p>Vous restez propriétaire des contenus que vous publiez (listes, critiques, commentaires). En les publiant sur ADN Movie, vous accordez à Cédric Georgel une licence non exclusive, gratuite et mondiale pour les afficher, reproduire et distribuer dans le cadre du fonctionnement du service.</p>
                <p>ADN Movie se réserve le droit de modérer, censurer ou supprimer tout contenu contraire aux présentes CGU, sans préavis ni indemnité.</p>
                <p>Les données cinématographiques (fiches films, synopsis, affiches) proviennent de <strong>The Movie Database (TMDB)</strong>. ADN Movie ne revendique aucun droit sur ces données. <em>This product uses the TMDB API but is not endorsed or certified by TMDB.</em></p>
            </div>

            <div class="legal-article">
                <h2>Art. 5 — Propriété intellectuelle</h2>
                <p>L'ensemble du service — code source, interface, logo, design, algorithme ADN — est la propriété exclusive de Cédric Georgel et est protégé par le droit d'auteur.</p>
                <p>Toute reproduction, copie ou exploitation, partielle ou totale, sans autorisation écrite préalable est interdite.</p>
            </div>

            <div class="legal-article">
                <h2>Art. 6 — Limitation de responsabilité</h2>
                <p>ADN Movie est un service fourni en l'état, sans garantie de disponibilité permanente. Cédric Georgel ne saurait être tenu responsable :</p>
                <ul>
                    <li>des interruptions ou dysfonctionnements du service ;</li>
                    <li>des dommages directs ou indirects résultant de l'utilisation ou de l'impossibilité d'utiliser le service ;</li>
                    <li>des contenus publiés par les utilisateurs ;</li>
                    <li>des services tiers accessibles via le service (Discord, Google, TMDB).</li>
                </ul>
            </div>

            <div class="legal-article">
                <h2>Art. 8 — Suspension et suppression de compte</h2>
                <p>Vous pouvez demander la suppression de votre compte à tout moment en contactant <a href="mailto:<?= h($email) ?>" class="legal-link"><?= h($email) ?></a>. La suppression entraîne l'anonymisation de vos données personnelles (votre pseudo et votre email sont effacés, vos contributions deviennent anonymes).</p>
                <p>ADN Movie se réserve le droit de suspendre ou supprimer tout compte en cas de violation des présentes CGU, sans préavis.</p>
            </div>

            <div class="legal-article">
                <h2>Art. 9 — Modifications des CGU</h2>
                <p>Les présentes CGU peuvent être modifiées à tout moment. Les modifications entrent en vigueur dès leur publication sur cette page. En continuant à utiliser le service après modification, vous acceptez les nouvelles CGU.</p>
            </div>

            <div class="legal-article">
                <h2>Art. 10 — Droit applicable</h2>
                <p>Les présentes CGU sont soumises au droit français. En cas de litige, et à défaut de résolution amiable, les tribunaux compétents de Strasbourg seront saisis.</p>
            </div>

        </section>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- CONFIDENTIALITÉ & RGPD                                        -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <section class="legal-section <?= $tab === 'confidentialite' ? 'active' : '' ?>">

            <h1 class="legal-h1">Politique de Confidentialité &amp; RGPD</h1>
            <p class="legal-meta">Dernière mise à jour : <?= $lastUpdate ?></p>

            <div class="legal-article">
                <h2>Art. 1 — Responsable du traitement</h2>
                <p>Le responsable du traitement des données personnelles collectées via ADN Movie est :</p>
                <div class="legal-highlight">
                    <p><strong>Cédric Georgel</strong><br>
                    Strasbourg, France<br>
                    <a href="mailto:<?= h($email) ?>" class="legal-link"><?= h($email) ?></a></p>
                </div>
                <p>Pour toute question relative à vos données personnelles ou pour exercer vos droits, contactez-nous à l'adresse ci-dessus.</p>
            </div>

            <div class="legal-article">
                <h2>Art. 2 — Données collectées</h2>
                <p>ADN Movie collecte les catégories de données suivantes :</p>

                <h3>2.1 Données d'identification (compte)</h3>
                <table class="legal-table">
                    <thead><tr><th>Donnée</th><th>Source</th><th>Finalité</th></tr></thead>
                    <tbody>
                        <tr><td>Identifiant unique</td><td>Discord ou Google</td><td>Identifier votre compte de manière permanente</td></tr>
                        <tr><td>Nom d'utilisateur</td><td>Discord ou Google</td><td>Affichage public sur la plateforme</td></tr>
                        <tr><td>Adresse e-mail</td><td>Discord ou Google (si fournie)</td><td>Contact administratif, non affiché publiquement</td></tr>
                        <tr><td>Photo de profil (avatar)</td><td>Discord ou Google</td><td>Stockée localement sur nos serveurs, affichée à côté de vos contributions</td></tr>
                        <tr><td>Fournisseur d'identité</td><td>Choix lors de la connexion</td><td>Gestion de l'authentification</td></tr>
                        <tr><td>Date d'inscription, dernière connexion</td><td>Générées automatiquement</td><td>Gestion du compte</td></tr>
                    </tbody>
                </table>

                <h3>2.2 Préférences et profil cinématographique (ADN)</h3>
                <p>C'est la donnée centrale du service. En notant des films, vous alimentez votre <strong>ADN cinématographique</strong> — un profil de goûts structuré autour de huit critères : Complexité, Prévisibilité, Intensité, Malaise, Stylisation, Dynamique, Dépaysement, Cohérence.</p>
                <table class="legal-table">
                    <thead><tr><th>Donnée</th><th>Détail</th></tr></thead>
                    <tbody>
                        <tr><td>Notes de films</td><td>Scores détaillés par critère, note d'appréciation globale, date de notation, contexte (période, créneau horaire)</td></tr>
                        <tr><td>Wishlist</td><td>Films que vous souhaitez voir</td></tr>
                        <tr><td>ADN calculé (profil)</td><td>Scores moyens par critère, variance, nombre de votes, catégorie primaire/secondaire</td></tr>
                        <tr><td>Historique ADN</td><td>Snapshots de l'évolution de votre profil dans le temps (mensuel, trimestriel, annuel)</td></tr>
                        <tr><td>Plateformes de streaming</td><td>Abonnements que vous déclarez (pour filtrer les recommandations)</td></tr>
                    </tbody>
                </table>

                <h3>2.3 Données comportementales (algorithme de recommandation)</h3>
                <p>Pour améliorer la pertinence de vos recommandations, ADN Movie enregistre vos interactions avec celles-ci :</p>
                <table class="legal-table">
                    <thead><tr><th>Donnée</th><th>Détail</th></tr></thead>
                    <tbody>
                        <tr><td>Résultat d'une recommandation</td><td>Film ignoré, vu, ou aimé</td></tr>
                        <tr><td>Score de pertinence au moment de la recommandation</td><td>Valeur calculée par l'algorithme</td></tr>
                        <tr><td>Position affichée dans la liste</td><td>Pour analyser l'effet de position</td></tr>
                        <tr><td>Taux d'exploration</td><td>Proportion de recommandations « surprises » vs proches de votre ADN</td></tr>
                    </tbody>
                </table>

                <h3>2.4 Données sociales</h3>
                <table class="legal-table">
                    <thead><tr><th>Donnée</th><th>Détail</th></tr></thead>
                    <tbody>
                        <tr><td>Sessions de groupe (rooms)</td><td>Appartenance, rôle (hôte/membre), date d'adhésion</td></tr>
                        <tr><td>Messages de chat</td><td>Contenus des messages échangés dans les rooms</td></tr>
                        <tr><td>Votes et propositions de films</td><td>Films proposés et votes exprimés dans les sessions</td></tr>
                        <tr><td>Événements</td><td>Événements de visionnage créés ou rejoints</td></tr>
                    </tbody>
                </table>

                <h3>2.5 Contenus créés</h3>
                <table class="legal-table">
                    <thead><tr><th>Donnée</th><th>Détail</th></tr></thead>
                    <tbody>
                        <tr><td>Listes de films</td><td>Titre, description, films inclus, visibilité publique/privée</td></tr>
                        <tr><td>Critiques</td><td>Texte libre associé à un film, visibilité publique/privée</td></tr>
                        <tr><td>Commentaires</td><td>Commentaires sur les fiches films et sur les contenus d'autres utilisateurs</td></tr>
                    </tbody>
                </table>

                <h3>2.6 Données de sécurité</h3>
                <table class="legal-table">
                    <thead><tr><th>Donnée</th><th>Détail</th><th>Durée de conservation</th></tr></thead>
                    <tbody>
                        <tr><td>Adresse IP</td><td>Enregistrée lors de tentatives d'accès à des pages protégées</td><td>12 mois</td></tr>
                        <tr><td>Page visitée, rôle requis</td><td>Associés aux tentatives d'accès</td><td>12 mois</td></tr>
                    </tbody>
                </table>
                <p>Ces données ne sont utilisées qu'à des fins de sécurité (détection d'intrusions, abus) et ne sont jamais utilisées à des fins de profilage commercial.</p>

                <h3>2.7 Notifications et badges</h3>
                <p>Nous conservons l'historique de vos notifications (mentions, réponses, messages) et les badges que vous avez obtenus, pour les afficher dans votre profil.</p>
            </div>

            <div class="legal-article">
                <h2>Art. 3 — Finalités et bases légales du traitement</h2>
                <table class="legal-table">
                    <thead><tr><th>Finalité</th><th>Base légale (RGPD art. 6)</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>Création et gestion de votre compte, authentification</td>
                            <td><span class="legal-tag tag-blue">6.1.b</span> Exécution du contrat</td>
                        </tr>
                        <tr>
                            <td>Calcul et affichage de votre ADN cinématographique</td>
                            <td><span class="legal-tag tag-blue">6.1.b</span> Exécution du contrat — fonctionnalité principale du service</td>
                        </tr>
                        <tr>
                            <td>Génération de recommandations personnalisées</td>
                            <td><span class="legal-tag tag-blue">6.1.b</span> Exécution du contrat</td>
                        </tr>
                        <tr>
                            <td>Affichage des contenus sociaux (sessions, chat, votes)</td>
                            <td><span class="legal-tag tag-blue">6.1.b</span> Exécution du contrat</td>
                        </tr>
                        <tr>
                            <td>Journaux de sécurité (adresses IP)</td>
                            <td><span class="legal-tag tag-green">6.1.f</span> Intérêt légitime — protection contre les abus</td>
                        </tr>
                        <tr>
                            <td>Amélioration de l'algorithme de recommandation</td>
                            <td><span class="legal-tag tag-green">6.1.f</span> Intérêt légitime — amélioration du service</td>
                        </tr>

                    </tbody>
                </table>
                <p><strong>Profilage automatisé :</strong> ADN Movie effectue un traitement automatisé de vos données de notation pour calculer votre profil cinématographique et générer des recommandations. Ce traitement ne produit pas d'effet juridique sur vous. Conformément à l'article 21 du RGPD, vous avez le droit de vous opposer à ce traitement (voir Art. 6).</p>
            </div>

            <div class="legal-article">
                <h2>Art. 4 — Durée de conservation</h2>
                <table class="legal-table">
                    <thead><tr><th>Catégorie de données</th><th>Durée</th></tr></thead>
                    <tbody>
                        <tr><td>Données de compte (nom, email, avatar)</td><td>Jusqu'à la suppression / anonymisation du compte</td></tr>
                        <tr><td>Profil ADN, notes, wishlist</td><td>Jusqu'à la suppression du compte</td></tr>
                        <tr><td>Messages de chat, contenus créés</td><td>Jusqu'à la suppression du compte (anonymisés, non supprimés, pour la cohérence des échanges)</td></tr>
                        <tr><td>Journaux de sécurité (IP)</td><td>12 mois glissants</td></tr>
                        <tr><td>Données de session PHP (cookie)</td><td>30 jours d'inactivité</td></tr>
                    </tbody>
                </table>
                <p>Lors d'une demande de suppression de compte, vos données identifiantes (nom, email, avatar) sont effacées et remplacées par un identifiant anonyme. Vos contributions restent visibles de façon anonyme afin de maintenir la cohérence du service.</p>
            </div>

            <div class="legal-article">
                <h2>Art. 5 — Destinataires des données</h2>
                <p>Vos données ne sont jamais vendues à des tiers.</p>
                <p>Elles peuvent être partagées uniquement avec :</p>
                <ul>
                    <li><strong>OVH SAS</strong> (hébergeur) — serveurs situés en France, soumis au RGPD.</li>
                    <li><strong>Discord Inc.</strong> / <strong>Google LLC</strong> — uniquement lors de l'authentification OAuth. Ces sociétés sont soumises à leurs propres politiques de confidentialité.</li>
                    <li><strong>The Movie Database (TMDB)</strong> — pour récupérer les données cinématographiques. Aucune donnée personnelle n'est transmise à TMDB.</li>

                </ul>
                <p>Il n'existe aucun transfert de données vers des pays tiers en dehors de l'UE/EEE, à l'exception des données traitées par Discord (États-Unis, couvert par des clauses contractuelles types) et Google (États-Unis, couvert par le Privacy Shield / DPF).</p>
            </div>

            <div class="legal-article">
                <h2>Art. 6 — Vos droits</h2>
                <p>Conformément au RGPD, vous disposez des droits suivants :</p>
                <table class="legal-table">
                    <thead><tr><th>Droit</th><th>Ce que cela signifie concrètement</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Accès</strong> (art. 15)</td><td>Obtenir une copie de toutes les données vous concernant.</td></tr>
                        <tr><td><strong>Rectification</strong> (art. 16)</td><td>Corriger votre pseudo ou votre description depuis votre profil, ou nous contacter.</td></tr>
                        <tr><td><strong>Effacement</strong> (art. 17)</td><td>Demander la suppression / anonymisation de votre compte et de vos données identifiantes.</td></tr>
                        <tr><td><strong>Limitation</strong> (art. 18)</td><td>Demander la suspension du traitement de vos données.</td></tr>
                        <tr><td><strong>Portabilité</strong> (art. 20)</td><td>Recevoir vos données dans un format structuré et lisible par machine.</td></tr>
                        <tr><td><strong>Opposition</strong> (art. 21)</td><td>Vous opposer au profilage automatisé et aux traitements fondés sur l'intérêt légitime.</td></tr>
                    </tbody>
                </table>
                <p>Pour exercer l'un de ces droits, contactez : <a href="mailto:<?= h($email) ?>" class="legal-link"><?= h($email) ?></a>. Nous répondrons dans un délai d'un mois.</p>
                <p>Vous disposez également du droit d'introduire une réclamation auprès de la <strong>CNIL</strong> (<a href="https://www.cnil.fr" class="legal-link" target="_blank" rel="noopener">cnil.fr</a>).</p>
            </div>

            <div class="legal-article">
                <h2>Art. 7 — Hébergement et sécurité</h2>
                <p><strong>Hébergeur :</strong> OVH SAS — 2 rue Kellermann, 59100 Roubaix, France. Données hébergées en France.</p>
                <p>Les mesures de sécurité mises en place comprennent notamment :</p>
                <ul>
                    <li>Connexions chiffrées (HTTPS/TLS).</li>
                    <li>Cookies de session avec flags <code>HttpOnly</code>, <code>Secure</code> et <code>SameSite=Lax</code>.</li>
                    <li>Mots de passe non stockés (authentification déléguée à Discord et Google).</li>
                    <li>Dossier de sessions PHP isolé du répertoire web public.</li>
                    <li>Journalisation des tentatives d'accès anormales.</li>
                </ul>
                <p>Aucun système n'est infaillible. En cas de violation de données susceptible d'engendrer un risque pour vos droits et libertés, vous en serez informé dans les meilleurs délais.</p>
            </div>

        </section>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- COOKIES                                                        -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <section class="legal-section <?= $tab === 'cookies' ? 'active' : '' ?>">

            <h1 class="legal-h1">Gestion des Cookies</h1>
            <p class="legal-meta">Dernière mise à jour : <?= $lastUpdate ?></p>

            <div class="legal-article">
                <h2>Art. 1 — Qu'est-ce qu'un cookie ?</h2>
                <p>Un cookie est un petit fichier texte déposé sur votre navigateur lors de votre visite sur un site. Il permet au site de mémoriser des informations sur votre session (connexion, préférences, etc.).</p>
                <p>La réglementation applicable est l'article 82 de la loi Informatique et Libertés (transposant la directive ePrivacy) et les <a href="https://www.cnil.fr/fr/cookies-et-traceurs-que-dit-la-loi" class="legal-link" target="_blank" rel="noopener">recommandations de la CNIL</a>.</p>
            </div>

            <div class="legal-article">
                <h2>Art. 2 — Cookies utilisés par ADN Movie</h2>
                <div class="legal-highlight">
                    <p><span class="legal-tag tag-green">Actuellement</span> ADN Movie n'utilise <strong>qu'un seul cookie</strong>, strictement nécessaire au fonctionnement du service. Aucun cookie tiers, aucun cookie publicitaire, aucun traceur analytique.</p>
                </div>
                <table class="legal-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Durée</th>
                            <th>Finalité</th>
                            <th>Consentement requis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>PHPSESSID</code></td>
                            <td>Technique / Session</td>
                            <td>30 jours</td>
                            <td>Maintenir votre connexion entre les pages. Sans ce cookie, vous seriez déconnecté à chaque navigation.</td>
                            <td><span class="legal-tag tag-green">Non — exempté</span></td>
                        </tr>
                    </tbody>
                </table>
                <p>Ce cookie est <strong>exempté de consentement</strong> au sens de l'article 82 de la loi Informatique et Libertés et des recommandations CNIL, car il est strictement nécessaire à la fourniture du service que vous avez expressément demandé.</p>
                <p>Caractéristiques techniques : le cookie est protégé par les attributs <code>HttpOnly</code> (inaccessible au JavaScript), <code>Secure</code> (transmis uniquement en HTTPS en production) et <code>SameSite=Lax</code> (protection contre les attaques CSRF).</p>
            </div>

            <div class="legal-article">
                <h2>Art. 3 — Gestion de votre cookie de session</h2>
                <p>Vous pouvez à tout moment supprimer le cookie de session depuis les paramètres de votre navigateur :</p>
                <ul>
                    <li><strong>Chrome :</strong> Paramètres → Confidentialité et sécurité → Cookies et autres données de site → Afficher tous les cookies</li>
                    <li><strong>Firefox :</strong> Préférences → Vie privée et sécurité → Cookies et données de sites → Gérer les données</li>
                    <li><strong>Safari :</strong> Préférences → Confidentialité → Gérer les données de sites web</li>
                </ul>
                <p>La suppression de ce cookie entraîne votre déconnexion. Vos données et préférences restent sauvegardées sur nos serveurs et seront rechargées lors de votre prochaine connexion.</p>
            </div>


        </section>

    </main>
</div>

<?php renderFooter(); ?>
</body>
</html>
