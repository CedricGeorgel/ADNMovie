/**
 * ASSETS/SCRIPT.JS - Initialisation globale de l'interface
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. NAVIGATION MOBILE (Burger Menu) ---
    const menuToggle = document.getElementById('menuToggle');
    const navMenu = document.getElementById('navMenu');

    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = navMenu.classList.toggle('active');
            menuToggle.classList.toggle('active', isOpen);
            menuToggle.setAttribute('aria-expanded', String(isOpen));
        });

        // Fermer en cliquant en dehors
        document.addEventListener('click', (e) => {
            if (!menuToggle.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('active');
                menuToggle.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
            }
        });

        // Fermer quand on clique un lien nav
        navMenu.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                menuToggle.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // --- 2. INIT RADARS ---
    // Géré directement par radars.js via son propre DOMContentLoaded

    // --- 3. ANIMATION DES CARTES ALGO (Index) ---
    const algoCards = document.querySelectorAll('.algo-card');
    if (algoCards.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = 1;
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        algoCards.forEach(card => {
            card.style.opacity = 0;
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'all 0.6s ease-out';
            observer.observe(card);
        });
    }

    // --- 4. BARRE DE RECHERCHE ---
    const isSessionPage = document.body.classList.contains('page-session');
    const roomId = document.body.dataset.roomId;
    const searchInput = document.getElementById('movieSearchInput');
    const grid = document.getElementById('moviesGrid') || document.getElementById('sessionMoviesGrid');
    let searchTimeout;

    if (searchInput && grid) {
        const initialContent = grid.innerHTML;
        
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            clearTimeout(searchTimeout);
            
            if (query.length === 0) {
                grid.classList.remove('search-results-mode');
                grid.innerHTML = initialContent;
                return;
            }
            if (query.length < 3) return;

            searchTimeout = setTimeout(() => {
                fetch(`api/api_search.php?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        grid.classList.add('search-results-mode');
                        grid.innerHTML = data.length ? '' : '<p class="empty-state">Aucune archive trouvée...</p>';
                        data.forEach(movie => {
                            grid.innerHTML += isSessionPage ? createMovieRowSessionHTML(movie, roomId) : createMovieRowHTML(movie);
                        });
                    })
                    .catch(err => console.error("Erreur de recherche:", err));
            }, 500);
        });
    }
});