@php
/**
 * @var array $matches
 * @var array $filters
 * @var array $tournaments
 * @var array $pagination
 */
$badge = ['scheduled' => 'text-bg-info', 'live' => 'text-bg-danger', 'finished' => 'text-bg-secondary'];
@endphp
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="get" action="{!! e(url('/matches')) !!}" class="d-flex flex-wrap gap-2">
        <select name="tournament_id" class="form-select" style="width: auto">
            <option value="">All tournaments</option>
            @foreach($tournaments as $tournament)
                <option value="{!! (int) $tournament['id'] !!}"
                    {!! ($filters['tournament_id'] ?? null) === (int) $tournament['id'] ? 'selected' : '' !!}>
                    {!! e($tournament['name']) !!}
                </option>
            @endforeach
        </select>
        <select name="status" class="form-select" style="width: auto">
            <option value="">All statuses</option>
            @foreach(['scheduled', 'live', 'finished'] as $status)
                <option value="{!! $status !!}" {!! ($filters['status'] ?? null) === $status ? 'selected' : '' !!}>
                    {!! ucfirst($status) !!}
                </option>
            @endforeach
        </select>
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
        @if(($filters['tournament_id'] ?? null) !== null || ($filters['status'] ?? null) !== null)
            <a href="{!! e(url('/matches')) !!}" class="btn btn-outline-danger">Clear</a>
        @endif
    </form>
    <a href="{!! e(url('/matches/create')) !!}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Schedule match
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Date</th>
                <th>Fixture</th>
                <th class="text-center">Score</th>
                <th>Tournament</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @if($matches === [])
                <tr><td colspan="6" class="text-center text-muted py-5">No matches match your filters.</td></tr>
            @endif
            @foreach($matches as $m)
                <tr>
                    <td class="text-nowrap small">{!! e(format_date($m['match_date'], true)) !!}</td>
                    <td class="fw-semibold text-nowrap">
                        {!! e($m['home_name']) !!}
                        <span class="text-muted fw-normal mx-1">vs</span>
                        {!! e($m['away_name']) !!}
                        @if($m['venue'])
                            <span class="d-block small text-muted fw-normal">
                                <i class="bi bi-geo-alt"></i> {!! e($m['venue']) !!}
                            </span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($m['status'] === 'finished')
                            <span class="score-pill">{!! (int) $m['home_score'] !!} : {!! (int) $m['away_score'] !!}</span>
                        @else
                            <span class="text-muted">&ndash; : &ndash;</span>
                        @endif
                    </td>
                    <td class="small">{!! e($m['tournament_name'] ?? 'Friendly') !!}</td>
                    <td>
                        <span class="badge {!! $badge[$m['status']] ?? 'text-bg-secondary' !!}">
                            {!! $m['status'] === 'live' ? '<i class="bi bi-broadcast me-1"></i>' : '' !!}{!! ucfirst($m['status']) !!}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{!! e(url('/matches/' . $m['id'] . '/edit')) !!}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="post" action="{!! e(url('/matches/' . $m['id'] . '/delete')) !!}" class="d-inline"
                              onsubmit="return confirm('Delete this match?');">
                            {!! csrf_field() !!}
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="small text-muted">
            Showing {!! $matches === [] ? 0 : ($pagination['page'] - 1) * $pagination['perPage'] + 1 !!}&ndash;
            {!! min($pagination['total'], $pagination['page'] * $pagination['perPage']) !!}
            of {!! $pagination['total'] !!} matches
        </span>
        {!! pagination_links($pagination) !!}
    </div>
</div>
