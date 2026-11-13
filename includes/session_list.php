<?php
/**
 * DAFTAR SESI TERAKHIR
 * Dipakai di home.php dan progress.php.
 *
 * Butuh variabel: $recentSessions (hasil getRecentSessions()).
 */
?>
<?php if (!$recentSessions): ?>
    <div class="empty-state">
        <span aria-hidden="true">✧</span>
        <strong>Your progress starts here</strong>
        <p>Complete a study session and it will show up here.</p>
    </div>
<?php else: ?>
    <ul class="session-list">
        <?php foreach ($recentSessions as $session): ?>
            <li class="session-item">
                <span class="session-symbol" aria-hidden="true">✦</span>
                <span class="session-details">
                    <strong><?= e($session['subject']) ?></strong>
                    <span><?= e($session['ended_at'] ? date('M j · g:i A', strtotime($session['ended_at'])) : 'No end time') ?></span>
                </span>
                <span class="session-duration"><?= $session['minutes'] ?>m</span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
