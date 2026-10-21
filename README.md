# Sport Management System

A complete, role-based sport management web application built with **Laravel 12**
(PHP 8.2) on **MySQL/MariaDB**. It ships with a Bootstrap 5 UI, the original demo
seed data, and a full **Pest** test suite.

The project began life as a hand-rolled PHP MVC app (v2/v3) and was rewritten
in place on Laravel while keeping the exact same data model, behaviour, routes,
markup hooks and authorization rules.

**v2** covers multi-team, role-based staff management: an administrator registers
teams and users, and each team's staff manage their **own** squad — players,
season statistics, physical tests, mental/wellbeing, fixtures, a shared calendar
of events, and image uploads (team logos, player photos, user avatars).

**v3** adds a **player experience**: players get their own login, check in to
training sessions by uploading a photo (served straight from a phone camera),
see their own performance and personal calendar, and chat with their team or in
admin-created groups. Player accounts are tightly scoped — they can never edit
team/player data or read a team-mate's private physical/wellbeing data.

## Features

| Module | What you can do |
|---|---|
| **Dashboard** | Admins get league-wide stats, recent results, upcoming fixtures, top scorers and events; staff get a team dashboard with squad fitness bars, medical/wellbeing alerts, fixtures, events and top scorers; players get a personal portal |
| **Roles & scoping** | `admin`, `manager` and `player` roles; managers are locked to their own team's players, staff, team profile, fixtures and events; players only see their own data and same-team chat |
| **Teams** | Create / edit / delete (admin), logo upload, description, coach, city, founded year, staff count, squad overview |
| **Team staff** | Per-team staff list, add staff to your team, edit profile — role/team changes are admin-only |
| **Players** | Full CRUD scoped to your team, photo upload, position, foot, nationality, jersey number, optional 1:1 player login |
| **Player portal** | Personal dashboard: next sessions, own performance snapshot, attendance history, own calendar and team/group chat |
| **Training attendance** | Players check in to training by submitting a photo; staff see the squad roster and can adjust each player's status (present / late / absent / injured) |
| **Chat** | Automatic team room per team plus admin-created group chats; polling message feed, member management |
| **Season statistics** | Per-player goals, assists, appearances, minutes, cards and rating, plus a league top-scorers view |
| **Physical tracking** | Dated physical tests (height, weight, top speed, stamina, strength, agility, body fat, fitness status) with history |
| **Mental / wellbeing** | Dated wellbeing assessments (confidence, focus, motivation, stress, overall wellbeing) with history and trend |
| **Calendar & events** | Month grid overlaid with fixtures; create training / meeting / travel / deadline / media / other events, team-scoped or league-wide |
| **Tournaments** | Create / edit / delete with status (`upcoming` / `ongoing` / `completed`), modal-based creation (admin-only) |
| **Matches** | Schedule fixtures, enter scores, filter by tournament & status, mark `scheduled` / `live` / `finished`; staff manage only fixtures involving their team |
| **Standings** | League table computed from finished matches (P W D L GF GA GD Pts), sorted by points → goal difference → goals |
| **Image uploads** | Team logos, player photos and user avatars — validated (3 MB, JPEG/PNG/WebP/GIF), auto-downscaled when GD is present, stored under `public/uploads/` |
| **Auth & security** | Session login (Breeze Blade stack), bcrypt hashing, CSRF protection on every POST, role/team authorization, path-traversal-safe uploads |
| **403/404/419** | Friendly error pages for forbidden, unknown and expired-CSRF requests |

## Roles & permissions

| Action | Admin | Manager (team staff) | Player |
|---|---|---|---|
| View dashboard | league-wide | own team | own portal |
| View calendar, teams, standings | ✔ (league-wide) | ✔ (own team + league-wide) | own calendar + league-wide events |
| Create / delete teams | ✔ | ✘ | ✘ |
| Edit team profile & logo | any team | own team only | ✘ |
| Manage team staff | any team | own team only (cannot change own role) | ✘ |
| Add / edit / delete players | any team | own team only | ✘ |
| View player profile pages | any player | any player (own team shows private tabs) | own record + own-team profiles (other teams → 404) |
| Record physical tests & wellbeing | any player | own team's players only | ✘ |
| View physical / mental tabs | ✔ | own team's players only | own tabs only |
| View / edit season statistics | ✔ | own team only | own (read-only) |
| Schedule / edit / delete matches | all | only fixtures involving own team | ✘ |
| Create tournaments & league-wide events | ✔ | ✘ | ✘ |
| Create team events | ✔ | own team only (team is locked server-side) | ✘ |
| Check in to training (photo) | — | — | own team's sessions only |
| Manage attendance roster | any session | own team's sessions only | ✘ |
| Chat: team room | all | own team | own team |
| Chat: create / edit groups | ✔ | ✘ | ✘ |
| Manage users & player logins | ✔ | ✘ | ✘ |

Authorization lives in the base controller (`requireAdmin()`, `requireStaff()`,
`requireTeam()`, `scopeTeamId()`); managers are scoped through `users.team_id`
and players through `users.player_id` + `users.team_id`. The global helpers
`is_admin()`, `is_staff()`, `is_player()`, `auth_team_id()` and `auth_player_id()`
mirror the original app.

## Requirements

- PHP >= 8.2 (with PDO MySQL) — XAMPP's PHP 8.2 works out of the box
- Composer 2.x
- MySQL / MariaDB (XAMPP's MariaDB works fine)
- Optional: the GD extension for automatic image downscaling (uploads still work without it)
- No Node/npm or asset build step — Bootstrap 5 and Bootstrap Icons load from a CDN

## Installation

```bash
# 1. Install dependencies
composer install

# 2. Configure the environment (defaults match XAMPP: root, no password)
copy .env.example .env
php artisan key:generate

# 3. Create the database, tables and demo data
mysql -u root < database/schema.sql

# 4. Run it
php artisan serve            # http://127.0.0.1:8000
```

`database/schema.sql` is the complete v3 schema + demo seed (it drops and
recreates the tables). The Laravel migrations match this schema exactly, so if
you already have an existing `sport_management` database with live data you can
simply point `.env` at it — **nothing is re-seeded**. The optional
non-destructive upgrade for older databases is `database/migrate_v3.sql`.

**With XAMPP/Apache**, drop the folder in `htdocs` and open either:

- <http://localhost/sport_management/> (project root `.htaccess` forwards into `public/`), or
- <http://localhost/sport_management/public/>

Application files (`app/`, `config/`, `.env`, `database/`, `storage/`) are **not**
reachable from the web root — the root `.htaccess` forwards those paths into the
front controller, and `.env` is explicitly denied. `public/uploads/` has an
`.htaccess` that blocks script execution so uploaded images can never run code.

### Demo accounts

All use the password `password`:

| Email | Role | Team |
|---|---|---|
| `admin@sport.test` | admin | — (manages every team, user & tournament) |
| `manager@sport.test` | manager | Thunder FC |
| `irl.staff@sport.test` | manager | Iron Lions |
| `fitness@sport.test` | manager | Thunder FC |
| `james@sport.test` | player | Thunder FC (linked to James Carter) |

Player logins are created from the player form — tick **Create a login** and set
an email + temporary password. The account is linked 1:1 to the player record
(`users.player_id`) and its team is kept in sync when the player transfers.

## Project structure

```
sport_management/
├── app/
│   ├── Http/Controllers/   # auth (Breeze), dashboard, teams, players, matches,
│   │                       # tournaments, users, calendar/events, standings,
│   │                       # attendance, chat
│   ├── Models/             # Team, Player, PlayerMetric, PlayerWelfare, Tournament,
│   │                       # MatchModel, EventModel, User, Attendance, ChatRoom,
│   │                       # ChatMessage (Eloquent, array-returning query helpers)
│   ├── Session/            # Store + SessionManager (legacy 64-char CSRF token)
│   ├── Support/            # Upload, UploadException, Validator
│   └── helpers.php         # e(), url(), flash(), old(), paginate(), avatar helpers
├── bootstrap/app.php       # routing + exception config (403/404/419)
├── database/
│   ├── migrations/         # 9 schema-adopting migrations
│   ├── schema.sql          # Complete v3 schema + demo seed
│   └── migrate_v3.sql      # Non-destructive v2 → v3 upgrade
├── public/                 # Web root: index.php, .htaccess, assets/, uploads/
├── resources/views/        # Blade templates (layout + pages)
├── routes/web.php          # All routes
├── routes/auth.php         # Login / logout (Breeze)
└── tests/Feature/Smoke/    # Pest port of the original 128-assertion suite
```

## Routes

All routes except `GET|POST /login` require authentication. Mutating requests
(`POST`) require a `_token` CSRF field.

```
GET  /                      Dashboard (admin overview | team dashboard | player portal)
GET|POST /login, POST /logout

# Teams
GET  /teams, /teams/create, GET /teams/{id}, /teams/{id}/edit
POST /teams, /teams/{id}, /teams/{id}/delete

# Team staff
GET  /teams/{id}/staff, GET /staff/{id}/edit
POST /teams/{id}/staff, /staff/{id}, /staff/{id}/delete

# Users (admin)
GET  /users, /users/create, /users/{id}/edit
POST /users, /users/{id}, /users/{id}/delete

# Players (profile + statistics + physical/mental)
GET  /players, /players/create, /players/{id}, /players/{id}/edit
POST /players, /players/{id}, /players/{id}/delete
POST /players/{id}/stats, /players/{id}/photo
POST /players/{id}/metrics, /players/{id}/welfare
POST /metrics/{id}/delete, /welfare/{id}/delete

# Calendar & events
GET  /calendar, /events/create, /events/{id}/edit
POST /events, /events/{id}, /events/{id}/delete

# Tournaments
GET  /tournaments, /tournaments/{id}, /tournaments/{id}/edit
POST /tournaments, /tournaments/{id}, /tournaments/{id}/delete

# Matches
GET  /matches, /matches/create, /matches/{id}/edit
POST /matches, /matches/{id}, /matches/{id}/delete

# Standings
GET  /standings  -> redirects to /standings/{tournamentId}

# Training attendance
GET  /events/{id}/attendance          # staff roster (admin | team staff)
POST /events/{id}/attendance          # player check-in (photo)
POST /events/{id}/attendance/{playerId}  # staff sets a player's status

# Chat
GET  /chat, /chat/{id}
POST /chat/{id}                       # post a message
GET  /chat/{id}/messages              # JSON feed for polling (?after={id})
GET  /chat/create                     # admin: new group
POST /chat                            # admin: create group
GET  /chat/{id}/edit                  # admin: edit group
POST /chat/{id}/update                # admin: rename / change members
```

## Player tracking

Each player detail page has four tabs — **Overview**, **Statistics**, **Physical**
and **Mental**. The Physical and Mental tabs are only visible to the player's own
team staff and admins (other roles see the public Overview/Statistics).

- **Physical** records dated tests: height, weight, top speed, stamina, strength,
  agility, body fat and a fitness status (`excellent` / `good` / `fair` / `poor` /
  `injured`). The latest reading shows at the top with a full history below.
- **Mental** records dated wellbeing assessments on a 1–10 scale: confidence,
  focus, motivation, stress, plus an overall wellbeing status.

After saving, the app redirects back to the same tab (`/players/{id#physical}`,
`#mental`, `#statistics`) so staff stay in context. Team dashboards surface injured
or low-wellbeing players as alerts.

## Player portal (v3)

A `player` login lands on `GET /` — its own portal rather than the staff
dashboard. It shows:

- **Next sessions** for the player's team, with a one-tap check-in.
- **Performance snapshot**: the player's season statistics and latest physical /
  wellbeing readings (their own tabs are readable; every other player's Physical
  and Mental tabs stay hidden).
- **Attendance history**: status per session with the submitted photo.
- Links to *My Calendar* (the shared calendar filtered to their scope) and *Chat*.

Players get a lean navigation bar (Dashboard, My Calendar, My Performance, Chat)
and are blocked with a 403 from every staff/admin route — `/users`, the player
list and editor, and event / match / tournament / staff management.

Profile pages are scoped to the player's own squad: a player may open their own
record and the public profiles of their own team-mates, but a player on another
team is treated as non-existent (`404`). Team-mates' **Physical** and **Mental**
tabs stay hidden — only the player themselves and their team's staff see those.

## Training attendance

Staff (admin or the team's own staff) create a **training** event as usual, then
open its attendance page from the event row in the calendar's upcoming list.

- The roster lists every player on the team with their current status.
- A player checks in at `POST /events/{id}/attendance` with a photo taken from
  the device camera (`<input type="file" accept="image/*" capture="environment">`).
  This records the player as **present** and stores the photo under
  `public/uploads/attendance/`. Re-checking in without a new photo keeps the
  previous one.
- Staff can set any player's status (`present` / `late` / `absent` / `injured`)
  from the same page. Check-in is player-only: staff POSTs are rejected with 403.

## Chat

Every team gets an automatic **team room** (`ChatRoom::ensureTeamRoom`). Admins
can additionally create **group rooms** and pick their members. Access rules:

- *team room* — visible to members of that team and to admins;
- *group room* — visible to its members and to admins.

The room view polls `GET /chat/{id}/messages?after={id}` for new messages.
Players can post but never create or edit rooms (403).

## Calendar & events

`GET /calendar` renders a month grid. Fixtures for the visible month are overlaid
alongside **events**, colour-coded by type (training, meeting, travel, deadline,
media, other). Events with a team are visible only to that team (and admins);
**league-wide** events (no team) are visible to everyone. Managers can only create
and edit events for their own team — the team is locked server-side. Prev/next
month navigation and an upcoming-events list are included.

## Image uploads

Team logos, player photos, user avatars and attendance check-in photos go through
`App\Support\Upload`:

- Rejects files over 3 MB or non-image MIME types (JPEG, PNG, WebP, GIF only).
- Downscales to a max dimension of 1400 px via GD when available, otherwise stores
  the original.
- Randomised filenames under `public/uploads/{teams,players,avatars,attendance}/`;
  the old file is deleted on replacement. `public/uploads/.htaccess` blocks script
  execution.

## Player logins

A login account is optional and 1:1 with a player record. Tick **Create a login**
on the player form (`/players/create` or `/players/{id}/edit`), enter an email and
a temporary password (min 6 chars), and the app creates a `player` user linked via
`users.player_id`. On the Users screen these accounts show role/team/linked player
as read-only; the admin can still change the name, email, phone or password. When
a player moves teams the login's `users.team_id` is updated so scoping stays
correct, and deleting the player cascades to the login.

## How standings work

`MatchModel::standings($tournamentId)` collects every match with status
`finished` for the tournament and accumulates results per team:
3 points for a win, 1 for a draw. Teams are sorted by points, then goal
difference, then goals scored. Enter a score with status *Finished* on the
matches screen and the table updates immediately.

## Testing

The original four smoke suites (PowerShell + `curl.exe`, **128 assertions**) have
been ported to **Pest feature tests** and are backed by a dedicated test database
so the live data is never touched.

```bash
# 1. Import the demo schema into a throwaway test database
#    (schema.sql hardcodes "sport_management", so swap the name on the way in)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS sport_management_test"
#   PowerShell:
#   (Get-Content database/schema.sql -Raw) -replace 'sport_management','sport_management_test' |
#     mysql -u root
#   bash/macOS:
#   sed 's/sport_management/sport_management_test/g' database/schema.sql | mysql -u root

# 2. Run the suite
php artisan test
```

`tests/Feature/Smoke/` mirrors the PowerShell suites:

| File | Ports | Checks |
|---|---|---|
| `SmokeTest1Test.php` | `smoke_test.ps1` | 21 — auth, CSRF token shape, main pages, standings, team CRUD, 404 |
| `SmokeTest2Test.php` | `smoke_test2.ps1` | 11 — search filters, player CRUD, standings after a finished match |
| `SmokeTest3Test.php` | `smoke_test3.ps1` | 36 — manager scoping, metrics/welfare, events, matches, uploads, CSRF |
| `SmokeV3Test.php` | `smoke_v3.ps1` | 60 — player portal & scoping, check-in + photo, chat groups, admin |

The tests run against the **seeded** `sport_management_test` schema inside a
transaction (`DatabaseTransactions`), so they need no factories and always start
from the demo data. `.env.testing` and `phpunit.xml` point at
`sport_management_test`; `DatabaseGuardTest` asserts the suite is never wired to
the live database.

The raw PowerShell suites can still be run against a live Laravel server:

```powershell
# built-in server
php artisan serve
$env:TEST_BASE='http://127.0.0.1:8000'
powershell -File smoke_test.ps1
powershell -File smoke_test2.ps1
powershell -File smoke_test3.ps1
powershell -File smoke_v3.ps1

# against Apache
$env:TEST_BASE='http://localhost/sport_management'
```

> **Note on 419:** the CSRF-token format is preserved from the original app
> (a 64-character hex value), and a missing/expired token answers with a bare
> `419` status from the exception handler. On Apache the status line is emitted
> directly so it survives the webserver.

## Configuration (`.env`)

```ini
APP_NAME="Sport Manager"
APP_DEBUG=true            # show errors in the browser (set false in production)

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sport_management
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```
