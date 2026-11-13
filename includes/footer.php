<?php
/**
 * BAGIAN BAWAH HALAMAN DASHBOARD
 * Menutup <main>, menampilkan menu bawah (untuk HP), dan memuat JavaScript.
 *
 * Variabel opsional yang bisa di-set oleh halaman:
 *   $pageScript → nama file JS khusus halaman ini di assets/js/, contoh: 'timer.js'
 *   $jsData     → data tambahan dari PHP untuk JavaScript (dibaca lewat window.studyAnalyzer)
 */
?>
            <footer class="footer-note">Keep going, you can do everything.</footer>
        </main>

        <!-- ===== MENU BAWAH (tampil di HP) ===== -->
        <nav class="bottom-nav" aria-label="Main navigation">
            <?php foreach ($menuItems as $menuPage => $menu): ?>
                <a class="bottom-nav-link <?= $menuPage === $activePage ? 'active' : '' ?>" href="<?= $menuPage ?>.php" <?= $menuPage === $activePage ? 'aria-current="page"' : '' ?>><span class="bottom-nav-icon" aria-hidden="true"><?= $menu['icon'] ?></span><span><?= e($menu['shortLabel']) ?></span></a>
            <?php endforeach; ?>
        </nav>
    </div>

    <?php if (isset($pageScript)): ?>
        <script>
            // Data dari PHP yang dibutuhkan JavaScript halaman ini.
            window.studyAnalyzer = <?= json_encode(
                ['csrfToken' => $csrfToken] + ($jsData ?? []),
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ) ?>;
        </script>
        <script src="<?= e(asset('assets/js/api.js')) ?>"></script>
        <script src="<?= e(asset('assets/js/' . $pageScript)) ?>"></script>
    <?php endif; ?>
</body>
</html>
