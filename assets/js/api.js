/**
 * Kirim data (JSON) ke file PHP di folder api/ dan kembalikan balasannya.
 *
 * Contoh:
 *   const result = await postJson('api/timer.php', { action: 'pause' }, 'Gagal pause.');
 *
 * Kalau server membalas dengan error, fungsi ini melempar Error berisi pesan dari server
 * (atau fallbackError kalau server tidak mengirim pesan). Tangkap dengan try/catch.
 */
async function postJson(url, data, fallbackError) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            // Token CSRF dari PHP (lihat includes/footer.php), dicek oleh server.
            'X-CSRF-Token': window.studyAnalyzer.csrfToken
        },
        body: JSON.stringify(data)
    });

    // Kalau balasan bukan JSON (misalnya PHP error), anggap sebagai objek kosong.
    const result = await response.json().catch(function () {
        return {};
    });

    if (!response.ok) {
        throw new Error(result.error || fallbackError);
    }
    return result;
}
