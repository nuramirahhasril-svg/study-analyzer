import 'dart:async';

import 'package:material_ui/material_ui.dart';

import '../api.dart';
import '../format.dart';
import '../models.dart';
import '../store.dart';
import '../theme.dart';
import '../widgets.dart';

/// Durasi yang dikirim dari Home ("Use suggestion").
/// Dibuat sebagai object baru setiap kali, supaya TimerScreen tahu ada permintaan baru.
class TimerPreset {
  TimerPreset(this.minutes);
  final int minutes;
}

/// Layar timer (sama dengan timer.php + assets/js/timer.js).
///
/// Data tersimpan (local_backend.dart) adalah "sumber kebenaran": layar hanya menghitung mundur
/// berdasarkan elapsed_seconds yang tersimpan + detik yang lewat sejak data diambil.
class TimerScreen extends StatefulWidget {
  const TimerScreen({super.key, required this.store, this.preset});

  final DashboardStore store;
  final TimerPreset? preset;

  @override
  State<TimerScreen> createState() => _TimerScreenState();
}

class _TimerScreenState extends State<TimerScreen> {
  static const _quickMinutes = [15, 25, 45, 60];

  final _subject = TextEditingController();
  Timer? _ticker;
  int? _chosenMinutes; // null = pakai durasi default dari pengaturan user
  bool _busy = false;
  int? _autoFinishedSessionId; // supaya "finish" otomatis hanya dikirim sekali

  @override
  void initState() {
    super.initState();
    _chosenMinutes = widget.preset?.minutes;
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
  }

  @override
  void didUpdateWidget(TimerScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    final preset = widget.preset;
    if (preset != null && !identical(preset, oldWidget.preset)) {
      setState(() => _chosenMinutes = preset.minutes.clamp(1, 120).toInt());
    }
  }

  @override
  void dispose() {
    _ticker?.cancel();
    _subject.dispose();
    super.dispose();
  }

  int _elapsedSeconds(ActiveSession session) {
    var elapsed = session.elapsedSeconds;
    if (session.isRunning) {
      elapsed += DateTime.now().difference(widget.store.fetchedAt).inSeconds;
    }
    return elapsed > session.plannedSeconds ? session.plannedSeconds : elapsed;
  }

  void _tick() {
    final session = widget.store.data?.activeSession;
    if (!mounted || session == null || !session.isRunning) return;
    setState(() {}); // gambar ulang angka countdown

    // Waktu habis → simpan sesi otomatis (sama seperti di website).
    if (_elapsedSeconds(session) >= session.plannedSeconds &&
        _autoFinishedSessionId != session.id &&
        !_busy) {
      _autoFinishedSessionId = session.id;
      _runAction('finish');
    }
  }

  Future<void> _runAction(String action, [Map<String, dynamic> extra = const {}]) async {
    setState(() => _busy = true);
    try {
      final result = await Api.instance.post('timer.php', {'action': action, ...extra});
      if (mounted) showMessage(context, result['message'] as String? ?? 'Saved.');
      if (action == 'start') _subject.clear();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    } finally {
      await widget.store.refresh();
      if (mounted) setState(() => _busy = false);
    }
  }

  void _start(int minutes) {
    final subject = _subject.text.trim();
    if (subject.isEmpty) {
      showMessage(context, 'Enter what you are studying first.');
      return;
    }
    FocusScope.of(context).unfocus();
    _runAction('start', {'subject': subject, 'duration_seconds': minutes * 60});
  }

  Future<void> _confirmCancel() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel this session?'),
        content: const Text('The time from this session will not be saved.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep going')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Cancel session')),
        ],
      ),
    );
    if (confirmed == true) _runAction('cancel');
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: widget.store,
      builder: (context, _) {
        final data = widget.store.data;
        Widget body;
        if (data == null) {
          body = widget.store.error != null
              ? ErrorView(message: widget.store.error!, onRetry: widget.store.refresh)
              : const Center(child: CircularProgressIndicator());
        } else if (data.activeSession != null) {
          body = _buildActive(data.activeSession!);
        } else {
          body = _buildStartForm(data);
        }
        return Scaffold(appBar: AppBar(title: const Text('Study timer')), body: body);
      },
    );
  }

  Widget _buildActive(ActiveSession session) {
    final elapsed = _elapsedSeconds(session);
    final remaining = session.plannedSeconds - elapsed;
    final progress = session.plannedSeconds == 0 ? 0.0 : elapsed / session.plannedSeconds;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              children: [
                Text(session.subject,
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                Chip(
                  label: Text(session.isRunning ? 'Focusing' : 'Paused'),
                  backgroundColor: session.isRunning ? AppColors.lime : AppColors.peach,
                  side: BorderSide.none,
                ),
                const SizedBox(height: 20),
                SizedBox(
                  width: 230,
                  height: 230,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      CircularProgressIndicator(
                        value: progress,
                        strokeWidth: 14,
                        color: AppColors.purple,
                        backgroundColor: const Color(0xFFEEF0EC),
                        strokeCap: StrokeCap.round,
                      ),
                      Center(
                        child: Text(
                          countdown(remaining),
                          style: const TextStyle(
                            fontSize: 52,
                            fontWeight: FontWeight.w800,
                            fontFeatures: [FontFeature.tabularFigures()],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 8),
                Text('of ${session.plannedSeconds ~/ 60} min', style: const TextStyle(color: AppColors.muted)),
                const SizedBox(height: 24),
                Row(
                  children: [
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: _busy ? null : () => _runAction(session.isRunning ? 'pause' : 'resume'),
                        icon: Icon(session.isRunning ? Icons.pause : Icons.play_arrow),
                        label: Text(session.isRunning ? 'Pause' : 'Resume'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(minimumSize: const Size(0, 48)),
                        onPressed: _busy ? null : () => _runAction('finish'),
                        icon: const Icon(Icons.check),
                        label: const Text('Finish'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                TextButton(onPressed: _busy ? null : _confirmCancel, child: const Text('Cancel session')),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        const Text(
          'Tip: the timer keeps counting even if you close the app.',
          textAlign: TextAlign.center,
          style: TextStyle(color: AppColors.muted),
        ),
      ],
    );
  }

  Widget _buildStartForm(Dashboard data) {
    final minutes = (_chosenMinutes ?? data.defaultSession).clamp(1, 120).toInt();
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        SectionCard(
          eyebrow: 'NEW SESSION',
          title: 'What are you studying?',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              TextField(
                controller: _subject,
                maxLength: 100,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(labelText: 'Subject', hintText: 'e.g. Biology'),
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Duration', style: TextStyle(fontWeight: FontWeight.w700)),
                  Text('$minutes min', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                ],
              ),
              Slider(
                value: minutes.toDouble(),
                min: 1,
                max: 120,
                divisions: 119,
                label: '$minutes min',
                onChanged: (value) => setState(() => _chosenMinutes = value.round()),
              ),
              Wrap(
                spacing: 8,
                children: [
                  for (final quick in _quickMinutes)
                    ChoiceChip(
                      label: Text('$quick min'),
                      selected: minutes == quick,
                      onSelected: (_) => setState(() => _chosenMinutes = quick),
                    ),
                ],
              ),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: _busy ? null : () => _start(minutes),
                icon: const Icon(Icons.play_arrow),
                label: const Text('Start studying'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Card(
          child: ListTile(
            leading: const Icon(Icons.lightbulb_outline, color: AppColors.purple),
            title: Text("Today's suggestion: ${data.suggestionMinutes} min"),
            subtitle: Text(data.suggestionReason),
          ),
        ),
      ],
    );
  }
}
