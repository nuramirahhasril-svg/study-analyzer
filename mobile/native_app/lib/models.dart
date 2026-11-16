// Data dari LocalBackend (format JSON yang sama seperti dari server dulu), diubah menjadi class Dart.

int _int(dynamic value) => value is num ? value.toInt() : int.tryParse('$value') ?? 0;

class ActiveSession {
  ActiveSession({
    required this.id,
    required this.subject,
    required this.plannedSeconds,
    required this.elapsedSeconds,
    required this.status,
  });

  final int id;
  final String subject;
  final int plannedSeconds;
  final int elapsedSeconds;
  final String status; // 'running' | 'paused'

  bool get isRunning => status == 'running';

  factory ActiveSession.fromJson(Map<String, dynamic> json) => ActiveSession(
        id: _int(json['id']),
        subject: json['subject'] as String,
        plannedSeconds: _int(json['planned_seconds']),
        elapsedSeconds: _int(json['elapsed_seconds']),
        status: json['status'] as String,
      );
}

class RecentSession {
  RecentSession({required this.subject, required this.minutes, this.endedAt});

  final String subject;
  final int minutes;
  final DateTime? endedAt;

  factory RecentSession.fromJson(Map<String, dynamic> json) => RecentSession(
        subject: json['subject'] as String,
        minutes: _int(json['minutes']),
        endedAt: json['ended_at'] == null ? null : DateTime.tryParse(json['ended_at'] as String),
      );
}

class DayMinutes {
  DayMinutes({required this.label, required this.minutes});

  final String label; // "Mon", "Tue", ...
  final int minutes;

  factory DayMinutes.fromJson(Map<String, dynamic> json) =>
      DayMinutes(label: json['label'] as String, minutes: _int(json['minutes']));
}

class Dashboard {
  Dashboard({
    required this.userName,
    required this.dailyGoal,
    required this.defaultSession,
    required this.todayMinutes,
    required this.streakDays,
    required this.weeklyAverage,
    required this.suggestionMinutes,
    required this.suggestionReason,
    required this.activeSession,
    required this.recentSessions,
    required this.lastSevenDays,
  });

  final String userName;
  final int dailyGoal;
  final int defaultSession;
  final int todayMinutes;
  final int streakDays;
  final int weeklyAverage;
  final int suggestionMinutes;
  final String suggestionReason;
  final ActiveSession? activeSession;
  final List<RecentSession> recentSessions;
  final List<DayMinutes> lastSevenDays;

  String get firstName => userName.trim().split(' ').first;
  int get remainingMinutes => todayMinutes >= dailyGoal ? 0 : dailyGoal - todayMinutes;
  double get goalProgress => dailyGoal <= 0 ? 0 : (todayMinutes / dailyGoal).clamp(0.0, 1.0).toDouble();

  factory Dashboard.fromJson(Map<String, dynamic> json) {
    final suggestion = json['suggestion'] as Map<String, dynamic>;
    final active = json['active_session'];
    return Dashboard(
      userName: (json['user'] as Map<String, dynamic>)['name'] as String,
      dailyGoal: _int(json['daily_goal']),
      defaultSession: _int(json['default_session']),
      todayMinutes: _int(json['today_minutes']),
      streakDays: _int(json['streak_days']),
      weeklyAverage: _int(json['weekly_average']),
      suggestionMinutes: _int(suggestion['minutes']),
      suggestionReason: suggestion['reason'] as String,
      activeSession: active is Map<String, dynamic> ? ActiveSession.fromJson(active) : null,
      recentSessions: (json['recent_sessions'] as List)
          .map((item) => RecentSession.fromJson(item as Map<String, dynamic>))
          .toList(),
      lastSevenDays: (json['last_7_days'] as List)
          .map((item) => DayMinutes.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }
}

class StudyPlan {
  StudyPlan({required this.id, required this.title, required this.notes, required this.date, this.time});

  final int id;
  final String title;
  final String notes;
  final String date; // "YYYY-MM-DD"
  final String? time; // "HH:MM" atau null (sepanjang hari)

  factory StudyPlan.fromJson(Map<String, dynamic> json) => StudyPlan(
        id: _int(json['id']),
        title: json['title'] as String,
        notes: (json['notes'] as String?) ?? '',
        date: json['date'] as String,
        time: json['time'] as String?,
      );
}
