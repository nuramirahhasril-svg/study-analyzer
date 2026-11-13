/**
 * Halaman login (index.php): pindah antara form Login dan form Register.
 * Dipanggil dari link "Register" / "Login" lewat onclick="showForm('...')".
 */
function showForm(formId) {
    document.querySelectorAll('.form-box').forEach(function (form) {
        form.classList.remove('active');
    });
    document.getElementById(formId).classList.add('active');
}
