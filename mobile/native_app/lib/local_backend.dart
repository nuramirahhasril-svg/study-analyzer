import 'dart:convert';
import 'dart:io';

import 'package:path_provider/path_provider.dart';

import 'format.dart';

/// Error yang siap ditampilkan ke user (sama seperti balasan error dari server dulu).
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

/// "Server" yang berjalan DI DALAM HP.
///
/// Dulu aplikasi ini mengirim request ke PHP (api/mobile/*.php) di komputer.
/// Sekarang semua data disimpan di file JSON di HP, dan aturan yang sama
/// (timer, streak, rata-rata, saran, planner) dihitung di sini.
/// Nama "path" seperti 'dashboard.php' tetap dipakai supaya layar-layar
/// aplikasi tidak perlu diubah.
///
/// Isi file (study_data.json):
/// {
///   "profile": {"name": "Mya"} | null,
///   "prefs": {"daily_goal": 120, "default_session": 25},
///   "next_session_id": 1, "next_plan_id": 1,
///   "sessions": [{id, subject, planned_seconds, elapsed_seconds, status,
///                 started_at, running_since, ended_at}],
///   "plans": [{id, title, notes, date, time}]
/// }
/// Waktu disimpan sebagai teks ISO jam lokal HP, contoh "2026-11-14T09:30:00.000".
class LocalBackend {
  LocalBackend._();

  static final LocalBackend instance = LocalBackend._();

  late File _file;
  Map<String, dynamic> _data = _emptyData();

  static Map<String, dynamic> _emptyData() => {
        'profile': null,
        'prefs': {'daily_goal': 120, 'default_session': 25},
        'next_session_id': 1,
        'next_plan_id': 1,
        'sessions': <dynamic>[],
        'plans': <dynamic>[],
      };

  /// Baca data dari file (dipanggil sekali saat aplikasi dibuka).
  Future<void> load() async {
    final dir = await getApplicationDocumentsDirectory();
    await loadFrom(File('${dir.path}${Platform.pathSeparator}study_data.json'));
  }

  /// Sama dengan [load], tapi file-nya ditentukan sendiri (dipakai oleh test).
  Future<void> loadFrom(File file) async {
    _file = file;
    _data = _emptyData();
    if (!await _file.exists()) return;
    try {
      final decoded = jsonDecode(await _file.readAsString());
      if (decoded is Map<String, dynamic>) {
        _data = {..._emptyData(), ...decoded};
      }
    } catch (_) {
      // File rusak (sangat jarang). Simpan cadangannya, lalu mulai dari data kosong.
      await _file.copy('${_file.path}.broken');
    }
  }

  /// Simpan ke file. Ditulis ke file sementara dulu lalu di-rename,
  /// supaya data tidak rusak kalau aplikasi tertutup di tengah penyimpanan.
  Future<void> _save() async {
    final temp = File('${_file.path}.tmp');
    await temp.writeAsString(jsonEncode(_data), flush: true);
    await temp.rename(_file.path);
  }

  /* =========================================================
   * Profil (pengganti login)
   * ========================================================= */

  String? get profileName => (_data['profile'] as Map<String, dynamic>?)?['name'] as String?;

  Future<void> setProfileName(String? name) async {
    _data['profile'] = name == null ? null : {'name': name};
    await _save();
  }

  /* =========================================================
   * Pintu masuk: sama seperti request GET/POST ke server dulu
   * ========================================================= */

  Future<Map<String, dynamic>> get(String path, Map<String, String>? query) async {
    switch (path) {
      case 'dashboard.php':
        return _dashboard();
      case 'planner.php':
        return _plannerList(query ?? const {});
    }
    throw ApiException('Unknown request: $path', statusCode: 404);
  }

  Future<Map<String, dynamic>> post(String path, Map<String, dynamic> body) async {
    switch (path) {
      case 'timer.php':
        return _timerAction(body);
      case 'planner.php':
        return _plannerAction(body);
    }
    throw ApiException('Unknown request: $path', statusCode: 404);
  }

  /* =========================================================
   * Bantuan
   * ========================================================= */

  List<Map<String, dynamic>> get _sessions => (_data['sessions'] as List).cast<Map<String, dynamic>>();
  List<Map<String, dynamic>> get _plans => (_data['plans'] as List).cast<Map<String, dynamic>>();
  Map<String, dynamic> get _prefs => _data['prefs'] as Map<String, dynamic>;

  /// Hari ini jam 00:00 (jam lokal HP).
  static DateTime _today() {
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day);
  }

  static DateTime? _time(dynamic value) => value is String ? DateTime.tryParse(value) : null;

  static String _iso(DateTime d) => d.toIso8601String();

  static int _int(dynamic value) => value is num ? value.toInt() : int.tryParse('$value') ?? 0;

  /// Panjang teks dalam karakter (emoji dihitung 1), sama seperti regex /u di PHP.
  static int _chars(String text) => text.runes.length;

  /// "YYYY-MM-DD" → tanggal. null kalau formatnya salah (contoh: 2026-02-31).
  static DateTime? _parseDate(String text) {
    final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(text);
    if (match == null) return null;
    final y = int.parse(match.group(1)!), m = int.parse(match.group(2)!), d = int.parse(match.group(3)!);
    final date = DateTime(y, m, d);
    return (date.year == y && date.month == m && date.day == d) ? date : null;
  }

  Iterable<Map<String, dynamic>> get _completed => _sessions.where((s) => s['status'] == 'completed');

  /// Sesi yang sedang berjalan atau di-pause (paling baru), atau null.
  Map<String, dynamic>? get _activeRow {
    Map<String, dynamic>? active;
    for (final s in _sessions) {
      if ((s['status'] == 'running' || s['status'] == 'paused') &&
          (active == null || _int(s['id']) > _int(active['id']))) {
        active = s;
      }
    }
    return active;
  }

  /// Total detik belajar sesi sampai sekarang (belum dibatasi durasi rencana).
  static int _studiedSeconds(Map<String, dynamic> s, DateTime now) {
    var seconds = _int(s['elapsed_seconds']);
    final since = _time(s['running_since']);
    if (s['status'] == 'running' && since != null) {
      final running = now.difference(since).inSeconds;
      if (running > 0) seconds += running;
    }
    return seconds;
  }

  /// Menit belajar per tanggal ("YYYY-MM-DD" → menit) untuk sesi selesai di [from, to).
  Map<String, int> _minutesByDate(DateTime from, DateTime to) {
    final seconds = <String, int>{};
    for (final s in _completed) {
      final ended = _time(s['ended_at']);
      if (ended == null || ended.isBefore(from) || !ended.isBefore(to)) continue;
      final key = ymd(ended);
      seconds[key] = (seconds[key] ?? 0) + _int(s['elapsed_seconds']);
    }
    return seconds.map((date, secs) => MapEntry(date, secs ~/ 60));
  }

  /* =========================================================
   * Dashboard (Home, Timer, Progress) — sama dengan api/mobile/dashboard.php
   * ========================================================= */

  Map<String, dynamic> _dashboard() {
    final now = DateTime.now();
    final today = _today();
    final tomorrow = DateTime(today.year, today.month, today.day + 1);
    final dailyGoal = _int(_prefs['daily_goal']);
    final defaultSession = _int(_prefs['default_session']);

    // Menit belajar hari ini.
    final todayMinutes = (_minutesByDate(today, tomorrow)[ymd(today)] ?? 0);

    // Rata-rata 7 hari terakhir (termasuk hari ini).
    final weekStart = DateTime(today.year, today.month, today.day - 6);
    var weekSeconds = 0;
    for (final s in _completed) {
      final ended = _time(s['ended_at']);
      if (ended != null && !ended.isBefore(weekStart)) weekSeconds += _int(s['elapsed_seconds']);
    }
    final weeklyAverage = weekSeconds ~/ 7 ~/ 60;

    // Streak: hari berturut-turut belajar. Kalau hari ini belum belajar,
    // dihitung mulai kemarin (jadi streak tidak hilang di pagi hari).
    final yearAgo = DateTime(today.year, today.month, today.day - 366);
    final studyDays = <String>{};
    String? latestDay;
    for (final s in _completed) {
      final ended = _time(s['ended_at']);
      if (ended == null || ended.isBefore(yearAgo)) continue;
      final key = ymd(ended);
      studyDays.add(key);
      if (latestDay == null || key.compareTo(latestDay) > 0) latestDay = key;
    }
    var day = today;
    if (latestDay != null && latestDay != ymd(today)) day = DateTime(day.year, day.month, day.day - 1);
    var streak = 0;
    while (studyDays.contains(ymd(day))) {
      streak++;
      day = DateTime(day.year, day.month, day.day - 1);
    }

    // Saran durasi hari ini.
    final remaining = (dailyGoal - todayMinutes).clamp(0, 1 << 30);
    final suggestion = defaultSession < remaining ? defaultSession : remaining;
    final String reason;
    if (suggestion == 0) {
      reason = 'You have reached your daily study goal. Take a well-earned break.';
    } else if (weeklyAverage == 0) {
      reason = 'Start with a focused $suggestion-minute session and build a steady routine.';
    } else {
      reason = 'Your 7-day average is $weeklyAverage minutes a day. Try $suggestion focused minutes today.';
    }

    // Sesi aktif.
    Map<String, dynamic>? activeSession;
    final active = _activeRow;
    if (active != null) {
      final planned = _int(active['planned_seconds']);
      final studied = _studiedSeconds(active, now);
      activeSession = {
        'id': _int(active['id']),
        'subject': active['subject'],
        'planned_seconds': planned,
        'elapsed_seconds': studied < planned ? studied : planned,
        'status': active['status'],
      };
    }

    // 10 sesi terakhir yang selesai.
    final recent = _completed.where((s) => _time(s['ended_at']) != null).toList()
      ..sort((a, b) => _time(b['ended_at'])!.compareTo(_time(a['ended_at'])!));
    final recentSessions = recent
        .take(10)
        .map((s) => {
              'subject': s['subject'],
              'minutes': _int(s['elapsed_seconds']) ~/ 60,
              'ended_at': s['ended_at'],
            })
        .toList();

    // Grafik 7 hari terakhir.
    const labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    final byDate = _minutesByDate(weekStart, tomorrow);
    final lastSevenDays = [
      for (var i = 0; i < 7; i++)
        () {
          final d = DateTime(weekStart.year, weekStart.month, weekStart.day + i);
          return {'date': ymd(d), 'label': labels[d.weekday - 1], 'minutes': byDate[ymd(d)] ?? 0};
        }(),
    ];

    return {
      'user': {'name': profileName ?? 'Student'},
      'today': ymd(today),
      'daily_goal': dailyGoal,
      'default_session': defaultSession,
      'today_minutes': todayMinutes,
      'streak_days': streak,
      'weekly_average': weeklyAverage,
      'suggestion': {'minutes': suggestion, 'reason': reason},
      'active_session': activeSession,
      'recent_sessions': recentSessions,
      'last_7_days': lastSevenDays,
    };
  }

  /* =========================================================
   * Timer — sama dengan includes/timer_actions.php
   * ========================================================= */

  Future<Map<String, dynamic>> _timerAction(Map<String, dynamic> input) async {
    final action = input['action'];
    final now = DateTime.now();

    if (action == 'start') {
      final subject = '${input['subject'] ?? ''}'.trim();
      final planned = input['duration_seconds'];
      if (subject.isEmpty || _chars(subject) > 100 || planned is! int || planned < 1 || planned > 7200) {
        throw ApiException('Enter a subject and a duration between 1 and 120 minutes.', statusCode: 422);
      }
      if (_activeRow != null) {
        throw ApiException('Finish or resume your current study session first.', statusCode: 409);
      }
      final id = _int(_data['next_session_id']);
      _data['next_session_id'] = id + 1;
      _sessions.add({
        'id': id,
        'subject': subject,
        'planned_seconds': planned,
        'elapsed_seconds': 0,
        'status': 'running',
        'started_at': _iso(now),
        'running_since': _iso(now),
        'ended_at': null,
      });
      await _save();
      return {
        'message': 'Your study session has started.',
        'session': {'id': id, 'subject': subject, 'planned_seconds': planned, 'elapsed_seconds': 0, 'status': 'running'},
      };
    }

    if (!const ['pause', 'resume', 'finish', 'cancel'].contains(action)) {
      throw ApiException('Choose a valid timer action.', statusCode: 422);
    }

    final session = _activeRow;
    if (session == null) throw ApiException('There is no active study session.', statusCode: 404);
    final status = session['status'];
    if (action == 'pause' && status != 'running') {
      throw ApiException('This session is already paused.', statusCode: 409);
    }
    if (action == 'resume' && status != 'paused') {
      throw ApiException('This session is already running.', statusCode: 409);
    }

    // Total waktu belajar, tapi TIDAK BOLEH lebih dari durasi yang direncanakan.
    // Contoh: timer 25 menit, aplikasi ditutup, baru dibuka besok → tetap 25 menit.
    final planned = _int(session['planned_seconds']);
    final total = _studiedSeconds(session, now);
    final studied = total < planned ? total : planned;
    final overtime = total - studied;

    final String message;
    if (action == 'pause') {
      session['elapsed_seconds'] = studied;
      session['status'] = 'paused';
      session['running_since'] = null;
      message = 'Your session is paused.';
    } else if (action == 'resume') {
      session['status'] = 'running';
      session['running_since'] = _iso(now);
      message = 'Your session has resumed.';
    } else if (action == 'finish') {
      // ended_at = saat timer benar-benar habis, supaya tercatat di tanggal yang benar.
      session['elapsed_seconds'] = studied;
      session['status'] = 'completed';
      session['running_since'] = null;
      session['ended_at'] = _iso(now.subtract(Duration(seconds: overtime)));
      message = 'Your study session is saved. Nice work!';
    } else {
      session['status'] = 'cancelled';
      session['running_since'] = null;
      session['ended_at'] = _iso(now);
      message = 'Your study session was cancelled.';
    }
    await _save();
    return {'message': message};
  }

  /* =========================================================
   * Planner — sama dengan api/mobile/planner.php + includes/planner_actions.php
   * ========================================================= */

  Map<String, dynamic> _plannerList(Map<String, String> query) {
    final from = _parseDate(query['from'] ?? '');
    final to = _parseDate(query['to'] ?? '');
    if (from == null || to == null || !to.isAfter(from) || to.difference(from).inDays > 100) {
      throw ApiException('Choose a valid date range (up to 100 days).', statusCode: 422);
    }
    final fromText = ymd(from), toText = ymd(to);

    final plans = _plans
        .where((p) => (p['date'] as String).compareTo(fromText) >= 0 && (p['date'] as String).compareTo(toText) < 0)
        .toList()
      // Urut: tanggal, lalu yang punya jam dulu (rencana "sepanjang hari" di akhir), lalu jam, lalu id.
      ..sort((a, b) {
        final byDate = (a['date'] as String).compareTo(b['date'] as String);
        if (byDate != 0) return byDate;
        final ta = a['time'] as String?, tb = b['time'] as String?;
        if ((ta == null) != (tb == null)) return ta == null ? 1 : -1;
        if (ta != null && tb != null && ta != tb) return ta.compareTo(tb);
        return _int(a['id']).compareTo(_int(b['id']));
      });

    return {
      'plans': plans.take(300).map((p) => Map<String, dynamic>.from(p)).toList(),
      'minutes_by_date': _minutesByDate(from, to),
    };
  }

  Future<Map<String, dynamic>> _plannerAction(Map<String, dynamic> input) async {
    final action = input['action'];

    if (action == 'create') {
      final title = '${input['title'] ?? ''}'.trim();
      final notes = '${input['notes'] ?? ''}'.trim();
      final dateText = '${input['date'] ?? ''}';
      final time = '${input['time'] ?? ''}'.trim();

      final validTitle = title.isNotEmpty && _chars(title) <= 120;
      final validNotes = _chars(notes) <= 500;
      final date = _parseDate(dateText);
      final validTime = time.isEmpty || RegExp(r'^(?:[01]\d|2[0-3]):[0-5]\d$').hasMatch(time);
      if (!validTitle || !validNotes || date == null || !validTime) {
        throw ApiException('Add a title, a valid date, and optional notes up to 500 characters.', statusCode: 422);
      }

      // Rencana hanya boleh dari hari ini sampai 2 tahun ke depan.
      final today = _today();
      final limit = DateTime(today.year + 2, today.month, today.day);
      if (date.isBefore(today) || date.isAfter(limit)) {
        throw ApiException('Plans can be scheduled from today up to two years ahead.', statusCode: 422);
      }

      final id = _int(_data['next_plan_id']);
      _data['next_plan_id'] = id + 1;
      _plans.add({'id': id, 'title': title, 'notes': notes, 'date': ymd(date), 'time': time.isEmpty ? null : time});
      await _save();
      return {'message': 'Your study plan has been saved.', 'id': id};
    }

    if (action == 'delete') {
      final id = input['id'];
      if (id is! int || id < 1) throw ApiException('Choose a valid study plan to remove.', statusCode: 422);
      final before = _plans.length;
      _plans.removeWhere((p) => _int(p['id']) == id);
      if (_plans.length == before) throw ApiException('That study plan could not be found.', statusCode: 404);
      await _save();
      return {'message': 'The study plan has been removed.'};
    }

    throw ApiException('Choose a valid planner action.', statusCode: 422);
  }
}
