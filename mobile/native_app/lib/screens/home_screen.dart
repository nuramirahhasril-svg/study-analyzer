import 'package:material_ui/material_ui.dart';

import '../api.dart';
import '../models.dart';
import '../store.dart';
import '../theme.dart';
import '../widgets.dart';

/// Layar Home (sama dengan home.php): target harian, streak, saran, sesi terakhir.
class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key, required this.store, required this.onOpenTimer, required this.onOpenPlanner});

  final DashboardStore store;
  final void Function([int? minutes]) onOpenTimer;
  final VoidCallback onOpenPlanner;

  Future<void> _confirmLogout(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Change name?'),
        content: const Text('Your study data stays saved on this phone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Change name')),
        ],
      ),
    );
    if (confirmed == true) await Api.instance.logout();
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: store,
      builder: (context, _) {
        final data = store.data;
        return Scaffold(
          appBar: AppBar(
            title: Text(data == null ? 'Home' : 'Welcome back, ${data.firstName}'),
            actions: [
              IconButton(
                tooltip: 'Change name',
                icon: const Icon(Icons.edit_outlined),
                onPressed: () => _confirmLogout(context),
              ),
            ],
          ),
          body: data == null
              ? (store.error != null
                  ? ErrorView(message: store.error!, onRetry: store.refresh)
                  : const Center(child: CircularProgressIndicator()))
              : RefreshIndicator(onRefresh: store.refresh, child: _buildContent(context, data)),
        );
      },
    );
  }

  Widget _buildContent(BuildContext context, Dashboard data) {
    final active = data.activeSession;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
      children: [
        const Text('Make today count.', style: TextStyle(color: AppColors.muted)),
        const SizedBox(height: 16),
        StatCard(
          icon: Icons.schedule,
          iconColor: AppColors.mint,
          label: 'Daily goal',
          value: '${data.todayMinutes}',
          unit: ' / ${data.dailyGoal} min',
          note: data.remainingMinutes > 0
              ? '${data.remainingMinutes} minutes left to reach your goal'
              : 'Daily goal reached. Great work!',
          progress: data.goalProgress,
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: StatCard(
                icon: Icons.local_fire_department_outlined,
                iconColor: AppColors.peach,
                label: 'Streak',
                value: '${data.streakDays}',
                unit: data.streakDays == 1 ? ' day' : ' days',
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: StatCard(
                icon: Icons.insights,
                iconColor: AppColors.lilac,
                label: '7-day avg',
                value: '${data.weeklyAverage}',
                unit: ' min',
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        StatCard(
          icon: Icons.edit_note,
          iconColor: AppColors.peach,
          label: "Today's suggestion",
          value: '${data.suggestionMinutes}',
          unit: ' min',
          note: data.suggestionReason,
          action: TextButton.icon(
            onPressed: active != null
                ? () => onOpenTimer()
                : data.suggestionMinutes > 0
                    ? () => onOpenTimer(data.suggestionMinutes)
                    : null,
            icon: const Icon(Icons.arrow_forward),
            label: Text(active != null ? 'Return to timer' : 'Use suggestion'),
          ),
        ),
        const SizedBox(height: 12),
        SectionCard(
          eyebrow: 'READY WHEN YOU ARE',
          title: active == null
              ? 'Make room for focus'
              : active.isRunning
                  ? 'Your session is in progress'
                  : 'Your session is paused',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(active == null
                  ? 'Your next study session is only a tap away. Pick a subject and start with a fresh, focused session.'
                  : 'You have a ${active.status} session for ${active.subject}.'),
              const SizedBox(height: 14),
              FilledButton(
                onPressed: () => onOpenTimer(),
                child: Text(active == null ? 'Open study timer' : 'Return to timer'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        SectionCard(
          eyebrow: 'KEEP IT GOING',
          title: 'Recent sessions',
          child: SessionList(sessions: data.recentSessions.take(5).toList()),
        ),
        const SizedBox(height: 12),
        Card(
          child: ListTile(
            contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
            leading: const CircleAvatar(
              backgroundColor: AppColors.lime,
              child: Icon(Icons.calendar_month, color: AppColors.ink),
            ),
            title: const Text('Plan your next study session', style: TextStyle(fontWeight: FontWeight.w700)),
            subtitle: const Text('Open your calendar and see your study habits.'),
            trailing: const Icon(Icons.arrow_forward),
            onTap: onOpenPlanner,
          ),
        ),
      ],
    );
  }
}
