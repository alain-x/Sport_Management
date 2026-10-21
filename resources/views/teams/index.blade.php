@php
/**
 * @var array $teams
 * @var string $search
 * @var array $pagination
 */
@endphp
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="get" action="{!! e(url('/teams')) !!}" class="d-flex gap-2" style="min-width: 320px">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="search" name="q" value="{!! e($search) !!}" class="form-control"
                   placeholder="Search by name, code, city or coach...">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
            @if($search !== '')
                <a href="{!! e(url('/teams')) !!}" class="btn btn-outline-danger">Clear</a>
            @endif
        </div>
    </form>
    @if(is_admin())
        <a href="{!! e(url('/teams/create')) !!}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add team
        </a>
    @endif
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="width: 56px"></th>
                <th>Team</th>
                <th>Coach</th>
                <th>City</th>
                <th class="text-center">Players</th>
                <th class="text-center">Founded</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @if($teams === [])
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        No teams found. {!! $search !== '' ? 'Try a different search.' : 'Get started by adding one.' !!}
                    </td>
                </tr>
            @endif
            @php
$selfTeamId = auth_team_id();
@endphp
            @foreach($teams as $team)
                <tr>
                    <td>{!! crest_html($team['logo'] ?? null, $team['code'], 'crest-sm') !!}</td>
                    <td>
                        <a href="{!! e(url('/teams/' . $team['id'])) !!}" class="fw-semibold text-decoration-none">
                            {!! e($team['name']) !!}
                        </a>
                        <span class="badge text-bg-light border ms-1">{!! e($team['code']) !!}</span>
                        @if($selfTeamId !== null && $selfTeamId === (int) $team['id'])
                            <span class="badge text-bg-primary ms-1"><i class="bi bi-star-fill"></i> Your team</span>
                        @endif
                    </td>
                    <td>{!! e($team['coach'] ?? '-') !!}</td>
                    <td>{!! e($team['city'] ?? '-') !!}</td>
                    <td class="text-center">{!! (int) $team['players_count'] !!}</td>
                    <td class="text-center">{!! e($team['founded_year'] ?? '-') !!}</td>
                    <td class="text-end text-nowrap">
                        @php
$canManage = is_admin() || $selfTeamId === (int) $team['id'];
@endphp
                        <a href="{!! e(url('/teams/' . $team['id'])) !!}" class="btn btn-sm btn-outline-secondary"
                           title="View"><i class="bi bi-eye"></i></a>
                        @if($canManage)
                            <a href="{!! e(url('/teams/' . $team['id'] . '/staff')) !!}" class="btn btn-sm btn-outline-secondary"
                               title="Staff"><i class="bi bi-person-badge"></i></a>
                            <a href="{!! e(url('/teams/' . $team['id'] . '/edit')) !!}" class="btn btn-sm btn-outline-primary"
                               title="Edit"><i class="bi bi-pencil"></i></a>
                        @endif
                        @if(is_admin())
                            <form method="post" action="{!! e(url('/teams/' . $team['id'] . '/delete')) !!}" class="d-inline"
                                  onsubmit="return confirm('Delete {!! e($team['name']) !!}? Its players and matches will also be removed.');">
                                {!! csrf_field() !!}
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="small text-muted">
            Showing {!! $teams === [] ? 0 : ($pagination['page'] - 1) * $pagination['perPage'] + 1 !!}&ndash;
            {!! min($pagination['total'], $pagination['page'] * $pagination['perPage']) !!}
            of {!! $pagination['total'] !!} teams
        </span>
        {!! pagination_links($pagination) !!}
    </div>
</div>
