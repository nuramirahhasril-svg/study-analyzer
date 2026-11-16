import 'package:material_ui/material_ui.dart';
import 'package:table_calendar/table_calendar.dart';

import '../api.dart';
import '../format.dart';
import '../models.dart';
import '../theme.dart';
import '../widgets.dart';

/// Layar Planner (sama dengan planner.php): kalender, rencana belajar, tambah & hapus.
class PlannerScreen extends StatefulWidget {
  const PlannerScreen({super.key});

  @override
  State<PlannerScreen> createState() => _PlannerScreenState();
}

class _PlannerScreenState extends State<PlannerScreen> {
  final DateTime _today = DateUtils.dateOnly(DateTime.now());
  late DateTime _focusedDay = _today;
  late DateTime _selectedDay = _today;
  CalendarFormat _format = CalendarFormat.month;

  Map<String, List<StudyPlan>> _plansByDate = {};
  Map<String, int> _minutesByDate = {};
  bool _loading = false;
  String? _error;
  int _requestId = 0; // abaikan balasan lama kalau user cepat ganti bulan

  @override
  void initState() {
    super.initState();
    _load();
  }

  /// Ambil rencana untuk bulan yang terlihat (+ sedikit sebelum & sesudahnya).
  Future<void> _load() async {
    final requestId = ++_requestId;
    final from = DateTime(_focusedDay.year, _focusedDay.month, 1).subtract(const Duration(days: 7));
    final to = DateTime(_focusedDay.year, _focusedDay.month + 1, 1).add(const Duration(days: 14));

    setState(() => _loading = true);
    try {
      final json = await Api.instance.get('planner.php', {'from': ymd(from), 'to': ymd(to)});
      if (requestId != _requestId || !mounted) return;

      final plans = <String, List<StudyPlan>>{};
      for (final item in json['plans'] as List) {
        final plan = StudyPlan.fromJson(item as Map<String, dynamic>);
        plans.putIfAbsent(plan.date, () => []).add(plan);
      }
      final minutes = <String, int>{};
      (json['minutes_by_date'] as Map<String, dynamic>).forEach((date, value) {
        minutes[date] = (value as num).toInt();
      });

      setState(() {
        _plansByDate = plans;
        _minutesByDate = minutes;
        _error = null;
      });
    } on ApiException catch (e) {
      if (requestId == _requestId && mounted) setState(() => _error = e.message);
    } finally {
      if (requestId == _requestId && mounted) setState(() => _loading = false);
    }
  }

  List<StudyPlan> _plansOn(DateTime day) => _plansByDate[ymd(day)] ?? const [];

  Future<void> _addPlan() async {
    final initialDate = _selectedDay.isBefore(_today) ? _today : DateUtils.dateOnly(_selectedDay);
    final plan = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => _AddPlanSheet(initialDate: initialDate, today: _today),
    );
    if (plan == null) return;

    try {
      final result = await Api.instance.post('planner.php', {'action': 'create', ...plan});
      if (mounted) showMessage(context, result['message'] as String? ?? 'Saved.');
      final date = DateTime.parse(plan['date'] as String);
      setState(() {
        _selectedDay = date;
        _focusedDay = date;
      });
      await _load();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    }
  }

  Future<void> _deletePlan(StudyPlan plan) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Remove this plan?'),
        content: Text('"${plan.title}" will be removed from your planner.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Remove')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      final result = await Api.instance.post('planner.php', {'action': 'delete', 'id': plan.id});
      if (mounted) showMessage(context, result['message'] as String? ?? 'Removed.');
      await _load();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final selectedPlans = _plansOn(_selectedDay);
    final studied = _minutesByDate[ymd(_selectedDay)] ?? 0;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Planner'),
        actions: [
          IconButton(
            tooltip: 'Today',
            icon: const Icon(Icons.today),
            onPressed: () {
              setState(() {
                _focusedDay = _today;
                _selectedDay = _today;
              });
              _load();
            },
          ),
        ],
        bottom: _loading
            ? const PreferredSize(preferredSize: Size.fromHeight(2), child: LinearProgressIndicator(minHeight: 2))
            : null,
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _addPlan,
        backgroundColor: AppColors.lime,
        foregroundColor: AppColors.ink,
        icon: const Icon(Icons.add),
        label: const Text('Add plan'),
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 96),
          children: [
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(8),
                child: TableCalendar<StudyPlan>(
                  firstDay: DateTime(_today.year - 2, 1, 1),
                  lastDay: DateTime(_today.year + 3, 12, 31),
                  focusedDay: _focusedDay,
                  calendarFormat: _format,
                  startingDayOfWeek: StartingDayOfWeek.monday,
                  availableCalendarFormats: const {
                    CalendarFormat.month: 'Month',
                    CalendarFormat.twoWeeks: '2 weeks',
                    CalendarFormat.week: 'Week',
                  },
                  selectedDayPredicate: (day) => isSameDay(day, _selectedDay),
                  eventLoader: _plansOn,
                  onDaySelected: (selected, focused) => setState(() {
                    _selectedDay = DateUtils.dateOnly(selected);
                    _focusedDay = focused;
                  }),
                  onFormatChanged: (format) => setState(() => _format = format),
                  onPageChanged: (focused) {
                    _focusedDay = focused;
                    _load();
                  },
                  headerStyle: const HeaderStyle(
                    titleTextStyle: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
                    formatButtonShowsNext: false,
                  ),
                  calendarStyle: const CalendarStyle(
                    todayDecoration: BoxDecoration(color: AppColors.pink, shape: BoxShape.circle),
                    selectedDecoration: BoxDecoration(color: AppColors.purple, shape: BoxShape.circle),
                    markerDecoration: BoxDecoration(color: AppColors.purple, shape: BoxShape.circle),
                    markersMaxCount: 3,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            SectionCard(
              eyebrow: studied > 0 ? 'STUDIED $studied MIN THIS DAY' : 'YOUR PLANS',
              title: longDate(_selectedDay),
              child: selectedPlans.isEmpty
                  ? const Padding(
                      padding: EdgeInsets.symmetric(vertical: 8),
                      child: Text('No plans for this day yet. Tap "Add plan" to schedule one.',
                          style: TextStyle(color: AppColors.muted)),
                    )
                  : Column(
                      children: [
                        for (final plan in selectedPlans)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: CircleAvatar(
                              backgroundColor: AppColors.lilac,
                              child: Icon(plan.time == null ? Icons.wb_sunny_outlined : Icons.schedule,
                                  color: AppColors.purple, size: 20),
                            ),
                            title: Text(plan.title, style: const TextStyle(fontWeight: FontWeight.w700)),
                            subtitle: Text([
                              plan.time == null ? 'All day' : planTime(plan.time!),
                              if (plan.notes.isNotEmpty) plan.notes,
                            ].join(' · ')),
                            trailing: IconButton(
                              tooltip: 'Remove',
                              icon: const Icon(Icons.delete_outline),
                              onPressed: () => _deletePlan(plan),
                            ),
                          ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Form "Add plan" di bagian bawah layar. Mengembalikan data rencana, atau null kalau dibatalkan.
class _AddPlanSheet extends StatefulWidget {
  const _AddPlanSheet({required this.initialDate, required this.today});

  final DateTime initialDate;
  final DateTime today;

  @override
  State<_AddPlanSheet> createState() => _AddPlanSheetState();
}

class _AddPlanSheetState extends State<_AddPlanSheet> {
  final _title = TextEditingController();
  final _notes = TextEditingController();
  late DateTime _date = widget.initialDate;
  TimeOfDay? _time; // null = sepanjang hari
  String? _error;

  @override
  void dispose() {
    _title.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: widget.today,
      lastDate: widget.today.add(const Duration(days: 730)),
    );
    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(context: context, initialTime: _time ?? TimeOfDay.now());
    if (picked != null) setState(() => _time = picked);
  }

  void _save() {
    final title = _title.text.trim();
    if (title.isEmpty) {
      setState(() => _error = 'Add a title for your plan.');
      return;
    }
    final time = _time;
    Navigator.pop(context, {
      'title': title,
      'notes': _notes.text.trim(),
      'date': ymd(_date),
      'time': time == null
          ? ''
          : '${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}',
    });
  }

  @override
  Widget build(BuildContext context) {
    final time = _time;
    return Padding(
      // Geser ke atas saat keyboard muncul.
      padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.of(context).viewInsets.bottom + 20),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('New study plan', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
            const SizedBox(height: 16),
            TextField(
              controller: _title,
              autofocus: true,
              maxLength: 120,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(labelText: 'Title', hintText: 'e.g. Review chapter 4'),
            ),
            TextField(
              controller: _notes,
              maxLength: 500,
              maxLines: 3,
              minLines: 1,
              decoration: const InputDecoration(labelText: 'Notes (optional)'),
            ),
            const SizedBox(height: 4),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _pickDate,
                    icon: const Icon(Icons.event),
                    label: Text(shortDate(_date)),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _pickTime,
                    icon: const Icon(Icons.schedule),
                    label: Text(time == null ? 'All day' : clockTime(time.hour, time.minute)),
                  ),
                ),
                if (time != null)
                  IconButton(
                    tooltip: 'Make it all day',
                    icon: const Icon(Icons.close),
                    onPressed: () => setState(() => _time = null),
                  ),
              ],
            ),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
            const SizedBox(height: 16),
            FilledButton(onPressed: _save, child: const Text('Save plan')),
          ],
        ),
      ),
    );
  }
}
