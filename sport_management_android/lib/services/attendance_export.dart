import 'dart:io';

import 'package:excel/excel.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

/// Builds Excel workbooks for the management reports and opens the Android
/// share sheet so a manager can save/email/WhatsApp the file.
///
/// All data comes from the JSON API (no server spreadsheet library), which
/// keeps the backend free of extra PHP dependencies.
class AttendanceExport {
  AttendanceExport._();

  static const _mime =
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

  static final _headerStyle = CellStyle(
    bold: true,
    fontColorHex: ExcelColor.white,
    backgroundColorHex: ExcelColor.blue,
    horizontalAlign: HorizontalAlign.Center,
  );

  static final _titleStyle = CellStyle(
    bold: true,
    fontSize: 14,
    fontColorHex: ExcelColor.blue,
  );

  static final _labelStyle = CellStyle(bold: true);

  /// One player's full record: profile, attendance log and performance.
  static Future<File> playerRecord({
    required Map<String, dynamic> player,
    required String teamName,
    required List<Map<String, dynamic>> attendance,
    required Map<String, dynamic> summary,
  }) async {
    final excel = Excel.createExcel();
    final firstSheet = excel.getDefaultSheet() ?? 'Sheet1';
    excel.rename(firstSheet, 'Player record');

    final name = _fullName(player);
    final profile = excel['Player record'];
    profile.appendRow([TextCellValue('Player record')]);
    profile.cell(CellIndex.indexByColumnRow(columnIndex: 0, rowIndex: 0))
        .cellStyle = _titleStyle;
    profile.appendRow([TextCellValue('')]);
    _kv(profile, 'Player', name);
    _kv(profile, 'Team', teamName);
    _kv(profile, 'Jersey', _text(player['jersey_number']));
    _kv(profile, 'Position', _text(player['position']));
    _kv(profile, 'Nationality', _text(player['nationality']));
    _kv(profile, 'Date of birth', _text(player['date_of_birth']));
    _kv(profile, 'Preferred foot', _text(player['foot']));
    profile.appendRow([TextCellValue('')]);
    _kv(profile, 'Sessions marked', '${summary['marked'] ?? 0}');
    _kv(profile, 'Attended (present + late)', '${summary['attended'] ?? 0}');
    _kv(profile, 'Attendance rate', '${summary['rate'] ?? 0}%');
    _kv(profile, 'Present', '${summary['present'] ?? 0}');
    _kv(profile, 'Late', '${summary['late'] ?? 0}');
    _kv(profile, 'Absent', '${summary['absent'] ?? 0}');
    _kv(profile, 'Excused', '${summary['excused'] ?? 0}');

    final log = excel['Attendance'];
    _header(log, const [
      'Date',
      'Session',
      'Category',
      'Status',
      'Checked in at',
      'Notes',
    ]);
    for (final row in attendance) {
      log.appendRow([
        TextCellValue(_date(row['event_starts_at'])),
        TextCellValue(_text(row['event_title'])),
        TextCellValue(_text(row['event_type'])),
        TextCellValue(_text(row['status']).toUpperCase()),
        TextCellValue(_dateTime(row['checked_in_at'])),
        TextCellValue(_text(row['notes'])),
      ]);
    }
    _widths(log, const [14, 38, 12, 12, 20, 38]);

    final performance = excel['Performance'];
    _header(performance, const ['Metric', 'Value']);
    _kv(performance, 'Appearances', '${player['appearances'] ?? 0}');
    _kv(performance, 'Goals', '${player['goals'] ?? 0}');
    _kv(performance, 'Assists', '${player['assists'] ?? 0}');
    _kv(performance, 'Minutes played', '${player['minutes_played'] ?? 0}');
    _kv(performance, 'Yellow cards', '${player['yellow_cards'] ?? 0}');
    _kv(performance, 'Red cards', '${player['red_cards'] ?? 0}');
    _kv(performance, 'Rating', _text(player['rating'], '—'));
    _kv(performance, 'Attendance rate', '${summary['rate'] ?? 0}%');
    _widths(performance, const [26, 22]);

    final file = await _write(
      excel,
      '${_slug(name)}_record_${_stamp()}.xlsx',
    );
    await shareFile(
      file,
      subject: '$name — player record',
      text: 'Attendance and performance record for $name.',
    );
    return file;
  }

  /// Attendance sheet for one event/session (every squad member).
  static Future<File> eventAttendance({
    required Map<String, dynamic> event,
    required String teamName,
    required List<Map<String, dynamic>> squad,
    required Map<String, dynamic> tally,
  }) async {
    final excel = Excel.createExcel();
    final firstSheet = excel.getDefaultSheet() ?? 'Sheet1';
    excel.rename(firstSheet, 'Session');

    final info = excel['Session'];
    info.appendRow([TextCellValue(_text(event['title'], 'Session'))]);
    info.cell(CellIndex.indexByColumnRow(columnIndex: 0, rowIndex: 0))
        .cellStyle = _titleStyle;
    info.appendRow([TextCellValue('')]);
    _kv(info, 'Team', teamName);
    _kv(info, 'Category', _text(event['type']));
    _kv(info, 'Starts at', _dateTime(event['starts_at']));
    _kv(info, 'Squad size', '${squad.length}');
    _kv(info, 'Present', '${tally['present'] ?? 0}');
    _kv(info, 'Late', '${tally['late'] ?? 0}');
    _kv(info, 'Absent', '${tally['absent'] ?? 0}');
    _kv(info, 'Excused', '${tally['excused'] ?? 0}');
    _kv(info, 'Attendance rate', '${tally['rate'] ?? 0}%');

    final sheet = excel['Attendance'];
    _header(sheet, const [
      '#',
      'Player',
      'Jersey',
      'Position',
      'Status',
      'Checked in at',
      'Notes',
    ]);
    for (var i = 0; i < squad.length; i++) {
      final player = squad[i];
      sheet.appendRow([
        IntCellValue(i + 1),
        TextCellValue(_fullName(player)),
        TextCellValue(_text(player['jersey_number'])),
        TextCellValue(_text(player['position'])),
        TextCellValue(
          player['status'] == null ? 'NOT MARKED' : _text(player['status']).toUpperCase(),
        ),
        TextCellValue(_dateTime(player['checked_in_at'])),
        TextCellValue(_text(JsonLike.map(player['attendance'])['notes'])),
      ]);
    }
    _widths(sheet, const [5, 28, 9, 14, 14, 20, 34]);

    final file = await _write(
      excel,
      '${_slug(_text(event['title'], 'session'))}_attendance_${_stamp()}.xlsx',
    );
    await shareFile(
      file,
      subject: '${_text(event['title'], 'Session')} — attendance',
      text: 'Attendance sheet for ${_text(event['title'], 'the session')}.',
    );
    return file;
  }

  /// Squad-wide attendance summary (dashboard export).
  static Future<File> squadAttendance({
    required String teamName,
    required Map<String, dynamic> summary,
    required List<Map<String, dynamic>> perPlayer,
  }) async {
    final excel = Excel.createExcel();
    final firstSheet = excel.getDefaultSheet() ?? 'Sheet1';
    excel.rename(firstSheet, 'Squad summary');

    final info = excel['Squad summary'];
    info.appendRow([TextCellValue('$teamName — attendance summary')]);
    info.cell(CellIndex.indexByColumnRow(columnIndex: 0, rowIndex: 0))
        .cellStyle = _titleStyle;
    info.appendRow([TextCellValue('')]);
    _kv(info, 'Attendance rate', '${summary['rate'] ?? 0}%');
    _kv(info, 'Sessions marked', '${summary['marked'] ?? 0}');
    _kv(info, 'Present', '${summary['present'] ?? 0}');
    _kv(info, 'Late', '${summary['late'] ?? 0}');
    _kv(info, 'Absent', '${summary['absent'] ?? 0}');
    _kv(info, 'Excused', '${summary['excused'] ?? 0}');

    final sheet = excel['Players'];
    _header(sheet, const [
      '#',
      'Player',
      'Jersey',
      'Position',
      'Present',
      'Late',
      'Absent',
      'Excused',
      'Marked',
      'Rate',
    ]);
    for (var i = 0; i < perPlayer.length; i++) {
      final player = perPlayer[i];
      sheet.appendRow([
        IntCellValue(i + 1),
        TextCellValue(_fullName(player)),
        TextCellValue(_text(player['jersey_number'])),
        TextCellValue(_text(player['position'])),
        IntCellValue(_int(player['present'])),
        IntCellValue(_int(player['late'])),
        IntCellValue(_int(player['absent'])),
        IntCellValue(_int(player['excused'])),
        IntCellValue(_int(player['marked'])),
        TextCellValue('${_int(player['rate'])}%'),
      ]);
    }
    _widths(sheet, const [5, 28, 9, 14, 10, 9, 9, 11, 9, 9]);

    final file = await _write(
      excel,
      '${_slug(teamName)}_attendance_${_stamp()}.xlsx',
    );
    await shareFile(
      file,
      subject: '$teamName — attendance summary',
      text: 'Squad attendance summary for $teamName.',
    );
    return file;
  }

  // -----------------------------------------------------------------
  // Helpers
  // -----------------------------------------------------------------

  static Future<void> shareFile(
    File file, {
    String? subject,
    String? text,
  }) async {
    await SharePlus.instance.share(
      ShareParams(
        files: [XFile(file.path, mimeType: _mime)],
        subject: subject,
        text: text,
      ),
    );
  }

  static Future<File> _write(Excel excel, String fileName) async {
    final directory = await getApplicationDocumentsDirectory();
    final exportDir = Directory('${directory.path}${Platform.pathSeparator}exports');
    if (!await exportDir.exists()) {
      await exportDir.create(recursive: true);
    }

    final file = File('${exportDir.path}${Platform.pathSeparator}$fileName');
    final bytes = excel.save();
    if (bytes == null) {
      throw const FileSystemException('Could not build the Excel workbook.');
    }
    await file.writeAsBytes(bytes, flush: true);
    return file;
  }

  static void _header(Sheet sheet, List<String> labels) {
    sheet.appendRow(labels.map<CellValue?>(TextCellValue.new).toList());
    final rowIndex = sheet.maxRows - 1;
    for (var i = 0; i < labels.length; i++) {
      sheet
          .cell(CellIndex.indexByColumnRow(columnIndex: i, rowIndex: rowIndex))
          .cellStyle = _headerStyle;
    }
  }

  static void _kv(Sheet sheet, String label, String value) {
    sheet.appendRow([TextCellValue(label), TextCellValue(value)]);
    final rowIndex = sheet.maxRows - 1;
    sheet
        .cell(CellIndex.indexByColumnRow(columnIndex: 0, rowIndex: rowIndex))
        .cellStyle = _labelStyle;
  }

  static void _widths(Sheet sheet, List<double> widths) {
    for (var i = 0; i < widths.length; i++) {
      sheet.setColumnWidth(i, widths[i]);
    }
  }

  static String _fullName(Map<String, dynamic> player) {
    final name = '${_text(player['first_name'])} ${_text(player['last_name'])}'.trim();
    return name.isEmpty ? 'Unknown player' : name;
  }

  static String _text(dynamic value, [String fallback = '']) {
    if (value == null) return fallback;
    if (value is Map || value is Iterable) return fallback;
    final text = '$value'.trim();
    return text.isEmpty ? fallback : text;
  }

  static int _int(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse('$value') ?? 0;
  }

  static String _date(dynamic value) {
    final parsed = _parse(value);
    if (parsed == null) return '—';
    return '${parsed.year}-${_two(parsed.month)}-${_two(parsed.day)}';
  }

  static String _dateTime(dynamic value) {
    final parsed = _parse(value);
    if (parsed == null) return '—';
    return '${_date(value)} ${_two(parsed.hour)}:${_two(parsed.minute)}';
  }

  static DateTime? _parse(dynamic value) {
    final text = _text(value);
    if (text.isEmpty) return null;
    return DateTime.tryParse(text.replaceFirst(' ', 'T'));
  }

  static String _two(int value) => value.toString().padLeft(2, '0');

  static String _stamp() {
    final now = DateTime.now();
    return '${now.year}${_two(now.month)}${_two(now.day)}_'
        '${_two(now.hour)}${_two(now.minute)}';
  }

  static String _slug(String value) {
    final slug = value
        .toLowerCase()
        .replaceAll(RegExp(r'[^a-z0-9]+'), '_')
        .replaceAll(RegExp(r'^_+|_+$'), '');
    return slug.isEmpty ? 'sport' : slug;
  }
}

/// Tiny map helper so the export service does not depend on UI models.
class JsonLike {
  JsonLike._();

  static Map<String, dynamic> map(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return value.map((k, v) => MapEntry('$k', v));
    return const {};
  }
}
