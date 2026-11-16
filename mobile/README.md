# Study Analyzer — Aplikasi Android (Flutter)

Ada **dua** aplikasi di folder ini:

| Folder | Isinya | Kelebihan |
|---|---|---|
| `webview_app/` | Website Study Analyzer dibungkus jadi aplikasi (WebView) | Sangat sederhana; perubahan di website langsung ikut berubah |
| `native_app/` | Aplikasi Flutter asli: Login, Home, Timer, Progress, Planner, AI Chat | Tampilan & rasa aplikasi asli; memakai API `api/mobile/` |

Keduanya tetap butuh **server PHP + MySQL** (XAMPP atau hosting), karena data disimpan di sana.

> Folder `mobile/` tidak perlu di-upload ke hosting. Yang perlu di-upload hanya file website (termasuk folder `api/mobile/`).

---

## 1. Install Flutter (sekali saja)

1. Install **Android Studio**: <https://developer.android.com/studio>. Buka sekali, biarkan dia men-download Android SDK.
2. Install **Flutter**: ikuti <https://docs.flutter.dev/get-started/install/windows/mobile>.
3. Cek di terminal baru:
   ```
   flutter doctor
   flutter doctor --android-licenses
   ```
   Perbaiki semua tanda ✗ di bagian *Flutter* dan *Android toolchain*.

## 2. Siapkan database

Aplikasi native butuh tabel baru `api_tokens`. Import ulang `database/database.sql` di phpMyAdmin
(aman, data lama tidak terhapus karena semua tabel memakai `CREATE TABLE IF NOT EXISTS`).

## 3. Buat folder Android

Dari folder `mobile/`:

```powershell
powershell -ExecutionPolicy Bypass -File setup.ps1
```

Script ini menjalankan `flutter create` (membuat folder `android/`), menambahkan izin internet, lalu `flutter pub get`.

## 4. Atur alamat server

- `webview_app/lib/main.dart` → `siteUrl`
- `native_app/lib/config.dart` → `serverUrl`

| Kamu tes pakai | Alamatnya |
|---|---|
| Emulator Android + XAMPP | `http://10.0.2.2/study_analyzer` |
| HP asli + XAMPP (Wi-Fi yang sama dengan PC) | `http://<IP-PC>/study_analyzer` (cek IP dengan `ipconfig`, contoh `192.168.1.10`). Mungkin perlu mengizinkan Apache di Windows Firewall. |
| Sudah di-hosting | `https://namasitusmu.com` |

## 5. Jalankan

Sambungkan HP (aktifkan *USB debugging*) atau buka emulator dari Android Studio, lalu:

```
cd native_app        (atau: cd webview_app)
flutter run
```

## 6. Buat file APK

```
flutter build apk --release
```

Hasilnya: `build/app/outputs/flutter-apk/app-release.apk` — kirim ke HP lalu install.
Untuk Play Store pakai `flutter build appbundle` (butuh signing key, lihat
<https://docs.flutter.dev/deployment/android>).

---

## Catatan

- **AI Chat** memakai Puter.js yang hanya bisa jalan di browser, jadi di kedua aplikasi tab ini
  memuat halaman `ai-chat/dist/` lewat WebView. Kalau Puter meminta login lewat *popup*,
  popup itu mungkin tidak bisa terbuka di WebView.
- **Timer** dihitung di server, jadi tetap berjalan walaupun aplikasi ditutup. Belum ada notifikasi
  saat waktu habis — bisa ditambahkan dengan package `flutter_local_notifications`.
- **Login di aplikasi native** memakai token (tabel `api_tokens`), bukan session/cookie seperti website.

### API untuk aplikasi native (`api/mobile/`)

Semua request (kecuali login/daftar) wajib header `Authorization: Bearer <token>`.

| File | Method | Fungsi |
|---|---|---|
| `auth.php` | POST | `login`, `register`, `logout` → mengembalikan `token` |
| `dashboard.php` | GET | Semua data Home/Timer/Progress |
| `timer.php` | POST | `start`, `pause`, `resume`, `finish`, `cancel` (logika sama dengan website) |
| `planner.php` | GET / POST | GET `?from=&to=` → daftar rencana; POST `create` / `delete` |
