/// Helpers for reading loosely-typed JSON coming from the Laravel API.
///
/// The backend returns associative arrays rather than typed resources, so
/// screens work directly with `Map<String, dynamic>` values. These helpers
/// keep the null/type handling in one place.
class Json {
  Json._();

  static Map<String, dynamic> map(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return value.map((k, v) => MapEntry('$k', v));
    return const {};
  }

  static List<Map<String, dynamic>> list(dynamic value) {
    if (value is List) {
      return value.map(Json.map).toList();
    }
    return const [];
  }

  static int intOr(dynamic value, [int fallback = 0]) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse('$value') ?? fallback;
  }

  static int? intOrNull(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse('$value');
  }

  static double doubleOr(dynamic value, [double fallback = 0]) {
    if (value is double) return value;
    if (value is num) return value.toDouble();
    return double.tryParse('$value') ?? fallback;
  }

  static String string(dynamic value, [String fallback = '']) {
    if (value == null) return fallback;
    final text = '$value';
    return text.isEmpty ? fallback : text;
  }

  static String? stringOrNull(dynamic value) {
    if (value == null) return null;
    final text = '$value';
    return text.isEmpty ? null : text;
  }

  /// Turns a `YYYY-MM-DD HH:MM:SS` (or ISO-8601) value into a [DateTime].
  static DateTime? dateTime(dynamic value) {
    final text = value?.toString();
    if (text == null || text.isEmpty) return null;
    return DateTime.tryParse(text.replaceFirst(' ', 'T'));
  }

  /// `12 Aug 2026` style label, or [fallback] when the value is unusable.
  static String dateLabel(dynamic value, {String fallback = '—'}) {
    final dt = dateTime(value);
    if (dt == null) return fallback;
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];
    return '${dt.day} ${months[dt.month - 1]} ${dt.year}';
  }

  /// `12 Aug 2026, 15:30` style label.
  static String dateTimeLabel(dynamic value, {String fallback = '—'}) {
    final dt = dateTime(value);
    if (dt == null) return fallback;
    final h = dt.hour.toString().padLeft(2, '0');
    final m = dt.minute.toString().padLeft(2, '0');
    return '${dateLabel(value)}, $h:$m';
  }

  /// `1st` / `2nd` / `3rd` / `4th` ... used by the standings table.
  static String ordinal(int n) {
    if (n <= 0) return '$n';
    final mod100 = n % 100;
    if (mod100 >= 11 && mod100 <= 13) return '${n}th';
    switch (n % 10) {
      case 1:
        return '${n}st';
      case 2:
        return '${n}nd';
      case 3:
        return '${n}rd';
      default:
        return '${n}th';
    }
  }
}
