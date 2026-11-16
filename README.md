# Study Analyzer

Aplikasi web untuk melacak waktu belajar: timer fokus, target harian, streak, kalender rencana belajar, dan AI study coach.

- **Backend:** PHP (tanpa framework) + MySQL/MariaDB
- **Frontend:** HTML, CSS, dan JavaScript biasa
- **AI chat:** aplikasi React kecil di folder `ai-chat/` (memakai [Puter.js](https://docs.puter.com))

---

## Cara menjalankan (Windows + XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) lalu jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
2. Salin folder project ini ke `C:\xampp\htdocs\`, misalnya menjadi `C:\xampp\htdocs\study_analyzer`.
3. Buat database:
   - Buka <http://localhost/phpmyadmin>
   - Klik tab **Import**, pilih file `database/database.sql`, lalu klik **Go**.
4. Buka <http://localhost/study_analyzer> di browser, daftar akun, lalu login.

> Kalau MySQL kamu memakai password atau nama database lain, ubah pengaturannya di `includes/database.php`.

Halaman AI chat sudah siap pakai (sudah di-build). Langkah build di bawah hanya perlu dilakukan kalau kamu **mengubah kode** di `ai-chat/src/`.

---

## Struktur folder

```
study_analyzer/
│
├── index.php            ← Halaman pertama: form Login & Register
├── home.php             ← Dashboard: target harian, streak, saran hari ini
├── timer.php            ← Timer belajar
├── progress.php         ← Statistik belajar
├── planner.php          ← Kalender rencana belajar
├── chat.php             ← AI study chat (menampilkan ai-chat/dist di iframe)
│
├── auth/                ← Proses form login/daftar/logout (lalu redirect)
│   ├── login.php
│   ├── register.php
│   └── logout.php
│
├── api/                 ← "Server" untuk JavaScript (menerima & membalas JSON)
│   ├── timer.php        ← start / pause / resume / finish sesi
│   └── planner.php      ← tambah / hapus rencana belajar
│
├── includes/            ← Potongan PHP yang dipakai ulang
│   ├── init.php         ← Di-include di awal SEMUA file (session + database + fungsi)
│   ├── database.php     ← Koneksi MySQL  ← ubah di sini kalau password beda
│   ├── functions.php    ← Fungsi umum: e(), redirect(), requireLogin(), jsonResponse()
│   ├── study_stats.php  ← Hitung statistik: menit hari ini, streak, rata-rata, saran
│   ├── planner_data.php ← Hitung kalender & grafik untuk planner.php
│   ├── header.php       ← Bagian atas halaman dashboard (head + sidebar)
│   ├── footer.php       ← Bagian bawah halaman dashboard (menu HP + script)
│   └── session_list.php ← Daftar "Recent sessions"
│
├── assets/
│   ├── css/
│   │   ├── login.css      ← Tampilan halaman login
│   │   └── dashboard.css  ← Tampilan semua halaman dashboard
│   ├── js/
│   │   ├── api.js         ← Fungsi postJson() untuk kirim data ke folder api/
│   │   ├── login.js       ← Ganti form Login ↔ Register
│   │   ├── timer.js       ← Logika tombol & hitung mundur timer
│   │   └── planner.js     ← Tambah/hapus rencana + countdown
│   └── images/
│
├── database/
│   └── database.sql     ← Struktur tabel (import lewat phpMyAdmin)
│
└── ai-chat/             ← Project React untuk AI chat (terpisah)
    ├── src/App.jsx      ← Kode chat
    └── dist/            ← Hasil build (ini yang ditampilkan chat.php)
```

### Pola setiap halaman dashboard

Semua halaman (`home.php`, `timer.php`, dst.) punya pola yang sama:

```php
<?php
require_once __DIR__ . '/includes/init.php';        // 1. session + database
require_once __DIR__ . '/includes/study_stats.php';
requireLogin();                                     // 2. wajib login

$data = getSomething($conn, currentUserId());       // 3. ambil data

$activePage = 'timer';                              // 4. info halaman
$pageTitle = 'Study timer';
require __DIR__ . '/includes/header.php';
?>
<!-- 5. HTML khusus halaman ini -->
<?php require __DIR__ . '/includes/footer.php'; ?>
```

### Alur data timer (contoh)

```
[Klik "Start"] → assets/js/timer.js
     → postJson('api/timer.php', { action: 'start', ... })
     → api/timer.php menyimpan ke tabel study_sessions
     → membalas JSON { message: "..." }
     → timer.js memperbarui tampilan
```

---

## Database

| Tabel                   | Isi                                              |
|-------------------------|--------------------------------------------------|
| `users`                 | Akun (password disimpan dalam bentuk hash)       |
| `study_preferences`     | Target harian & durasi sesi default per user     |
| `study_sessions`        | Setiap sesi timer (running / paused / completed) |
| `study_recommendations` | Saran durasi belajar per hari                    |
| `study_plans`           | Rencana belajar di kalender                      |

Cara kolom waktu di `study_sessions` bekerja:

- `elapsed_seconds`: detik yang sudah tersimpan (diperbarui saat pause/finish)
- `running_since`: kapan sesi terakhir di-start/resume (`NULL` kalau sedang pause)
- Total waktu belajar = `elapsed_seconds` + (sekarang − `running_since`), **maksimal** sebesar `planned_seconds`

Semua perhitungan waktu memakai jam **MySQL** (`NOW()`, `CURDATE()`), bukan jam PHP, karena di XAMPP zona waktu PHP (default `Europe/Berlin`) sering berbeda dengan MySQL.

---

## Mengubah AI chat (opsional)

Butuh [Node.js](https://nodejs.org/) versi 20 atau lebih baru.

```bash
cd ai-chat
npm install      # cukup sekali
npm run dev      # mode pengembangan (buka alamat yang muncul di terminal)
npm run build    # WAJIB setelah selesai mengubah kode → memperbarui folder dist/
```

`chat.php` menampilkan isi `ai-chat/dist/`, jadi perubahan baru terlihat di aplikasi setelah `npm run build`.

---

## Deploy ke Railway (versi web)

Project ini sudah ada `Dockerfile`, jadi Railway otomatis memakai PHP 8.2 + Apache.

1. Push project ke GitHub, lalu di Railway: **New Project → Deploy from GitHub repo**.
2. Tambah database: **+ New → Database → MySQL**.
3. Di service aplikasi → **Variables**, tambah `MYSQL_URL` = `${{MySQL.MYSQL_URL}}`.
4. **Settings → Networking → Generate Domain** untuk dapat alamat `https://xxx.up.railway.app`.

Tabel database dibuat otomatis setiap server nyala (`database/install.php`). Zona waktu default
`+08:00` (Malaysia), bisa diganti lewat variabel `DB_TIMEZONE`. Folder `mobile/` tidak ikut di-deploy.

Setelah dapat alamat Railway, isi alamat itu di `mobile/webview_app/lib/main.dart` (`siteUrl`)
dan `mobile/native_app/lib/config.dart` (`serverUrl`) supaya aplikasi Android memakai server yang sama.

---

## Aplikasi Android

Folder `mobile/` berisi dua aplikasi Flutter (versi WebView dan versi native) yang memakai server ini.
API khusus aplikasi ada di `api/mobile/`. Cara build: lihat [`mobile/README.md`](mobile/README.md).
