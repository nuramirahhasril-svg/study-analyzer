import 'package:flutter/foundation.dart';

import 'local_backend.dart';

export 'local_backend.dart' show ApiException;

/// Penghubung antara layar-layar aplikasi dan data.
///
/// Aplikasi ini sekarang berdiri sendiri (tidak butuh komputer/XAMPP/internet):
/// semua data disimpan di HP oleh [LocalBackend]. Class ini tetap ada supaya
/// layar-layar bisa memakai cara yang sama seperti dulu, yaitu
/// Api.instance.get('dashboard.php') dan Api.instance.post('timer.php', {...}).
class Api {
  Api._();

  static final Api instance = Api._();

  /// true = sudah mengisi nama (pengganti "sudah login").
  final ValueNotifier<bool> loggedIn = ValueNotifier(false);

  /// Baca data yang tersimpan di HP (dipanggil sekali saat aplikasi dibuka).
  Future<void> loadSavedToken() async {
    await LocalBackend.instance.load();
    loggedIn.value = LocalBackend.instance.profileName != null;
  }

  /// Simpan nama user (layar pertama). Tidak ada password: data hanya ada di HP ini.
  Future<void> setName(String name) async {
    final trimmed = name.trim();
    if (trimmed.isEmpty || trimmed.runes.length > 100) {
      throw ApiException('Please enter your name (up to 100 characters).');
    }
    await LocalBackend.instance.setProfileName(trimmed);
    loggedIn.value = true;
  }

  /// "Keluar": kembali ke layar nama. Data belajar TETAP tersimpan di HP.
  Future<void> logout() async {
    await LocalBackend.instance.setProfileName(null);
    loggedIn.value = false;
  }

  Future<Map<String, dynamic>> get(String path, [Map<String, String>? query]) =>
      LocalBackend.instance.get(path, query);

  Future<Map<String, dynamic>> post(String path, Map<String, dynamic> body) =>
      LocalBackend.instance.post(path, body);
}
