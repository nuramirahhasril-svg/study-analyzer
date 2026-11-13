/**
 * Halaman planner (planner.php):
 *   1. Form "Add a study plan"  → kirim ke api/planner.php (action: create)
 *   2. Tombol × di setiap rencana → kirim ke api/planner.php (action: delete)
 *   3. Countdown live untuk rencana yang punya jam
 */
(function () {
    const form = document.getElementById('planner-form');
    const message = document.getElementById('planner-message');
    const saveButton = document.getElementById('save-plan-button');

    function showMessage(text, isError) {
        message.textContent = text || '';
        message.classList.toggle('error', Boolean(isError));
    }

    /* ---------- 1. Tambah rencana ---------- */
    form.addEventListener('submit', async function (event) {
        event.preventDefault(); // jangan reload halaman, kita kirim lewat fetch
        saveButton.disabled = true;
        showMessage('');

        const planDate = document.getElementById('plan-date').value;
        try {
            await postJson('api/planner.php', {
                action: 'create',
                title: document.getElementById('plan-title').value.trim(),
                date: planDate,
                time: document.getElementById('plan-time').value,
                notes: document.getElementById('plan-notes').value.trim()
            }, 'The study plan could not be saved.');

            // Berhasil → buka kalender di tanggal rencana tersebut.
            const nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('date', planDate);
            window.location.assign(nextUrl.toString());
        } catch (error) {
            showMessage(error.message, true);
            saveButton.disabled = false;
        }
    });

    /* ---------- 2. Hapus rencana ---------- */
    document.querySelectorAll('[data-delete-plan]').forEach(function (button) {
        button.addEventListener('click', async function () {
            if (button.disabled) {
                return;
            }
            button.disabled = true;
            showMessage('');

            try {
                await postJson('api/planner.php', {
                    action: 'delete',
                    id: Number(button.dataset.deletePlan)
                }, 'The study plan could not be removed.');
                window.location.reload();
            } catch (error) {
                showMessage(error.message, true);
                button.disabled = false;
            }
        });
    });

    /* ---------- 3. Countdown ---------- */
    // Setiap elemen dengan atribut data-countdown="2026-11-13T07:30:00+00:00" diupdate tiap detik.
    document.querySelectorAll('[data-countdown]').forEach(function (countdown) {
        const targetTime = new Date(countdown.dataset.countdown).getTime();
        if (!Number.isFinite(targetTime)) {
            countdown.textContent = 'Time unavailable';
            return;
        }

        function updateCountdown() {
            const totalSeconds = Math.max(0, Math.floor((targetTime - Date.now()) / 1000));
            if (totalSeconds === 0) {
                countdown.textContent = 'Starting now';
                return;
            }
            const days = Math.floor(totalSeconds / 86400);
            const hours = Math.floor((totalSeconds % 86400) / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;

            // Lebih dari 1 hari → "2d 3h 15m", kurang dari 1 hari → "03:15:42"
            countdown.textContent = days > 0
                ? days + 'd ' + hours + 'h ' + minutes + 'm'
                : String(hours).padStart(2, '0') + ':' +
                    String(minutes).padStart(2, '0') + ':' +
                    String(seconds).padStart(2, '0');
        }

        updateCountdown();
        window.setInterval(updateCountdown, 1000);
    });
})();
