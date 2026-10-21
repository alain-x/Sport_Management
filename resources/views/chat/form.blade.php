@php
/**
 * Create / edit an administrator group chat.
 *
 * @var array|null $room
 * @var int[] $members selected user ids
 * @var array $users selectable users (id, name, email, role, team_name)
 */

$selected = array_map('intval', $members ?? []);
$roleBadge = ['admin' => 'danger', 'manager' => 'primary', 'player' => 'success'];
$roleLabel = ['admin' => 'Admin', 'manager' => 'Staff', 'player' => 'Player'];
$editing = $room !== null;
$backTo = $editing ? '/chat/' . $room['id'] : '/chat';
@endphp

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <a href="{!! e(url($backTo)) !!}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-people-fill me-2"></i>{!! $editing ? 'Edit group chat' : 'New group chat' !!}
            </div>
            <div class="card-body">
                <form method="post" action="{!! e(url($editing ? '/chat/' . $room['id'] . '/update' : '/chat')) !!}">
                    {!! csrf_field() !!}

                    <div class="mb-3">
                        <label class="form-label" for="chat_name">Group name <span class="text-danger">*</span></label>
                        <input id="chat_name" name="name" type="text" maxlength="120" required
                               class="form-control {!! error('name') ? 'is-invalid' : '' !!}"
                               value="{!! e((string) old('name', $room['name'] ?? '')) !!}"
                               placeholder="e.g. Captain's Council">
                        @if(error('name'))<div class="invalid-feedback">{!! e(error('name')) !!}</div>@endif
                    </div>

                    <label class="form-label">Members</label>
                    <p class="form-text mt-0">Choose who can read and post in this group. You are always included.</p>
                    <div class="border rounded p-2 chat-member-picker">
                        @foreach($users as $u)
                            <label class="form-check d-flex align-items-center gap-2 py-1 mb-0">
                                <input class="form-check-input mt-0" type="checkbox" name="members[]" value="{!! (int) $u['id'] !!}"
                                       {!! in_array((int) $u['id'], $selected, true) ? 'checked' : '' !!}>
                                <span class="form-check-label flex-grow-1">
                                    <span class="fw-semibold">{!! e($u['name']) !!}</span>
                                    <span class="small text-muted d-block">{!! e($u['email']) !!}{!! !empty($u['team_name']) ? ' &middot; ' . e($u['team_name']) : '' !!}</span>
                                </span>
                                <span class="badge text-bg-{!! e($roleBadge[$u['role']] ?? 'secondary') !!}">
                                    {!! e($roleLabel[$u['role']] ?? ucfirst((string) $u['role'])) !!}
                                </span>
                            </label>
                        @endforeach
                        @if($users === [])
                            <div class="small text-muted">No users available.</div>
                        @endif
                    </div>

                    <div class="text-end mt-3">
                        <a href="{!! e(url($backTo)) !!}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check-lg me-1"></i>{!! $editing ? 'Save changes' : 'Create group' !!}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
