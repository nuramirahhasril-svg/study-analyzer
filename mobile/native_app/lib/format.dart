// Fungsi kecil untuk menampilkan tanggal & waktu (tanpa package tambahan).

const _months = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
  'August', 'September', 'October', 'November', 'December'];
const _weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

String _two(int n) => n.toString().padLeft(2, '0');

/// 2026-11-13
String ymd(DateTime d) => '${d.year}-${_two(d.month)}-${_two(d.day)}';

/// Friday, November 13
String longDate(DateTime d) => '${_weekdays[d.weekday - 1]}, ${_months[d.month - 1]} ${d.day}';

/// Nov 13
String shortDate(DateTime d) => '${_months[d.month - 1].substring(0, 3)} ${d.day}';

/// 2:30 PM
String clockTime(int hour, int minute) {
  final h = hour % 12 == 0 ? 12 : hour % 12;
  return '$h:${_two(minute)} ${hour < 12 ? 'AM' : 'PM'}';
}

/// "14:30" → "2:30 PM"
String planTime(String hhmm) {
  final parts = hhmm.split(':');
  return clockTime(int.parse(parts[0]), int.parse(parts[1]));
}

/// Nov 13 · 2:30 PM
String sessionTime(DateTime d) => '${shortDate(d)} · ${clockTime(d.hour, d.minute)}';

/// 1500 detik → "25:00"
String countdown(int seconds) => '${_two(seconds ~/ 60)}:${_two(seconds % 60)}';
