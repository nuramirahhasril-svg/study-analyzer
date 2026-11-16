import 'package:material_ui/material_ui.dart';

import '../store.dart';
import 'chat_screen.dart';
import 'home_screen.dart';
import 'planner_screen.dart';
import 'progress_screen.dart';
import 'timer_screen.dart';

/// Kerangka aplikasi setelah login: 5 tab di bawah (sama dengan menu website).
class AppShell extends StatefulWidget {
  const AppShell({super.key});

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> with WidgetsBindingObserver {
  final _store = DashboardStore();
  int _index = 0;
  final Set<int> _openedTabs = {0}; // tab dibuat saat pertama kali dibuka
  TimerPreset? _timerPreset;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _store.refresh();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _store.dispose();
    super.dispose();
  }

  /// Saat aplikasi dibuka lagi dari background → ambil data terbaru.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _store.refresh();
  }

  void _selectTab(int index) {
    setState(() {
      _index = index;
      _openedTabs.add(index);
    });
    // Data Home/Timer/Progress diperbarui setiap kali tab-nya dibuka.
    if (index <= 2) _store.refresh();
  }

  /// Dipanggil dari Home: buka tab Timer (opsional dengan durasi saran).
  void _openTimer([int? minutes]) {
    if (minutes != null && minutes > 0) _timerPreset = TimerPreset(minutes);
    _selectTab(1);
  }

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      HomeScreen(store: _store, onOpenTimer: _openTimer, onOpenPlanner: () => _selectTab(3)),
      TimerScreen(store: _store, preset: _timerPreset),
      ProgressScreen(store: _store),
      const PlannerScreen(),
      const ChatScreen(),
    ];

    return Scaffold(
      body: IndexedStack(
        index: _index,
        children: [
          for (var i = 0; i < pages.length; i++)
            _openedTabs.contains(i) ? pages[i] : const SizedBox.shrink(),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: _selectTab,
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.timer_outlined), selectedIcon: Icon(Icons.timer), label: 'Timer'),
          NavigationDestination(
              icon: Icon(Icons.bar_chart_outlined), selectedIcon: Icon(Icons.bar_chart), label: 'Progress'),
          NavigationDestination(
              icon: Icon(Icons.calendar_month_outlined),
              selectedIcon: Icon(Icons.calendar_month),
              label: 'Planner'),
          NavigationDestination(
              icon: Icon(Icons.auto_awesome_outlined), selectedIcon: Icon(Icons.auto_awesome), label: 'AI Chat'),
        ],
      ),
    );
  }
}
