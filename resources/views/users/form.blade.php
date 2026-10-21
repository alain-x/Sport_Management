@php
/**
 * User create/edit form. Also used for the team-scoped staff editor.
 *
 * @var array|null $user
 * @var array $teams
 * @var bool $staffMode hides role/team fields (role is inherited from the account)
 * @var string $returnTo where Cancel returns to
 */
$isEdit = $user !== null;
$staffMode = $staffMode ?? false;
$action = $isEdit
    ? url($staffMode ? '/staff/' . $user['id'] : '/users/' . $user['id'])
    : url('/users');
$value = static fn (string $key, string $default = '') => old($key, $user[$key] ?? $default);
$role = (string) $value('role', 'manager');
$isPlayer = $isEdit && ($user['role'] ?? '') === 'player';
$teamName = '-';
foreach ($teams as $t) {
    if ((int) $t['id'] === (int) ($user['team_id'] ?? 0)) {
        $teamName = $t['name'];
    }
}
@endphp
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="post" action="{!! e($action) !!}">
                    {!! csrf_field() !!}

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="u_name">Full name <span class="text-danger">*</span></label>
                            <input id="u_name" name="name" class="form-control {!! error('name') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('name')) !!}" required>
                            @if(error('name'))<div class="invalid-feedback">{!! e(error('name')) !!}</div>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="u_email">Email <span class="text-danger">*</span></label>
                            <input id="u_email" name="email" type="email" class="form-control {!! error('email') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('email')) !!}" required>
                            @if(error('email'))<div class="invalid-feedback">{!! e(error('email')) !!}</div>@endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="u_phone">Phone</label>
                            <input id="u_phone" name="phone" class="form-control {!! error('phone') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('phone')) !!}" placeholder="+44 ...">
                            @if(error('phone'))<div class="invalid-feedback">{!! e(error('phone')) !!}</div>@endif
                        </div>

                        @if($staffMode)
                            <div class="col-md-6">
                                <label class="form-label">Team</label>
                                <input class="form-control" value="{!! e($teamName) !!}" disabled>
                            </div>
                        @elseif($isPlayer)
                            <div class="col-md-3">
                                <label class="form-label">Role</label>
                                <input class="form-control" value="Player" disabled>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Team</label>
                                <input class="form-control" value="{!! e($teamName) !!}" disabled>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-light border small mb-0">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Player login. Role, team and the linked player are managed from the player record.
                                    @if(!empty($user['player_id']))
                                        <a href="{!! e(url('/players/' . $user['player_id'])) !!}" class="ms-1">View player profile</a>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="col-md-3">
                                <label class="form-label" for="u_role">Role <span class="text-danger">*</span></label>
                                <select id="u_role" name="role" class="form-select {!! error('role') ? 'is-invalid' : '' !!}">
                                    <option value="manager" {!! $role === 'manager' ? 'selected' : '' !!}>Team staff</option>
                                    <option value="admin" {!! $role === 'admin' ? 'selected' : '' !!}>Administrator</option>
                                </select>
                                @if(error('role'))<div class="invalid-feedback">{!! e(error('role')) !!}</div>@endif
                            </div>
                            <div class="col-md-3" id="teamField">
                                <label class="form-label" for="u_team">Team</label>
                                <select id="u_team" name="team_id" class="form-select {!! error('team_id') ? 'is-invalid' : '' !!}">
                                    <option value="">Select a team</option>
                                    @foreach($teams as $team)
                                        <option value="{!! (int) $team['id'] !!}" {!! (int) $value('team_id') === (int) $team['id'] ? 'selected' : '' !!}>
                                            {!! e($team['name']) !!}
                                        </option>
                                    @endforeach
                                </select>
                                @if(error('team_id'))<div class="invalid-feedback">{!! e(error('team_id')) !!}</div>@endif
                            </div>
                        @endif
                    </div>

                    <hr class="my-4">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="u_password">
                                {!! $isEdit ? 'New password' : 'Password' !!}
                                @if($isEdit)<span class="text-muted small">(leave blank to keep)</span>
                                @else<span class="text-danger">*</span>@endif
                            </label>
                            <input id="u_password" name="password" type="password" minlength="8"
                                   class="form-control {!! error('password') ? 'is-invalid' : '' !!}"
                                   {!! $isEdit ? '' : 'required' !!}>
                            @if(error('password'))<div class="invalid-feedback">{!! e(error('password')) !!}</div>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="u_password2">
                                {!! $isEdit ? 'Confirm new password' : 'Confirm password' !!}
                                @if(!$isEdit)<span class="text-danger">*</span>@endif
                            </label>
                            <input id="u_password2" name="password_confirmation" type="password" minlength="8"
                                   class="form-control {!! error('password_confirmation') ? 'is-invalid' : '' !!}"
                                   {!! $isEdit ? '' : 'required' !!}>
                            @if(error('password_confirmation'))<div class="invalid-feedback">{!! e(error('password_confirmation')) !!}</div>@endif
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{!! e(url($returnTo)) !!}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check-lg me-1"></i>{!! $isEdit ? 'Save changes' : 'Create user' !!}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@if(!$staffMode && !$isPlayer)
<script>
    // The team field only applies to staff accounts.
    (function () {
        const role = document.getElementById('u_role');
        const field = document.getElementById('teamField');
        if (!role || !field) return;
        function sync() { field.style.display = role.value === 'admin' ? 'none' : ''; }
        role.addEventListener('change', sync);
        sync();
    })();
</script>
@endif
