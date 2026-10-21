@php
/**
 * @var string $content Rendered page content.
 * @var string $appName Application name.
 * @var string $pageTitle Page heading.
 */
$flashes = flash();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{!! e($pageTitle) !!} &middot; {!! e($appName) !!}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{!! e(url('/assets/css/style.css')) !!}" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <nav class="sidebar d-flex flex-column p-3">
        <a href="{!! e(url('/')) !!}" class="sidebar-brand mb-3">
            <i class="bi bi-trophy-fill me-2"></i><span>{!! e($appName) !!}</span>
        </a>

        <ul class="nav nav-pills flex-column gap-1">
            <li class="nav-item"><a class="nav-link {!! nav_active('') !!}" href="{!! e(url('/')) !!}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>

            @if(is_player())
                @php
$ownPlayerId = auth_player_id();
@endphp
                <li class="nav-item"><a class="nav-link {!! nav_active('calendar') !!}" href="{!! e(url('/calendar')) !!}"><i class="bi bi-calendar3 me-2"></i>My Calendar</a></li>
                @if($ownPlayerId !== null)
                    <li class="nav-item"><a class="nav-link {!! nav_active('players/' . $ownPlayerId) !!}" href="{!! e(url('/players/' . $ownPlayerId)) !!}"><i class="bi bi-graph-up-arrow me-2"></i>My Performance</a></li>
                @endif
                <li class="nav-item"><a class="nav-link {!! nav_active('chat') !!}" href="{!! e(url('/chat')) !!}"><i class="bi bi-chat-dots-fill me-2"></i>Chat</a></li>
            @else
                @php
$authTeamId = auth_team_id();
                $onOwnTeam = $authTeamId !== null && str_starts_with(current_path(), 'teams/' . $authTeamId);
@endphp
                @if($authTeamId !== null)
                    <li class="nav-item"><a class="nav-link {!! $onOwnTeam ? 'active' : '' !!}" href="{!! e(url('/teams/' . $authTeamId)) !!}"><i class="bi bi-shield-heart me-2"></i>My Team</a></li>
                @endif
                <li class="nav-item"><a class="nav-link {!! nav_active('calendar') !!}" href="{!! e(url('/calendar')) !!}"><i class="bi bi-calendar3 me-2"></i>Calendar</a></li>
                <li class="nav-item"><a class="nav-link {!! nav_active('chat') !!}" href="{!! e(url('/chat')) !!}"><i class="bi bi-chat-dots-fill me-2"></i>Chat</a></li>
                <li class="nav-item"><a class="nav-link {!! !$onOwnTeam && nav_active('teams') ? 'active' : '' !!}" href="{!! e(url('/teams')) !!}"><i class="bi bi-shield-fill me-2"></i>Teams</a></li>
                <li class="nav-item"><a class="nav-link {!! nav_active('players') !!}" href="{!! e(url('/players')) !!}"><i class="bi bi-people-fill me-2"></i>Players</a></li>
                <li class="nav-item"><a class="nav-link {!! nav_active('tournaments') !!}" href="{!! e(url('/tournaments')) !!}"><i class="bi bi-award-fill me-2"></i>Tournaments</a></li>
                <li class="nav-item"><a class="nav-link {!! nav_active('matches') !!}" href="{!! e(url('/matches')) !!}"><i class="bi bi-calendar-event-fill me-2"></i>Matches</a></li>
                <li class="nav-item"><a class="nav-link {!! nav_active('standings') !!}" href="{!! e(url('/standings')) !!}"><i class="bi bi-bar-chart-steps me-2"></i>Standings</a></li>
                @if(is_admin())
                    <li class="nav-item"><a class="nav-link {!! nav_active('users') !!}" href="{!! e(url('/users')) !!}"><i class="bi bi-person-gear me-2"></i>Users</a></li>
                @endif
            @endif
        </ul>

        <div class="mt-auto sidebar-footer">
            @php
$user = auth_user();
@endphp
            @if($user !== null)
                <div class="d-flex align-items-center gap-2 mb-2 px-2">
                    {!! avatar_html($user['photo'] ?? null, $user['name'], 'avatar-sm') !!}
                    <div class="text-truncate">
                        <div class="small fw-semibold text-white text-truncate">{!! e($user['name']) !!}</div>
                        <div class="small text-secondary text-truncate">
                            @if(is_admin())
                                <i class="bi bi-shield-lock me-1"></i>Administrator
                            @elseif(is_player())
                                <i class="bi bi-person-running me-1"></i>Player
                            @else
                                <i class="bi bi-person-badge me-1"></i>Team staff
                            @endif
                        </div>
                    </div>
                </div>
                <form method="post" action="{!! e(url('/logout')) !!}" class="m-0">
                    {!! csrf_field() !!}
                    <button class="btn btn-outline-light btn-sm w-100" type="submit">
                        <i class="bi bi-box-arrow-right me-1"></i>Sign out
                    </button>
                </form>
            @endif
        </div>
    </nav>

    <main class="main-content">
        <header class="topbar d-flex align-items-center justify-content-between px-4">
            <h1 class="h4 mb-0">{!! e($pageTitle) !!}</h1>
            <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{!! date('l, d F Y') !!}</span>
        </header>

        <div class="content-area">
            @foreach($flashes as $type => $message)
                <div class="alert alert-{!! $type === 'error' ? 'danger' : e($type) !!} alert-dismissible fade show" role="alert">
                    <i class="bi {!! $type === 'error' ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' !!} me-1"></i>
                    {!! e($message) !!}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endforeach

            {!! $content !!}
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
