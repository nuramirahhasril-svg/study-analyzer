import 'package:material_ui/material_ui.dart';

import 'format.dart';
import 'models.dart';
import 'theme.dart';

/// Kartu statistik, mirip .stat-card di website.
class StatCard extends StatelessWidget {
  const StatCard({
    super.key,
    required this.icon,
    required this.iconColor,
    required this.label,
    required this.value,
    this.unit = '',
    this.note,
    this.progress,
    this.action,
  });

  final IconData icon;
  final Color iconColor;
  final String label;
  final String value;
  final String unit;
  final String? note;
  final double? progress; // 0.0 - 1.0, null = tanpa progress bar
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(color: iconColor, borderRadius: BorderRadius.circular(12)),
                  child: Icon(icon, size: 20, color: AppColors.ink),
                ),
                const SizedBox(width: 12),
                Text(label, style: const TextStyle(color: AppColors.muted, fontWeight: FontWeight.w600)),
              ],
            ),
            const SizedBox(height: 14),
            Text.rich(TextSpan(children: [
              TextSpan(
                text: value,
                style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w800, color: AppColors.ink),
              ),
              TextSpan(text: unit, style: const TextStyle(fontSize: 15, color: AppColors.muted)),
            ])),
            if (note != null) ...[
              const SizedBox(height: 6),
              Text(note!, style: const TextStyle(color: AppColors.muted)),
            ],
            if (progress != null) ...[
              const SizedBox(height: 12),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: LinearProgressIndicator(
                  value: progress,
                  minHeight: 8,
                  color: AppColors.purple,
                  backgroundColor: const Color(0xFFEEF0EC),
                ),
              ),
            ],
            if (action != null) ...[const SizedBox(height: 8), action!],
          ],
        ),
      ),
    );
  }
}

/// Panel dengan judul kecil (eyebrow) + judul besar.
class SectionCard extends StatelessWidget {
  const SectionCard({super.key, required this.eyebrow, required this.title, required this.child});

  final String eyebrow;
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(eyebrow,
                style: const TextStyle(
                    fontSize: 11, letterSpacing: 1.2, fontWeight: FontWeight.w700, color: AppColors.muted)),
            const SizedBox(height: 4),
            Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 14),
            child,
          ],
        ),
      ),
    );
  }
}

/// Daftar "Recent sessions" (sama dengan includes/session_list.php).
class SessionList extends StatelessWidget {
  const SessionList({super.key, required this.sessions});

  final List<RecentSession> sessions;

  @override
  Widget build(BuildContext context) {
    if (sessions.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 16),
        child: Center(
          child: Column(
            children: [
              Text('✧', style: TextStyle(fontSize: 24, color: AppColors.pink)),
              SizedBox(height: 6),
              Text('Your progress starts here', style: TextStyle(fontWeight: FontWeight.w700)),
              SizedBox(height: 4),
              Text('Complete a study session and it will show up here.',
                  textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted)),
            ],
          ),
        ),
      );
    }

    return Column(
      children: [
        for (final session in sessions)
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const CircleAvatar(
              backgroundColor: AppColors.lilac,
              child: Text('✦', style: TextStyle(color: AppColors.purple)),
            ),
            title: Text(session.subject, style: const TextStyle(fontWeight: FontWeight.w700)),
            subtitle: Text(session.endedAt == null ? 'No end time' : sessionTime(session.endedAt!)),
            trailing: Text('${session.minutes}m',
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          ),
      ],
    );
  }
}

/// Grafik batang sederhana untuk menit belajar per hari.
class MinutesBarChart extends StatelessWidget {
  const MinutesBarChart({super.key, required this.days, this.height = 150});

  final List<DayMinutes> days;
  final double height;

  @override
  Widget build(BuildContext context) {
    final highest = days.fold<int>(0, (max, day) => day.minutes > max ? day.minutes : max);
    return SizedBox(
      height: height + 44,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          for (var i = 0; i < days.length; i++)
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    Text('${days[i].minutes}', style: const TextStyle(fontSize: 11, color: AppColors.muted)),
                    const SizedBox(height: 4),
                    Container(
                      height: highest == 0 ? 4 : 4 + (height - 4) * days[i].minutes / highest,
                      decoration: BoxDecoration(
                        // Hari terakhir (hari ini) diberi warna lebih gelap.
                        color: i == days.length - 1 ? AppColors.purple : AppColors.pink,
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(days[i].label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Tampilan saat data gagal dimuat.
class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off, size: 48, color: AppColors.muted),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: onRetry, child: const Text('Try again')),
          ],
        ),
      ),
    );
  }
}

void showMessage(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}
