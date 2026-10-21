@php
/**
 * Admin user list.
 * @var array $users
 * @var string $search
 * @var string $role
 * @var array $pagination
 */
$roleBadge = ['admin' => 'danger', 'manager' => 'primary'];
@endphp
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="get" action="{!! e(url('/users')) !!}" class="d-flex flex-wrap gap-2">
        <div class="input-group" style="min-width: 240px">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="search" name="q" value="{!! e($search) !!}" class="form-control" placeholder="Search name, email or team...">
        </div>
        <select name="role" class="form-select" style="width: auto">
            <option value="">All roles</option>
            <option value="admin" {!! $role === 'admin' ? 'selected' : '' !!}>Administrators</option>
            <option value="manager" {!! $role === 'manager' ? 'selected' : '' !!}>Team staff</option>
        </select>
        <button class="btn btn-outline-secondary" type="submit">Filter</button>
        @if($search !== '' || $role !== '')
            <a href="{!! e(url('/users')) !!}" class="btn btn-outline-danger">Clear</a>
        @endif
    </form>
    <a href="{!! e(url('/users/create')) !!}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Add user
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>User</th>
                <th>Role</th>
                <th>Team</th>
                <th>Phone</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @if($users === [])
                <tr><td colspan="6" class="text-center text-muted py-5">No users match your filters.</td></tr>
            @endif
            @php
$selfId = (int) (auth_user()['id'] ?? 0);
@endphp
            @foreach($users as $member)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            {!! avatar_html($member['photo'] ?? null, $member['name'], 'avatar-sm') !!}
                            <div class="text-truncate">
                                <span class="fw-semibold d-block text-truncate">
                                    {!! e($member['name']) !!}
                                    @if((int) $member['id'] === $selfId)
                                        <span class="badge text-bg-light border">you</span>
                                    @endif
                                </span>
                                <span class="small text-muted text-truncate d-block">{!! e($member['email']) !!}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge text-bg-{!! e($roleBadge[$member['role']] ?? 'secondary') !!}">
                            <i class="bi {!! $member['role'] === 'admin' ? 'bi-shield-lock' : 'bi-person-badge' !!} me-1"></i>
                            {!! $member['role'] === 'admin' ? 'Admin' : 'Staff' !!}
                        </span>
                    </td>
                    <td>
                        @if($member['team_id'] !== null)
                            <a href="{!! e(url('/teams/' . $member['team_id'])) !!}" class="text-decoration-none">
                                {!! e($member['team_name'] ?? 'Unknown') !!}
                                @if(!empty($member['team_code']))<span class="small text-muted">({!! e($member['team_code']) !!})</span>@endif
                            </a>
                        @else-@endif
                    </td>
                    <td class="small">{!! e($member['phone'] ?? '-') !!}</td>
                    <td class="small text-muted">{!! e(format_date($member['created_at'])) !!}</td>
                    <td class="text-end text-nowrap">
                        <a href="{!! e(url('/users/' . $member['id'] . '/edit')) !!}" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @if((int) $member['id'] !== $selfId)
                            <form method="post" action="{!! e(url('/users/' . $member['id'] . '/delete')) !!}" class="d-inline"
                                  onsubmit="return confirm('Delete {!! e($member['name']) !!}?');">
                                {!! csrf_field() !!}
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i></button>
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
            Showing {!! $users === [] ? 0 : ($pagination['page'] - 1) * $pagination['perPage'] + 1 !!}&ndash;
            {!! min($pagination['total'], $pagination['page'] * $pagination['perPage']) !!}
            of {!! $pagination['total'] !!} users
        </span>
        {!! pagination_links($pagination) !!}
    </div>
</div>
