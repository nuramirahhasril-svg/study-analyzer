import 'package:material_ui/material_ui.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';

import 'api.dart';
import 'config.dart';
import 'screens/login_screen.dart';
import 'screens/shell.dart';
import 'theme.dart';

/// Server kecil di dalam aplikasi untuk halaman AI chat (lihat config.dart).
final chatServer = InAppLocalhostServer(documentRoot: 'assets/ai-chat', port: chatServerPort);

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Api.instance.loadSavedToken(); // sudah pernah isi nama? langsung masuk.
  try {
    await chatServer.start();
  } catch (e) {
    // Gagal (misalnya port sedang dipakai): aplikasi tetap jalan, hanya AI chat yang tidak bisa dibuka.
    debugPrint('AI chat server could not start: $e');
  }
  runApp(const StudyAnalyzerApp());
}

class StudyAnalyzerApp extends StatelessWidget {
  const StudyAnalyzerApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Study Analyzer',
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
      // Pindah otomatis antara layar nama dan aplikasi.
      home: ValueListenableBuilder<bool>(
        valueListenable: Api.instance.loggedIn,
        builder: (context, loggedIn, _) => loggedIn ? const AppShell() : const LoginScreen(),
      ),
    );
  }
}
