@php
/**
 * League table for one tournament.
 * @var array $tournament
 * @var array $tournaments
 * @var array $rows
 * @var int $finishedCount
 */
@endphp
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="btn-group" role="group" aria-label="Tournament selector">
        @foreach($tournaments as $t)
            <a href="{!! e(url('/standings/' . $t['id'])) !!}"
               class="btn btn-sm {!! (int) $t['id'] === (int) $tournament['id'] ? 'btn-dark' : 'btn-outline-secondary' !!}">
                {!! e($t['name']) !!}
            </a>
        @endforeach
    </div>
    <div class="d-flex gap-2">
        <a href="{!! e(url('/matches?status=finished&tournament_id=' . $tournament['id'])) !!}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-list-check me-1"></i>Results
        </a>
        <a href="{!! e(url('/tournaments/' . $tournament['id'])) !!}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-info-circle me-1"></i>Tournament info
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="bi bi-trophy me-2 text-warning"></i>{!! e($tournament['name']) !!} &mdash; Standings
        </span>
        <span class="small text-muted">{!! $finishedCount !!} finished match(es) counted</span>
    </div>
    <div class="table-responsive">
        <table class="table table-striped align-middle mb-0 standings-table">
            <thead class="table-dark">
            <tr>
                <th style="width: 60px" class="text-center">#</th>
                <th>Team</th>
                <th class="text-center" title="Played">P</th>
                <th class="text-center" title="Won">W</th>
                <th class="text-center" title="Drawn">D</th>
                <th class="text-center" title="Lost">L</th>
                <th class="text-center" title="Goals for">GF</th>
                <th class="text-center" title="Goals against">GA</th>
                <th class="text-center" title="Goal difference">GD</th>
                <th class="text-center" title="Points">Pts</th>
            </tr>
            </thead>
            <tbody>
            @if($rows === [])
                <tr><td colspan="10" class="text-center text-muted py-4">No teams to show.</td></tr>
            @endif
            @foreach($rows as $i => $row)
                <tr class="{!! $i < 3 ? 'table-success' : '' !!}">
                    <td class="text-center">
                        <span class="badge rounded-pill {!! $i === 0 ? 'text-bg-warning' : ($i < 3 ? 'text-bg-success' : 'text-bg-light border') !!}">
                            {!! $i + 1 !!}
                        </span>
                    </td>
                    <td>
                        <a href="{!! e(url('/teams/' . $row['team_id'])) !!}" class="text-decoration-none fw-semibold">
                            {!! e($row['team_name']) !!}
                        </a>
                        <span class="badge text-bg-light border ms-1">{!! e($row['team_code']) !!}</span>
                    </td>
                    <td class="text-center">{!! (int) $row['played'] !!}</td>
                    <td class="text-center">{!! (int) $row['won'] !!}</td>
                    <td class="text-center">{!! (int) $row['drawn'] !!}</td>
                    <td class="text-center">{!! (int) $row['lost'] !!}</td>
                    <td class="text-center">{!! (int) $row['gf'] !!}</td>
                    <td class="text-center">{!! (int) $row['ga'] !!}</td>
                    <td class="text-center">{!! $row['gd'] > 0 ? '+' : '' !!}{!! (int) $row['gd'] !!}</td>
                    <td class="text-center fw-bold fs-6">{!! (int) $row['points'] !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-muted">
        3 points for a win, 1 for a draw. Teams are ordered by points, goal difference, then goals scored.
        The top 3 rows are highlighted.
    </div>
</div>
