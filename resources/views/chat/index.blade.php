@php
/**
 * Chat room list: the user's team room + any group rooms they belong to.
 *
 * @var array $rooms
 * @var array<int,array> $previews room_id => latest message
 * @var bool $canCreateGroup
 */

$isGroup = static fn (array $room): bool => ($room['type'] ?? '') === 'group';
@endphp

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">
        <i class="bi bi-info-circle me-1"></i>Chat with your team, or in a group an administrator added you to.
    </p>
    @if($canCreateGroup)
        <a href="{!! e(url('/chat/create')) !!}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New group chat
        </a>
    @endif
</div>

<div class="row g-3">
    @if($rooms === [])
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-chat-square-dots fs-1 d-block mb-2"></i>
                    No chat rooms available yet.
                </div>
            </div>
        </div>
    @endif

    @foreach($rooms as $room)
        @php
$preview = $previews[(int) $room['id']] ?? null;
@endphp
        <div class="col-md-6 col-xl-4">
            <a href="{!! e(url('/chat/' . $room['id'])) !!}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 chat-room-card">
                    <div class="card-body d-flex gap-3">
                        <div class="stat-icon bg-{!! $isGroup($room) ? 'info' : 'primary' !!} flex-shrink-0">
                            <i class="bi {!! $isGroup($room) ? 'bi-people-fill' : 'bi-shield-fill' !!}"></i>
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center gap-2">
                                <h2 class="h6 mb-0 text-truncate text-dark">{!! e($room['name']) !!}</h2>
                                <span class="badge text-bg-{!! $isGroup($room) ? 'info' : 'primary' !!} flex-shrink-0">
                                    {!! $isGroup($room) ? 'Group' : 'Team' !!}
                                </span>
                            </div>
                            @if($preview !== null)
                                <p class="small text-muted mb-0 mt-1 text-truncate">
                                    <strong class="text-secondary">{!! e($preview['sender_name']) !!}:</strong>
                                    {!! e($preview['body']) !!}
                                </p>
                                <small class="text-muted">{!! e(format_date($preview['created_at'], true)) !!}</small>
                            @else
                                <p class="small text-muted mb-0 mt-1">No messages yet &mdash; say hello!</p>
                            @endif
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>
