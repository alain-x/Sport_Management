/// An error raised by the API client.
///
/// The Laravel API answers failures with
/// `{ "message": "...", "errors": { field: ["..."] } }`, so we keep the
/// field-level errors around for form validation and expose [message] for
/// generic display (snackbars, popups).
class ApiException implements Exception {
  ApiException(
    this.message, {
    this.statusCode,
    this.errors = const {},
  });

  /// Human-readable summary, safe to show to the user.
  final String message;

  /// HTTP status code, if the failure came back from the server.
  final int? statusCode;

  /// Field => list of messages (from a 422 validation response).
  final Map<String, List<String>> errors;

  /// True when the token is missing/expired (caller should sign out).
  bool get isUnauthorized => statusCode == 401;

  /// True when the server rejected the submitted values.
  bool get isValidationError => statusCode == 422;

  /// The first validation message for [field], or null.
  String? firstErrorFor(String field) {
    final list = errors[field];
    if (list == null || list.isEmpty) return null;
    return list.first;
  }

  /// A single line combining all validation messages, for compact display.
  String get flatErrors {
    if (errors.isEmpty) return message;
    return errors.values.expand((e) => e).join('\n');
  }

  @override
  String toString() => 'ApiException($statusCode): $message';
}
