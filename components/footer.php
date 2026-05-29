<?php
/**
 * FOOTER.PHP - Pied de page minimaliste
 * Les scripts sont chargés via renderScripts() dans header.php — pas ici.
 */
function renderFooter() {
    ?>
    <footer class="main-footer">
        <div class="container">
            <div class="footer-no_hover">
                <span class="footer-copy">© <?= date('Y') ?> Projet passionné</span>

                <div class="footer-minimal">
                    <a class="footer-no_hover" href="legal.php?tab=cgu">CGU</a>
                    <span style="color:var(--border);margin:0 6px;">·</span>
                    <a class="footer-no_hover" href="legal.php?tab=confidentialite">Confidentialité</a>
                    <span style="color:var(--border);margin:0 6px;">·</span>
                    <a class="footer-no_hover" href="legal.php?tab=cookies">Cookies</a>
                    <span style="color:var(--border);margin:0 6px;">·</span>
                    <a class="footer-no_hover" href="report.php">Creer un ticket</a>
                </div>
            </div>
        </div>
    </footer>
    <?php
}
?>