import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:study_analyzer/format.dart';
import 'package:study_analyzer/local_backend.dart';

/// Test untuk LocalBackend: aturan harus sama dengan PHP di website.
void main() {
  late Directory dir;
  late File file;
  final backend = LocalBackend.instance;

  DateTime daysAgo(int n, {int hour = 10}) {
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day - n, hour);
  }

  Map<String, dynamic> completed(int id, DateTime ended, int seconds) => {
        'id': id,
        'subject': 'S$id',
        'planned_seconds': seconds,
        'elapsed_seconds': seconds,
        'status': 'completed',
        'started_at': ended.subtract(Duration(seconds: seconds)).toIso8601String(),
        'running_since': null,
        'ended_at': ended.toIso8601String(),
      };

  Future<void> loadWith(Map<String, dynamic> data) async {
    await file.writeAsString(jsonEncode(data));
    await backend.loadFrom(file);
  }

  setUp(() async {
    dir = await Directory.systemTemp.createTemp('study_test');
    file = File('${dir.path}/study_data.json');
    await backend.loadFrom(file); // data kosong
  });

  tearDown(() => dir.delete(recursive: true));

  test('empty data gives a sensible dashboard', () async {
    await backend.setProfileName('Mya');
    final d = await backend.get('dashboard.php', null);
    expect(d['user'], {'name': 'Mya'});
    expect(d['today_minutes'], 0);
    expect(d['streak_days'], 0);
    expect(d['active_session'], isNull);
    expect((d['last_7_days'] as List).length, 7);
    expect((d['last_7_days'] as List).last['date'], ymd(DateTime.now()));
    expect(d['suggestion'], {
      'minutes': 25,
      'reason': 'Start with a focused 25-minute session and build a steady routine.',
    });
  });

  test('data survives a restart (saved to file)', () async {
    await backend.setProfileName('Mya');
    await backend.post('timer.php', {'action': 'start', 'subject': 'Biology', 'duration_seconds': 600});
    await backend.loadFrom(file); // seperti membuka aplikasi lagi
    expect(backend.profileName, 'Mya');
    final d = await backend.get('dashboard.php', null);
    expect(d['active_session']['subject'], 'Biology');
  });

  test('streak counts consecutive days, starting yesterday if nothing today', () async {
    await loadWith({
      'sessions': [
        completed(1, daysAgo(1), 600),
        completed(2, daysAgo(2), 600),
        completed(3, daysAgo(3), 600),
        completed(4, daysAgo(5), 600), // ada jeda di hari ke-4 → tidak dihitung
      ],
    });
    var d = await backend.get('dashboard.php', null);
    expect(d['streak_days'], 3);

    await loadWith({
      'sessions': [completed(1, daysAgo(0), 1800), completed(2, daysAgo(1), 600)],
    });
    d = await backend.get('dashboard.php', null);
    expect(d['streak_days'], 2);
    expect(d['today_minutes'], 30);
    // (1800 + 600) / 7 hari / 60 = 5 menit (dibulatkan ke bawah)
    expect(d['weekly_average'], 5);
    expect(d['suggestion']['minutes'], 25);
    expect(d['recent_sessions'][0]['subject'], 'S1'); // paling baru dulu
  });

  test('finish caps time at the planned duration and backdates ended_at', () async {
    final now = DateTime.now();
    await loadWith({
      'next_session_id': 2,
      'sessions': [
        {
          'id': 1,
          'subject': 'Math',
          'planned_seconds': 1500, // 25 menit
          'elapsed_seconds': 0,
          'status': 'running',
          'started_at': now.subtract(const Duration(hours: 1)).toIso8601String(),
          'running_since': now.subtract(const Duration(hours: 1)).toIso8601String(),
          'ended_at': null,
        },
      ],
    });
    final before = await backend.get('dashboard.php', null);
    expect(before['active_session']['elapsed_seconds'], 1500); // tidak lebih dari rencana

    await backend.post('timer.php', {'action': 'finish'});
    final saved = jsonDecode(await file.readAsString())['sessions'][0];
    expect(saved['status'], 'completed');
    expect(saved['elapsed_seconds'], 1500);
    final ended = DateTime.parse(saved['ended_at'] as String);
    // Timer habis 35 menit yang lalu (60 - 25).
    expect(now.difference(ended).inMinutes, inInclusiveRange(34, 36));
  });

  test('pause, resume and the one-active-session rule', () async {
    await backend.post('timer.php', {'action': 'start', 'subject': 'Chem', 'duration_seconds': 600});
    expect(
      () => backend.post('timer.php', {'action': 'start', 'subject': 'X', 'duration_seconds': 60}),
      throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 409)),
    );
    await backend.post('timer.php', {'action': 'pause'});
    expect(
      () => backend.post('timer.php', {'action': 'pause'}),
      throwsA(isA<ApiException>().having((e) => e.message, 'msg', 'This session is already paused.')),
    );
    await backend.post('timer.php', {'action': 'resume'});
    await backend.post('timer.php', {'action': 'cancel'});
    final d = await backend.get('dashboard.php', null);
    expect(d['active_session'], isNull);
    expect(d['recent_sessions'], isEmpty); // cancel tidak dihitung
  });

  test('start validates subject and duration', () async {
    for (final bad in [
      {'subject': '', 'duration_seconds': 60},
      {'subject': 'A', 'duration_seconds': 0},
      {'subject': 'A', 'duration_seconds': 7201},
      {'subject': 'x' * 101, 'duration_seconds': 60},
    ]) {
      expect(() => backend.post('timer.php', {'action': 'start', ...bad}), throwsA(isA<ApiException>()));
    }
  });

  test('planner: create, list in order, delete, and validation', () async {
    final today = DateTime.now();
    final tomorrow = ymd(DateTime(today.year, today.month, today.day + 1));
    await backend.post('planner.php', {'action': 'create', 'title': 'All day', 'date': tomorrow, 'time': '', 'notes': ''});
    await backend.post('planner.php', {'action': 'create', 'title': 'Late', 'date': tomorrow, 'time': '18:00', 'notes': ''});
    await backend.post('planner.php', {'action': 'create', 'title': 'Early', 'date': tomorrow, 'time': '08:30', 'notes': 'n'});

    final from = ymd(today);
    final to = ymd(DateTime(today.year, today.month, today.day + 7));
    final list = await backend.get('planner.php', {'from': from, 'to': to});
    expect((list['plans'] as List).map((p) => p['title']), ['Early', 'Late', 'All day']);
    expect((list['plans'] as List).last['time'], isNull);

    final yesterday = ymd(DateTime(today.year, today.month, today.day - 1));
    for (final bad in [
      {'title': '', 'date': tomorrow},
      {'title': 'A', 'date': yesterday}, // masa lalu
      {'title': 'A', 'date': '2026-02-31'}, // tanggal tidak ada
      {'title': 'A', 'date': tomorrow, 'time': '25:00'},
    ]) {
      expect(() => backend.post('planner.php', {'action': 'create', ...bad}), throwsA(isA<ApiException>()));
    }

    final firstId = (list['plans'] as List).first['id'] as int;
    await backend.post('planner.php', {'action': 'delete', 'id': firstId});
    expect(
      () => backend.post('planner.php', {'action': 'delete', 'id': firstId}),
      throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 404)),
    );
    expect(
      () => backend.get('planner.php', {'from': to, 'to': from}),
      throwsA(isA<ApiException>()),
    );
  });

  test('planner minutes_by_date only counts completed sessions in range', () async {
    await loadWith({
      'sessions': [
        completed(1, daysAgo(1), 1200),
        completed(2, daysAgo(1, hour: 15), 600),
        {...completed(3, daysAgo(1), 900), 'status': 'cancelled'},
        completed(4, daysAgo(40), 600),
      ],
    });
    final today = DateTime.now();
    final list = await backend.get('planner.php', {
      'from': ymd(DateTime(today.year, today.month, today.day - 7)),
      'to': ymd(DateTime(today.year, today.month, today.day + 1)),
    });
    expect(list['minutes_by_date'], {ymd(daysAgo(1)): 30});
  });
}
