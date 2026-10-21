@php
/**
 * Staff attendance roster for one training session.
 *
 * @var array $event event row incl. team_name
 * @var array|null $team team row incl. 'players'
 * @var array<int,array> $existing player_id => attendance row
 * @var array<string,int> $tally
 * @var string[] $statuses
 */

$statusBadge = ['present' => 'success', 'late' => 'warning', 'absent' => 'danger', 'excused' => 'secondary'];
$players = $team['players'] ?? [];
$recordedCount = count($existing);
@endphp

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <a href="{!! e(url('/calendar')) !!}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Calendar
    </a>
    <div class="text-end small text-muted">
        {!! e($event['team_name'] ?? '') !!}
        @if(!empty($event['tournament_name']))&middot; {!! e($event['tournament_name']) !!}@endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h1 class="h5 mb-1">{!! e($event['title']) !!}</h1>
                <p class="text-muted mb-0">
                    <i class="bi bi-clock me-1"></i>{!! e(format_date($event['starts_at'], true)) !!}
                    &middot; <span class="badge type-{!! e($event['type']) !!}">{!! e(ucfirst($event['type'])) !!}</span>
                    @if(!empty($event['description']))
                        <span class="d-block mt-2 small">{!! e($event['description']) !!}</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="small text-uppercase text-muted mb-2">Checked in</div>
                <div class="d-flex align-items-baseline gap-1 mb-2">
                    <span class="fs-2 fw-bold">{!! $recordedCount !!}</span>
                    <span class="text-muted">/ {!! count($players) !!} players</span>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($statuses as $status)
                        <span class="badge text-bg-{!! e($statusBadge[$status] ?? 'secondary') !!}">
                            {!! e(ucfirst($status)) !!}: {!! (int) ($tally[$status] ?? 0) !!}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-people-fill me-2"></i>Squad attendance
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="width: 56px">#</th>
                <th>Player</th>
                <th>Position</th>
                <th>Status</th>
                <th>Check-in photo</th>
                <th>Recorded</th>
                <th class="text-end" style="width: 220px">Set status</th>
            </tr>
            </thead>
            <tbody>
            @if($players === [])
                <tr><td colspan="7" class="text-center text-muted py-4">No players in this squad.</td></tr>
            @endif

            @foreach($players as $player)
                @php
$row = $existing[(int) $player['id']] ?? null;
                $status = $row['status'] ?? null;
                $fullName = $player['first_name'] . ' ' . $player['last_name'];
@endphp
                <tr>
                    <td>
                        <span class="badge text-bg-light border">{!! $player['jersey_number'] !== null ? e($player['jersey_number']) : '-' !!}</span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            {!! avatar_html($player['photo'] ?? null, $fullName, 'avatar-sm') !!}
                            <a href="{!! e(url('/players/' . $player['id'])) !!}" class="fw-semibold text-decoration-none">{!! e($fullName) !!}</a>
                        </div>
                    </td>
                    <td class="small text-muted">{!! e($player['position'] ?? '-') !!}</td>
                    <td>
                        @if($status !== null)
                            <span class="badge text-bg-{!! e($statusBadge[$status] ?? 'secondary') !!}">{!! e(ucfirst($status)) !!}</span>
                        @else
                            <span class="text-muted small">Not recorded</span>
                        @endif
                    </td>
                    <td>
                        @if($row !== null && !empty($row['photo']))
                            <a href="{!! e(upload_url($row['photo'])) !!}" target="_blank" rel="noopener">
                                <img src="{!! e(upload_url($row['photo'])) !!}" alt="Check-in photo"
                                     class="rounded border" style="height: 42px; width: 42px; object-fit: cover;">
                            </a>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </td>
                    <td class="small text-muted text-nowrap">
                        {!! $row !== null ? e(format_date($row['created_at'], true)) : '-' !!}
                    </td>
                    <td class="text-end">
                        <form method="post" action="{!! e(url('/events/' . (int) $event['id'] . '/attendance/' . (int) $player['id'])) !!}"
                              class="d-flex gap-1 justify-content-end">
                            {!! csrf_field() !!}
                            <select name="status" class="form-select form-select-sm" style="max-width: 130px">
                                @foreach($statuses as $option)
                                    <option value="{!! e($option) !!}" {!! $status === $option ? 'selected' : '' !!}>
                                        {!! e(ucfirst($option)) !!}
                                    </option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-primary" type="submit" title="Save">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
