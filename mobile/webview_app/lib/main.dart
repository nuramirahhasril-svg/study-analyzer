import 'package:material_ui/material_ui.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';

/// Alamat website Study Analyzer.
/// - Sudah di-hosting:      'https://namasitusmu.com'
/// - Tes di XAMPP (HP & PC di Wi-Fi yang sama): 'http://192.168.1.10/study_analyzer'
///   (ganti 192.168.1.10 dengan IP PC kamu, cek pakai `ipconfig`)
/// - Emulator Android:      'http://10.0.2.2/study_analyzer'
const String siteUrl = 'https://study-analyzer-production-d653db22.up.railway.app';

const Color brandColor = Color(0xFF3E2F59);
const Color canvasColor = Color(0xFFF7F8F5);

void main() {
  runApp(const MaterialApp(
    title: 'Study Analyzer',
    debugShowCheckedModeBanner: false,
    home: WebApp(),
  ));
}

/// Pengaturan WebView. supportMultipleWindows dibutuhkan supaya popup
/// (misalnya login Puter di halaman AI chat) bisa terbuka dan mengirim hasil
/// login kembali ke halaman. Tanpa ini login Puter selalu "canceled".
InAppWebViewSettings webSettings() => InAppWebViewSettings(
      javaScriptEnabled: true,
      javaScriptCanOpenWindowsAutomatically: true,
      supportMultipleWindows: true,
      domStorageEnabled: true,
      databaseEnabled: true,
      thirdPartyCookiesEnabled: true,
      mixedContentMode: MixedContentMode.MIXED_CONTENT_ALWAYS_ALLOW,
    );

class WebApp extends StatefulWidget {
  const WebApp({super.key});

  @override
  State<WebApp> createState() => _WebAppState();
}

class _WebAppState extends State<WebApp> {
  InAppWebViewController? _controller;
  int _progress = 0;
  bool _failed = false;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      // Tombol "back" Android → kembali ke halaman sebelumnya di website dulu.
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        final controller = _controller;
        if (controller != null && await controller.canGoBack()) {
          await controller.goBack();
        } else if (context.mounted) {
          Navigator.of(context).maybePop();
        }
      },
      child: Scaffold(
        backgroundColor: canvasColor,
        body: SafeArea(
          child: Stack(
            children: [
              // WebView tetap ada (tidak dibuang) supaya "Try again" bisa reload.
              Offstage(offstage: _failed, child: _buildWebView()),
              if (_failed) _buildError(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildWebView() {
    return Stack(
      children: [
        InAppWebView(
          initialUrlRequest: URLRequest(url: WebUri(siteUrl)),
          initialSettings: webSettings(),
          onWebViewCreated: (controller) => _controller = controller,
          onProgressChanged: (_, progress) => setState(() => _progress = progress),
          onLoadStart: (_, __) => setState(() => _failed = false),
          onReceivedError: (_, request, __) {
            // Hanya tampilkan layar error kalau HALAMAN UTAMA gagal dimuat
            // (bukan kalau satu gambar/font saja yang gagal).
            if (request.isForMainFrame ?? false) {
              setState(() => _failed = true);
            }
          },
          onCreateWindow: (_, action) => openWebPopup(context, action.windowId),
        ),
        if (_progress < 100)
          LinearProgressIndicator(
            value: _progress / 100,
            color: brandColor,
            backgroundColor: Colors.transparent,
          ),
      ],
    );
  }

  Widget _buildError() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.wifi_off, size: 48, color: Color(0xFF87918E)),
            const SizedBox(height: 16),
            const Text(
              'Could not reach Study Analyzer.\nCheck your internet connection.',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 16),
            FilledButton(
              style: FilledButton.styleFrom(backgroundColor: brandColor),
              onPressed: () {
                setState(() => _failed = false);
                _controller?.reload();
              },
              child: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }
}

/// Menampilkan popup (misalnya login Puter) di layar penuh.
/// Popup tertutup sendiri saat halaman memanggil window.close().
Future<bool> openWebPopup(BuildContext context, int windowId) async {
  Navigator.of(context).push(MaterialPageRoute(
    fullscreenDialog: true,
    builder: (_) => PopupPage(windowId: windowId),
  ));
  return true;
}

class PopupPage extends StatefulWidget {
  const PopupPage({super.key, required this.windowId});

  final int windowId;

  @override
  State<PopupPage> createState() => _PopupPageState();
}

class _PopupPageState extends State<PopupPage> {
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
        backgroundColor: canvasColor,
        leading: IconButton(icon: const Icon(Icons.close), onPressed: _close),
      ),
      body: Stack(
        children: [
          InAppWebView(
            windowId: widget.windowId,
            initialSettings: webSettings(),
            onProgressChanged: (_, progress) {
              if (mounted) setState(() => _progress = progress);
            },
            onCloseWindow: (_) => _close(),
            onCreateWindow: (_, action) => openWebPopup(context, action.windowId),
          ),
          if (_progress < 100)
            LinearProgressIndicator(
              value: _progress / 100,
              color: brandColor,
              backgroundColor: Colors.transparent,
            ),
        ],
      ),
    );
  }
}
