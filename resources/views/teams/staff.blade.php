@php
/**
 * Team staff management: list accounts for this team and add new ones.
 * @var array $team includes 'staff' rows and 'staff_count'
 */
$isAdmin = is_admin();
@endphp
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <a href="{!! e(url('/teams/' . $team['id'])) !!}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to {!! e($team['name']) !!}
    </a>
    <span class="text-muted small">{!! count($team['staff']) !!} staff account(s)</span>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person-badge me-2"></i>Team staff
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Member</th>
                        <th>Phone</th>
                        <th>Added</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if($team['staff'] === [])
                        <tr><td colspan="4" class="text-center text-muted py-4">
                            No staff accounts yet. Add the first one on the right.
                        </td></tr>
                    @endif
                    @php
$selfId = (int) (auth_user()['id'] ?? 0);
@endphp
                    @foreach($team['staff'] as $member)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    {!! avatar_html($member['photo'] ?? null, $member['name'], 'avatar-sm') !!}
                                    <div class="text-truncate">
                                        <span class="fw-semibold d-block text-truncate">{!! e($member['name']) !!}</span>
                                        <span class="small text-muted text-truncate d-block">{!! e($member['email']) !!}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="small">{!! e($member['phone'] ?? '-') !!}</td>
                            <td class="small text-muted">{!! e(format_date($member['created_at'])) !!}</td>
                            <td class="text-end text-nowrap">
                                <a href="{!! e(url('/staff/' . $member['id'] . '/edit')) !!}"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if((int) $member['id'] !== $selfId)
                                    <form method="post" action="{!! e(url('/staff/' . $member['id'] . '/delete')) !!}"
                                          class="d-inline" onsubmit="return confirm('Remove {!! e($member['name']) !!} from the staff list?');">
                                        {!! csrf_field() !!}
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Remove">
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
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person-plus me-2"></i>Add staff member
            </div>
            <div class="card-body">
                <form method="post" action="{!! e(url('/teams/' . $team['id'] . '/staff')) !!}">
                    {!! csrf_field() !!}

                    <div class="mb-3">
                        <label class="form-label" for="s_name">Full name <span class="text-danger">*</span></label>
                        <input id="s_name" name="name" class="form-control {!! error('name') ? 'is-invalid' : '' !!}"
                               value="{!! e((string) old('name')) !!}" placeholder="e.g. Sara Nolan" required>
                        @if(error('name'))<div class="invalid-feedback">{!! e(error('name')) !!}</div>@endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="s_email">Email <span class="text-danger">*</span></label>
                        <input id="s_email" name="email" type="email" class="form-control {!! error('email') ? 'is-invalid' : '' !!}"
                               value="{!! e((string) old('email')) !!}" placeholder="name@club.test" required>
                        @if(error('email'))<div class="invalid-feedback">{!! e(error('email')) !!}</div>@endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="s_phone">Phone</label>
                        <input id="s_phone" name="phone" class="form-control {!! error('phone') ? 'is-invalid' : '' !!}"
                               value="{!! e((string) old('phone')) !!}" placeholder="+44 ...">
                        @if(error('phone'))<div class="invalid-feedback">{!! e(error('phone')) !!}</div>@endif
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="s_password">Password <span class="text-danger">*</span></label>
                            <input id="s_password" name="password" type="password" minlength="8"
                                   class="form-control {!! error('password') ? 'is-invalid' : '' !!}" required>
                            @if(error('password'))<div class="invalid-feedback">{!! e(error('password')) !!}</div>@endif
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="s_password2">Confirm password <span class="text-danger">*</span></label>
                            <input id="s_password2" name="password_confirmation" type="password" minlength="8"
                                   class="form-control {!! error('password_confirmation') ? 'is-invalid' : '' !!}" required>
                            @if(error('password_confirmation'))<div class="invalid-feedback">{!! e(error('password_confirmation')) !!}</div>@endif
                        </div>
                    </div>

                    <div class="form-text mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        {!! $isAdmin
                            ? 'The account is created with the staff role for ' . e($team['name']) . '.'
                            : 'You can add staff members for your own team. They get full access to this team only.' !!}
                    </div>

                    <button class="btn btn-primary w-100" type="submit">
                        <i class="bi bi-person-plus me-1"></i>Add staff member
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
