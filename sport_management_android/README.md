# Sport Manager — Flutter client

A Flutter client for the Sport Management Laravel API. It provides login and
screens for the role-aware dashboard, matches, standings, teams, players,
tournaments, calendar/attendance, chat and profile.

## Quick start

```bash
flutter pub get
flutter run
```

Sign in with a backend account (demo: `admin@sport.test` / `password`).

## Backend address

The base URL is resolved in `lib/config.dart`:

| Where the app runs | Base URL |
| --- | --- |
| Android emulator | `http://10.0.2.2:8000` |
| Physical device (same Wi-Fi) | `http://<your-LAN-IP>:8000` |
| Apache (XAMPP) | `http://<host>/sport_management/public` |

Start the backend so the emulator can reach it:

```bash
# from the Laravel project root
php -S 0.0.0.0:8000 -t public
```

Override the URL without editing code:

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000
```

## Layout

```
lib/
├── config.dart              # API base URL + timeouts
├── main.dart                # app root; swaps splash / login / shell
├── models/                  # AppUser + JSON helpers
├── services/                # ApiClient, TokenStorage, ApiException
├── state/session_store.dart # auth state (token, current user)
├── widgets/                 # ErrorPopup (3s), common views
└── screens/                 # login, dashboard, matches, standings, teams,
                             # players, tournaments, events, chat, profile
```

## Behaviour notes

- **Auth**: Sanctum bearer tokens. The token is stored with
  `shared_preferences`; `bootstrap()` validates it via `GET /api/me` on launch.
- **Login errors**: a password mismatch shows a prominent top banner that
  dismisses itself after **3 seconds** (see `lib/widgets/error_popup.dart`).
  An inline message stays under the password field afterwards.
- **Live chat**: the room screen polls `GET /api/chat/{id}/messages?after=` every
  few seconds, so no websocket backend is needed.

## Tests

```bash
flutter test     # unit tests for config, models and the API client
flutter analyze  # static analysis
```

## Android toolchain

Gradle 8.14 (from the Flutter Android template) runs on JDK 17–24. If
`flutter build apk` fails with a bare version number, the JDK is too new — point
Flutter at a JDK 21:

```bash
flutter config --jdk-dir="C:/path/to/jdk-21"
```
