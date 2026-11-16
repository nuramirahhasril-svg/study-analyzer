import 'package:flutter/foundation.dart';

import 'api.dart';
import 'models.dart';

/// Menyimpan data dashboard (Home, Timer, Progress memakai data yang sama).
/// Layar-layar mendengarkan perubahan lewat ListenableBuilder.
class DashboardStore extends ChangeNotifier {
  Dashboard? data;
  String? error;
  bool loading = false;

  /// Kapan data terakhir diambil. Dipakai timer untuk menghitung detik
  /// yang sudah lewat sejak itu, tanpa harus bertanya ke server tiap detik.
  DateTime fetchedAt = DateTime.now();

  bool _disposed = false;

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }

  @override
  void notifyListeners() {
    // Bisa terjadi: user logout saat data masih diambil → store sudah dibuang.
    if (!_disposed) super.notifyListeners();
  }

  Future<void> refresh() async {
    loading = true;
    notifyListeners();
    try {
      final json = await Api.instance.get('dashboard.php');
      data = Dashboard.fromJson(json);
      fetchedAt = DateTime.now();
      error = null;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}
