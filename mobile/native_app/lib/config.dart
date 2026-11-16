/// Pengaturan aplikasi.
///
/// Aplikasi ini berdiri sendiri: data belajar disimpan di HP (lihat local_backend.dart),
/// jadi tidak butuh komputer, XAMPP, atau alamat server.
///
/// Halaman AI chat (folder assets/ai-chat, hasil build dari ai-chat/dist) dibuka lewat
/// server kecil di dalam aplikasi pada http://localhost. Puter.js butuh alamat
/// "localhost" atau https, jadi halaman ini tidak bisa dibuka langsung dari file.
/// Port jangan diganti-ganti: login Puter tersimpan per alamat (termasuk port).
const int chatServerPort = 8765;
const String chatUrl = 'http://localhost:$chatServerPort/index.html';
