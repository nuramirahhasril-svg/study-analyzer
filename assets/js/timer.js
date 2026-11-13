/**
 * Halaman timer (timer.php).
 *
 * Alur:
 *   Start  → kirim "start" ke api/timer.php, timer mulai berjalan
 *   Pause  → kirim "pause"  | Resume → kirim "resume"
 *   Finish → kirim "finish" (otomatis juga saat waktu habis), lalu halaman di-reload
 *
 * Server yang menyimpan waktu sebenarnya. JavaScript hanya menampilkan hitung mundur.
 */
(function () {
    const minutesDisplay = document.getElementById('timer-minutes');
    const secondsDisplay = document.getElementById('timer-seconds');
    const accessibleDisplay = document.getElementById('timer-display');
    const caption = document.getElementById('timer-caption');
    const message = document.getElementById('timer-message');
    const startButton = document.getElementById('start-button');
    const finishButton = document.getElementById('finish-button');
    const subjectInput = document.getElementById('subject');
    const durationInput = document.getElementById('duration');

    // Sesi aktif dari PHP (null kalau tidak ada). Isinya: subject, planned_seconds, elapsed_seconds, status.
    let session = window.studyAnalyzer.activeSession;
    let busy = false;            // true selama menunggu balasan server
    let completionSent = false;  // supaya "finish" otomatis hanya dikirim sekali

    /** Berapa detik sesi sudah berjalan (perkiraan di browser). */
    function elapsedSeconds() {
        if (!session) {
            return 0;
        }
        const saved = Number(session.elapsed_seconds) || 0;
        if (session.status !== 'running') {
            return saved;
        }
        // clientStartedAt = kapan browser mulai menghitung (saat halaman dibuka / start / resume).
        return saved + Math.max(0, Math.floor((Date.now() - session.clientStartedAt) / 1000));
    }

    function setMessage(text, isError) {
        message.textContent = text || '';
        message.classList.toggle('error', Boolean(isError));
    }

    /** Tampilkan angka MM:SS. */
    function renderTime(totalSeconds) {
        const seconds = Math.max(0, Math.floor(totalSeconds));
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        minutesDisplay.textContent = String(minutes).padStart(2, '0');
        secondsDisplay.textContent = String(remainingSeconds).padStart(2, '0');
        accessibleDisplay.textContent = minutes + ' minutes ' + remainingSeconds + ' seconds';
    }

    /** Update seluruh tampilan timer. Dipanggil 4x per detik. */
    function render() {
        if (!session) {
            renderTime(0);
            startButton.textContent = 'Start focusing';
            startButton.disabled = busy;
            finishButton.hidden = true;
            finishButton.disabled = busy;
            caption.textContent = 'One focused session at a time. You’ve got this.';
            subjectInput.disabled = false;
            durationInput.disabled = false;
            return;
        }

        const remaining = Math.max(0, session.planned_seconds - elapsedSeconds());
        renderTime(remaining);
        startButton.textContent = session.status === 'paused' ? 'Resume session' : 'Pause session';
        startButton.disabled = busy;
        finishButton.hidden = false;
        finishButton.disabled = busy;
        subjectInput.disabled = true;
        durationInput.disabled = true;
        caption.textContent = session.subject + (session.status === 'paused' ? ' · Session paused' : ' · Stay with it');

        // Waktu habis → selesaikan sesi otomatis.
        if (remaining === 0 && session.status === 'running' && !completionSent) {
            completionSent = true;
            updateSession('finish');
        }
    }

    /** Kirim action (start/pause/resume/finish) ke server, lalu update tampilan. */
    async function updateSession(action, details) {
        busy = true;
        startButton.disabled = true;
        finishButton.disabled = true;
        setMessage('');

        try {
            const result = await postJson(
                'api/timer.php',
                Object.assign({ action: action }, details || {}),
                'The timer could not be updated.'
            );

            if (action === 'start') {
                session = result.session;
                session.clientStartedAt = Date.now();
                completionSent = false;
            } else if (action === 'pause') {
                session.elapsed_seconds = elapsedSeconds();
                session.status = 'paused';
            } else if (action === 'resume') {
                session.status = 'running';
                session.clientStartedAt = Date.now();
                completionSent = false;
            } else if (action === 'finish') {
                session = null;
                // Reload sebentar lagi supaya saran & statistik ikut diperbarui.
                window.setTimeout(function () {
                    window.location.reload();
                }, 700);
            }
            setMessage(result.message, false);
        } catch (error) {
            completionSent = false;
            setMessage(error.message, true);
        } finally {
            busy = false;
            render();
        }
    }

    /* ---------- Tombol ---------- */
    startButton.addEventListener('click', function () {
        if (busy) {
            return;
        }
        // Sudah ada sesi → tombol ini berfungsi sebagai Pause / Resume.
        if (session) {
            updateSession(session.status === 'running' ? 'pause' : 'resume');
            return;
        }

        // Belum ada sesi → cek input lalu mulai.
        const subject = subjectInput.value.trim();
        const minutes = Number(durationInput.value);
        if (!subject) {
            setMessage('Add a subject before starting your session.', true);
            subjectInput.focus();
            return;
        }
        if (!Number.isInteger(minutes) || minutes < 1 || minutes > 120) {
            setMessage('Choose a duration between 1 and 120 minutes.', true);
            durationInput.focus();
            return;
        }
        updateSession('start', { subject: subject, duration_seconds: minutes * 60 });
    });

    finishButton.addEventListener('click', function () {
        if (session && !busy) {
            updateSession('finish');
        }
    });

    /* ---------- Mulai ---------- */
    if (session) {
        session.clientStartedAt = Date.now();
    }
    render();
    window.setInterval(render, 250);
})();
