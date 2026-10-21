@php
/**
 * Player portal (player login home): own profile, performance snapshot,
 * attendance summary and upcoming sessions with photo check-in.
 *
 * @var array $player  player row incl. team_name / team_code
 * @var array|null $team
 * @var array $upcoming upcoming own-team events
 * @var array<int,string> $attendanceStatuses event_id => status for this player
 * @var array<string,int> $attendanceSummary status => count
 * @var array|null $latestMetric
 * @var array|null $latestWelfare
 * @var array $user
 */

$full = $player['first_name'] . ' ' . $player['last_name'];

$age = null;
if (!empty($player['date_of_birth']) && strtotime($player['date_of_birth']) !== false) {
    $age = (int) floor((((int) date('Ymd')) - ((int) date('Ymd', strtotime($player['date_of_birth'])))) / 10000);
}

$fitnessBadge = ['excellent' => 'success', 'good' => 'success', 'fair' => 'warning', 'poor' => 'secondary', 'injured' => 'danger'];
$wellbeingBadge = ['great' => 'success', 'good' => 'success', 'okay' => 'secondary', 'low' => 'warning', 'struggling' => 'danger'];
$statusBadge = ['present' => 'success', 'late' => 'warning', 'absent' => 'danger', 'excused' => 'secondary'];

$cards = [
    ['label' => 'Goals', 'value' => (int) $player['goals'], 'icon' => 'bi-bullseye', 'color' => 'success'],
    ['label' => 'Assists', 'value' => (int) $player['assists'], 'icon' => 'bi-signpost-split', 'color' => 'primary'],
    ['label' => 'Appearances', 'value' => (int) $player['appearances'], 'icon' => 'bi-person-check', 'color' => 'info'],
    ['label' => 'Checks-in', 'value' => (int) ($attendanceSummary['present'] ?? 0), 'icon' => 'bi-camera', 'color' => 'warning'],
];
@endphp

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                {!! avatar_html($player['photo'] ?? null, $full, 'avatar-xl mx-auto mb-3') !!}
                <h1 class="h4 mb-1">{!! e($full) !!}</h1>
                <div class="d-flex flex-wrap gap-1 justify-content-center mb-2">
                    @if($player['jersey_number'] !== null)
                        <span class="badge text-bg-dark">#{!! e($player['jersey_number']) !!}</span>
                    @endif
                    <span class="badge text-bg-secondary">{!! e($player['position'] ?? 'Position n/a') !!}</span>
                    @if($player['rating'] !== null)
                        <span class="badge text-bg-success"><i class="bi bi-star-fill me-1"></i>{!! number_format((float) $player['rating'], 2) !!}</span>
                    @endif
                </div>
                <p class="text-muted small mb-3">
                    @if(!empty($player['team_name']))
                        <i class="bi bi-shield-fill me-1"></i>{!! e($player['team_name']) !!}
                        {!! $age !== null ? ' &middot; ' . $age . ' yrs' : '' !!}
                    @endif
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="{!! e(url('/players/' . $player['id'])) !!}" class="btn btn-sm btn-primary">
                        <i class="bi bi-graph-up-arrow me-1"></i>My performance
                    </a>
                    <a href="{!! e(url('/chat')) !!}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-chat-dots me-1"></i>Team chat
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="row g-3">
            @foreach($cards as $card)
                <div class="col-6 col-md-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="stat-icon bg-{!! e($card['color']) !!} mx-auto mb-2">
                                <i class="bi {!! e($card['icon']) !!}"></i>
                            </div>
                            <div class="fs-3 fw-bold">{!! (int) $card['value'] !!}</div>
                            <div class="text-muted small text-uppercase">{!! e($card['label']) !!}</div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-activity me-2 text-success"></i>Physical snapshot</div>
                    <div class="card-body">
                        @if($latestMetric === null)
                            <p class="text-muted small mb-0">No fitness test recorded yet.</p>
                        @else
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Latest test</span>
                                <strong>{!! e(format_date($latestMetric['test_date'])) !!}</strong>
                            </div>
                            <div class="row text-center g-2">
                                <div class="col-6">
                                    <div class="fw-bold">{!! $latestMetric['weight_kg'] !== null ? e($latestMetric['weight_kg']) . ' kg' : '-' !!}</div>
                                    <small class="text-muted text-uppercase">Weight</small>
                                </div>
                                <div class="col-6">
                                    <div class="fw-bold">{!! $latestMetric['top_speed'] !== null ? e($latestMetric['top_speed']) . ' km/h' : '-' !!}</div>
                                    <small class="text-muted text-uppercase">Top speed</small>
                                </div>
                            </div>
                            <div class="mt-2 text-center">
                                <span class="badge text-bg-{!! e($fitnessBadge[$latestMetric['fitness']] ?? 'secondary') !!}">
                                    {!! e(ucfirst($latestMetric['fitness'])) !!}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-emoji-smile me-2 text-primary"></i>Wellbeing snapshot</div>
                    <div class="card-body">
                        @if($latestWelfare === null)
                            <p class="text-muted small mb-0">No assessment recorded yet.</p>
                        @else
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Assessed</span>
                                <strong>{!! e(format_date($latestWelfare['assessment_date'])) !!}</strong>
                            </div>
                            @foreach(['confidence', 'focus', 'motivation'] as $score)
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="small text-muted" style="width: 78px">{!! ucfirst($score) !!}</span>
                                    <div class="progress flex-grow-1" style="height: 7px">
                                        <div class="progress-bar bg-success" style="width: {!! ((int) $latestWelfare[$score]) * 10 !!}%"></div>
                                    </div>
                                    <span class="small fw-semibold">{!! (int) $latestWelfare[$score] !!}</span>
                                </div>
                            @endforeach
                            <div class="mt-2 text-end">
                                <span class="badge text-bg-{!! e($wellbeingBadge[$latestWelfare['wellbeing']] ?? 'secondary') !!}">
                                    {!! e(ucfirst($latestWelfare['wellbeing'])) !!}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                <span class="fw-semibold me-2"><i class="bi bi-clipboard2-check me-1"></i>My attendance</span>
                @foreach($statusBadge as $status => $badge)
                    <span class="badge text-bg-{!! $badge !!}">{!! e(ucfirst($status)) !!}: {!! (int) ($attendanceSummary[$status] ?? 0) !!}</span>
                @endforeach
                <a href="{!! e(url('/calendar')) !!}" class="ms-auto small">View calendar <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-calendar2-week me-2 text-primary"></i>Upcoming sessions</span>
        <a href="{!! e(url('/calendar')) !!}" class="small">Full calendar</a>
    </div>
    <div class="list-group list-group-flush">
        @if($upcoming === [])
            <div class="list-group-item text-muted small">No upcoming sessions scheduled for your team.</div>
        @endif

        @foreach($upcoming as $event)
            @php
$eventId = (int) $event['id'];
            $isTraining = ($event['type'] ?? '') === 'training';
            $recorded = $attendanceStatuses[$eventId] ?? null;
@endphp
            <div class="list-group-item">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge type-{!! e($event['type']) !!} text-nowrap">
                        <i class="bi bi-clock me-1"></i>{!! e(format_date($event['starts_at'], true)) !!}
                    </span>
                    <div class="flex-grow-1">
                        <span class="fw-semibold">{!! e($event['title']) !!}</span>
                        <span class="d-block small text-muted">
                            {!! e(ucfirst($event['type'])) !!}
                            @if(!empty($event['tournament_name']))&middot; {!! e($event['tournament_name']) !!}@endif
                        </span>
                    </div>
                    @if($recorded !== null)
                        <span class="badge text-bg-{!! e($statusBadge[$recorded] ?? 'secondary') !!}">
                            <i class="bi bi-check2-circle me-1"></i>{!! e(ucfirst($recorded)) !!}
                        </span>
                    @elseif($isTraining)
                        <span class="badge text-bg-light border text-muted">Not checked in</span>
                    @endif
                </div>

                @if($isTraining && $recorded === null)
                    <form method="post" action="{!! e(url('/events/' . $eventId . '/attendance')) !!}"
                          enctype="multipart/form-data" class="row g-2 align-items-center mt-2">
                        {!! csrf_field() !!}
                        <input type="hidden" name="status" value="present">
                        <div class="col-sm-5">
                            <input type="file" name="photo" accept="image/*" capture="environment" required
                                   class="form-control form-control-sm {!! error('photo') ? 'is-invalid' : '' !!}"
                                   aria-label="Check-in photo">
                            @if(error('photo'))<div class="invalid-feedback">{!! e(error('photo')) !!}</div>@endif
                            <div class="form-text">Take or upload a photo to check in.</div>
                        </div>
                        <div class="col-sm-4">
                            <input type="text" name="notes" class="form-control form-control-sm" maxlength="255"
                                   placeholder="Optional note">
                        </div>
                        <div class="col-sm-3 d-grid">
                            <button class="btn btn-sm btn-success" type="submit">
                                <i class="bi bi-camera me-1"></i>Check in
                            </button>
                        </div>
                    </form>
                @elseif($isTraining)
                    <div class="small text-muted mt-1"><i class="bi bi-check2-all me-1 text-success"></i>Attendance recorded for this session.</div>
                @endif
            </div>
        @endforeach
    </div>
</div>
