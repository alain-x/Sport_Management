# Sport Management System

A complete sport management web application built with **PHP 8.2** and **Composer**
(PSR-4 autoloading), running on **MySQL/MariaDB** with a hand-rolled MVC layer —
no heavy framework required. It ships with a Bootstrap 5 UI and demo seed data.

## Features

| Module | What you can do |
|---|---|
| **Dashboard** | Live stats, recent results, upcoming fixtures, top scorers, ongoing tournaments |
| **Teams** | Create / edit / delete, search & paginate, squad overview per team |
| **Players** | Full CRUD, search by name/nationality, filter by team, positions, goals & assists |
| **Tournaments** | Create / edit / delete with status (`upcoming` / `ongoing` / `completed`), modal-based creation |
| **Matches** | Schedule fixtures, enter scores, filter by tournament & status, mark `scheduled` / `live` / `finished` |
| **Standings** | League table computed from finished matches (P W D L GF GA GD Pts), sorted by points → goal difference → goals |
| **Auth** | Session login, bcrypt password hashing, CSRF protection on every POST, protected routes |
| **404/419** | Friendly error pages for unknown routes and expired CSRF tokens |

## Requirements

- PHP >= 8.1 (with PDO MySQL) — XAMPP's PHP 8.2 works out of the box
- Composer 2.x
- MySQL / MariaDB (XAMPP's MariaDB works fine)

## Installation

```bash
# 1. Install dependencies (PSR-4 autoloader)
composer install

# 2. Configure the database connection (defaults match XAMPP: root, no password)
copy .env.example .env        # Windows: copy / edit manually

# 3. Create the database, tables and demo data
mysql -u root < database/schema.sql

# 4. Run it
composer serve                # php -S localhost:8000 -t public
```

**With XAMPP/Apache**, drop the folder in `htdocs` and open either:

- <http://localhost/sport_management/> (project root `.htaccess` forwards into `public/`), or
- <http://localhost/sport_management/public/>

Application files (`app/`, `config/`, `.env`, `database/`, `storage/`) are **not**
reachable from the web root — the root `.htaccess` forwards those paths into the
front controller, and `.env` is explicitly denied.

### Demo login

```
email:    admin@sport.test
password: password
```

## Project structure

```
sport_management/
├── app/
│   ├── Controllers/     # HTTP controllers (auth, dashboard, teams, ...)
│   ├── Core/            # Router, Request, View, Database (PDO), Auth, Csrf, Validator
│   ├── Models/          # Team, Player, Tournament, MatchModel, User
│   └── helpers.php      # Global helpers (e(), url(), flash(), old(), paginate(), ...)
├── config/config.php    # .env loader + configuration
├── database/schema.sql  # Schema + demo seed data (8 teams, 24 players, 18 matches)
├── public/              # Web root: index.php, .htaccess, assets/
├── routes/web.php       # All routes
├── storage/views/       # PHP templates (layout, pages, auth, errors)
└── composer.json        # PSR-4 autoload ("App\" => "app/")
```

## Routes

All routes except `GET|POST /login` require authentication. Mutating requests
(`POST`) require a `_token` CSRF field.

```
GET  /                      Dashboard
GET|POST /login, POST /logout
GET  /teams                 /teams/create, POST /teams, GET /teams/{id},
GET  /teams/{id}/edit, POST /teams/{id}, POST /teams/{id}/delete
GET  /players ...           same CRUD pattern
GET  /tournaments ...       same CRUD pattern (create/edit via modal)
GET  /matches ...           same CRUD pattern
GET  /standings             -> redirects to /standings/{tournamentId}
```

## How standings work

`MatchModel::standings($tournamentId)` collects every match with status
`finished` for the tournament and accumulates results per team:
3 points for a win, 1 for a draw. Teams are sorted by points, then goal
difference, then goals scored. Enter a score with status *Finished* on the
matches screen and the table updates immediately.

## Testing

Two smoke-test suites (PowerShell + `Invoke-WebRequest`) cover auth, CSRF,
CRUD, validation, search, pagination and standings math — **64 assertions,
all passing** against both the PHP built-in server and Apache/XAMPP:

```powershell
# built-in server (default http://127.0.0.1:8123)
powershell -File smoke_test.ps1
powershell -File smoke_test2.ps1

# against Apache
$env:TEST_BASE='http://localhost/sport_management/public'
powershell -File smoke_test.ps1
```

## Configuration (`.env`)

```ini
APP_NAME="Sport Manager"
APP_DEBUG=true            # show errors in the browser (set false in production)

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=sport_management
DB_USER=root
DB_PASS=
```
