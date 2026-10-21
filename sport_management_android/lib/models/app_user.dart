/// A signed-in user as returned by `POST /api/login` and `GET /api/me`.
class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    this.teamId,
    this.playerId,
    this.teamName,
    this.teamCode,
    this.phone,
    this.photo,
  });

  final int id;
  final String name;
  final String email;
  final String role;
  final int? teamId;
  final int? playerId;
  final String? teamName;
  final String? teamCode;
  final String? phone;
  final String? photo;

  bool get isAdmin => role == 'admin';
  bool get isManager => role == 'manager';
  bool get isPlayer => role == 'player';

  /// Human label for the role badge.
  String get roleLabel {
    switch (role) {
      case 'admin':
        return 'Administrator';
      case 'manager':
        return 'Team Manager';
      case 'player':
        return 'Player';
      default:
        return role.isEmpty ? 'User' : role;
    }
  }

  /// Initials for the avatar placeholder.
  String get initials {
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) return '?';
    if (parts.length == 1) return parts.first[0].toUpperCase();
    return (parts.first[0] + parts.last[0]).toUpperCase();
  }

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: _asInt(json['id']),
      name: (json['name'] ?? '').toString(),
      email: (json['email'] ?? '').toString(),
      role: (json['role'] ?? '').toString(),
      teamId: _asIntOrNull(json['team_id']),
      playerId: _asIntOrNull(json['player_id']),
      teamName: json['team_name']?.toString(),
      teamCode: json['team_code']?.toString(),
      phone: json['phone']?.toString(),
      photo: json['photo']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'role': role,
        'team_id': teamId,
        'player_id': playerId,
        'team_name': teamName,
        'team_code': teamCode,
        'phone': phone,
        'photo': photo,
      };
}

int _asInt(dynamic value) => _asIntOrNull(value) ?? 0;

int? _asIntOrNull(dynamic value) {
  if (value == null) return null;
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value.toString());
}
