@php
/**
 * Tournament detail: info + fixture list.
 * @var array $tournament includes 'matches'
 */
$badge = ['scheduled' => 'text-bg-info', 'live' => 'text-bg-danger', 'finished' => 'text-bg-secondary'];
@endphp
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <a href="{!! e(url('/tournaments')) !!}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to tournaments
    </a>
    <div class="d-flex gap-2">
        <a href="{!! e(url('/standings/' . $tournament['id'])) !!}" class="btn btn-outline-warning">
            <i class="bi bi-bar-chart-steps me-1"></i>Standings
        </a>
        <a href="{!! e(url('/tournaments/' . $tournament['id'] . '/edit')) !!}" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <span class="badge text-bg-{!! $tournament['status'] === 'ongoing' ? 'success' : ($tournament['status'] === 'completed' ? 'secondary' : 'info') !!} mb-2">
                    {!! e(ucfirst($tournament['status'])) !!}
                </span>
                <h1 class="h4 mb-1">{!! e($tournament['name']) !!}</h1>
                <p class="text-muted mb-3">{!! e($tournament['sport']) !!}</p>
                <p class="small mb-1">
                    <i class="bi bi-calendar-event me-1"></i>
                    {!! e(format_date($tournament['start_date'])) !!} &ndash; {!! e(format_date($tournament['end_date'])) !!}
                </p>
                <p class="small text-muted mb-0">{!! nl2br(e($tournament['description'] ?? 'No description.')) !!}</p>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Total matches</span><strong>{!! count($tournament['matches']) !!}</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Finished</span>
                    <strong>{!! count(array_filter($tournament['matches'], fn ($m) => $m['status'] === 'finished')) !!}</strong>
                </li>
            </ul>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-list-check me-2"></i>Fixtures</span>
                <a href="{!! e(url('/matches/create')) !!}" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-plus-lg me-1"></i>Schedule match
                </a>
            </div>
            <div class="list-group list-group-flush">
                @if($tournament['matches'] === [])
                    <div class="list-group-item text-center text-muted py-4">No matches in this tournament yet.</div>
                @endif
                @foreach($tournament['matches'] as $m)
                    <div class="list-group-item d-flex flex-wrap align-items-center gap-2 py-3">
                        <div class="flex-grow-1" style="min-width: 220px">
                            <div class="fw-semibold">
                                {!! e($m['home_name']) !!}
                                @if($m['status'] === 'finished')
                                    <span class="mx-2 score-pill">{!! (int) $m['home_score'] !!} : {!! (int) $m['away_score'] !!}</span>
                                @else
                                    <span class="text-muted mx-2">vs</span>
                                @endif
                                {!! e($m['away_name']) !!}
                            </div>
                            <div class="small text-muted">
                                <i class="bi bi-clock me-1"></i>{!! e(format_date($m['match_date'], true)) !!}
                                @if($m['venue']) &middot; <i class="bi bi-geo-alt me-1"></i>{!! e($m['venue']) !!}@endif
                            </div>
                        </div>
                        <span class="badge {!! $badge[$m['status']] ?? 'text-bg-secondary' !!}">
                            {!! $m['status'] === 'live' ? '<i class="bi bi-broadcast me-1"></i>' : '' !!}{!! ucfirst($m['status']) !!}
                        </span>
                        <a href="{!! e(url('/matches/' . $m['id'] . '/edit')) !!}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
