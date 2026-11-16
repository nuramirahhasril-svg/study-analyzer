import 'package:material_ui/material_ui.dart';

import '../store.dart';
import '../theme.dart';
import '../widgets.dart';

/// Layar Progress (sama dengan progress.php) + grafik 7 hari terakhir.
class ProgressScreen extends StatelessWidget {
  const ProgressScreen({super.key, required this.store});

  final DashboardStore store;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: store,
      builder: (context, _) {
        final data = store.data;
        Widget body;
        if (data == null) {
          body = store.error != null
              ? ErrorView(message: store.error!, onRetry: store.refresh)
              : const Center(child: CircularProgressIndicator());
        } else {
          body = RefreshIndicator(
            onRefresh: store.refresh,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
              children: [
                const Text('Keep going', style: TextStyle(color: AppColors.muted)),
                const SizedBox(height: 16),
                StatCard(
                  icon: Icons.schedule,
                  iconColor: AppColors.mint,
                  label: 'Studied today',
                  value: '${data.todayMinutes}',
                  unit: ' min',
                  note: 'of your ${data.dailyGoal} min daily goal',
                  progress: data.goalProgress,
                ),
                const SizedBox(height: 12),
                StatCard(
                  icon: Icons.insights,
                  iconColor: AppColors.lilac,
                  label: '7-day daily average',
                  value: '${data.weeklyAverage}',
                  unit: ' min',
                  note: 'Your last seven days of study',
                ),
                const SizedBox(height: 12),
                SectionCard(
                  eyebrow: 'LAST 7 DAYS',
                  title: 'Minutes studied',
                  child: MinutesBarChart(days: data.lastSevenDays),
                ),
                const SizedBox(height: 12),
                SectionCard(
                  eyebrow: 'KEEP IT GOING',
                  title: 'Recent sessions',
                  child: SessionList(sessions: data.recentSessions),
                ),
              ],
            ),
          );
        }
        return Scaffold(appBar: AppBar(title: const Text('Your progress')), body: body);
      },
    );
  }
}
