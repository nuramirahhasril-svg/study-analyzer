import 'package:material_ui/material_ui.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';

import '../config.dart';

/// Layar AI chat.
///
/// AI chat di website memakai Puter.js, yang hanya bisa berjalan di browser.
/// Jadi layar ini menampilkan halaman ai-chat (dibawa di dalam aplikasi, folder
/// assets/ai-chat) lewat WebView dari http://localhost. Butuh internet untuk Puter.
///
/// Login Puter dibuka lewat *popup* (window.open). WebView biasa memblokir
/// popup, sehingga login dianggap "canceled". Karena itu dipakai
/// flutter_inappwebview yang mendukung popup: popup ditampilkan di layar
/// penuh, dan setelah login selesai hasilnya dikirim kembali ke halaman chat.
class ChatScreen extends StatefulWidget {
  const ChatScreen({super.key});

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

/// Pengaturan WebView yang dipakai halaman chat DAN popup login Puter.
InAppWebViewSettings chatWebViewSettings() => InAppWebViewSettings(
      javaScriptEnabled: true,
      javaScriptCanOpenWindowsAutomatically: true,
      supportMultipleWindows: true,
      domStorageEnabled: true,
      databaseEnabled: true,
      thirdPartyCookiesEnabled: true,
      mixedContentMode: MixedContentMode.MIXED_CONTENT_ALWAYS_ALLOW,
      transparentBackground: false,
    );

class _ChatScreenState extends State<ChatScreen> {
  InAppWebViewController? _controller;
  int _progress = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('AI study chat'),
        actions: [
          IconButton(
            tooltip: 'Reload',
            icon: const Icon(Icons.refresh),
            onPressed: () => _controller?.reload(),
          ),
        ],
      ),
      body: Stack(
        children: [
          InAppWebView(
            initialUrlRequest: URLRequest(url: WebUri(chatUrl)),
            initialSettings: chatWebViewSettings(),
            onWebViewCreated: (controller) => _controller = controller,
            onProgressChanged: (_, progress) {
              if (mounted) setState(() => _progress = progress);
            },
            onCreateWindow: (_, action) => openWebPopup(context, action.windowId),
          ),
          if (_progress < 100) LinearProgressIndicator(value: _progress / 100, minHeight: 2),
        ],
      ),
    );
  }
}

/// Menampilkan popup (misalnya login Puter) di layar penuh.
/// Popup tertutup sendiri saat halaman memanggil window.close().
Future<bool> openWebPopup(BuildContext context, int windowId) async {
  Navigator.of(context).push(MaterialPageRoute(
    fullscreenDialog: true,
    builder: (_) => _PopupPage(windowId: windowId),
  ));
  return true;
}

class _PopupPage extends StatefulWidget {
  const _PopupPage({required this.windowId});

  final int windowId;

  @override
  State<_PopupPage> createState() => _PopupPageState();
}

class _PopupPageState extends State<_PopupPage> {
  int _progress = 0;
  bool _closed = false;

  void _close() {
    if (_closed || !mounted) return;
    _closed = true;
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Sign in'),
        leading: IconButton(icon: const Icon(Icons.close), onPressed: _close),
      ),
      body: Stack(
        children: [
          InAppWebView(
            windowId: widget.windowId,
            initialSettings: chatWebViewSettings(),
            onProgressChanged: (_, progress) {
              if (mounted) setState(() => _progress = progress);
            },
            onCloseWindow: (_) => _close(),
            // Popup di dalam popup (jarang) - buka juga di layar penuh.
            onCreateWindow: (_, action) => openWebPopup(context, action.windowId),
          ),
          if (_progress < 100) LinearProgressIndicator(value: _progress / 100, minHeight: 2),
        ],
      ),
    );
  }
}
