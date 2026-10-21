@php
/**
 * A single chat room: message history, composer and (for groups) member list.
 *
 * @var array $room
 * @var array $messages oldest first, joined with sender_name / sender_photo / sender_role
 * @var int $lastId id of the newest rendered message
 * @var array $members explicit members (group rooms only)
 * @var int $memberCount
 * @var bool $canEditGroup
 */

$roleBadge = ['admin' => 'danger', 'manager' => 'primary', 'player' => 'success'];
$roleLabel = ['admin' => 'Admin', 'manager' => 'Staff', 'player' => 'Player'];
$isGroup = ($room['type'] ?? '') === 'group';
$me = (int) (auth_user()['id'] ?? 0);
@endphp

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="{!! e(url('/chat')) !!}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>All chats
        </a>
        <h1 class="h5 mb-0">
            <i class="bi {!! $isGroup ? 'bi-people-fill' : 'bi-shield-fill' !!} me-1 text-primary"></i>{!! e($room['name']) !!}
        </h1>
        <span class="badge text-bg-{!! $isGroup ? 'info' : 'primary' !!}">{!! $isGroup ? 'Group' : 'Team' !!}</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="small text-muted"><i class="bi bi-person me-1"></i>{!! (int) $memberCount !!} members</span>
        @if($canEditGroup)
            <a href="{!! e(url('/chat/' . $room['id'] . '/edit')) !!}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-gear me-1"></i>Manage
            </a>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="{!! $isGroup ? 'col-lg-9' : 'col-12' !!}">
        <div class="card border-0 shadow-sm chat-card">
            <div class="chat-messages" id="chat-messages">
                @if($messages === [])
                    <div class="text-center text-muted py-5" id="chat-empty">
                        <i class="bi bi-chat-square-dots fs-1 d-block mb-2"></i>
                        No messages yet. Start the conversation!
                    </div>
                @endif

                @foreach($messages as $m)
                    @php
$own = (int) $m['user_id'] === $me;
@endphp
                    <div class="chat-msg {!! $own ? 'chat-msg-own' : '' !!}" data-id="{!! (int) $m['id'] !!}">
                        {!! avatar_html($m['sender_photo'] ?? null, $m['sender_name'], 'avatar-sm') !!}
                        <div class="chat-bubble">
                            <div class="chat-meta">
                                <strong>{!! e($m['sender_name']) !!}</strong>
                                <span class="badge text-bg-{!! e($roleBadge[$m['sender_role']] ?? 'secondary') !!}">
                                    {!! e($roleLabel[$m['sender_role']] ?? ucfirst((string) $m['sender_role'])) !!}
                                </span>
                                <small class="text-muted">{!! e(format_date($m['created_at'], true)) !!}</small>
                            </div>
                            <div class="chat-text">{!! nl2br(e($m['body'])) !!}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card-footer bg-white">
                <form method="post" action="{!! e(url('/chat/' . $room['id'])) !!}" class="d-flex gap-2 align-items-end">
                    {!! csrf_field() !!}
                    <textarea name="body" rows="1" class="form-control chat-input" maxlength="4000" required
                              placeholder="Write a message..."></textarea>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-send-fill"></i><span class="d-none d-sm-inline ms-1">Send</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if($isGroup)
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-people me-2"></i>Members ({!! count($members) !!})
                </div>
                <div class="list-group list-group-flush">
                    @foreach($members as $member)
                        <div class="list-group-item d-flex align-items-center gap-2">
                            {!! avatar_html($member['photo'] ?? null, $member['name'], 'avatar-sm') !!}
                            <div class="min-w-0 flex-grow-1">
                                <div class="small fw-semibold text-truncate">{!! e($member['name']) !!}</div>
                                <div class="small text-muted">{!! e($roleLabel[$member['role']] ?? ucfirst((string) $member['role'])) !!}</div>
                            </div>
                        </div>
                    @endforeach
                    @if($members === [])
                        <div class="list-group-item small text-muted">No explicit members.</div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

<script>
(function () {
    const box = document.getElementById('chat-messages');
    if (!box) return;

    const messagesUrl = {!! json_encode(url('/chat/' . $room['id'] . '/messages')) !!};
    const uploadsBase = {!! json_encode(rtrim(url('/uploads/'), '/') . '/') !!};
    const currentUserId = {!! (int) $me !!};
    const roleBadge = {!! json_encode($roleBadge) !!};
    const roleLabel = {!! json_encode($roleLabel) !!};
    let lastId = {!! (int) $lastId !!};

    function initials(name) {
        const parts = String(name || '').trim().split(/\s+/);
        if (!parts.length) return '?';
        let out = parts[0].charAt(0);
        if (parts.length > 1) out += parts[parts.length - 1].charAt(0);
        return out.toUpperCase();
    }

    function scrollToBottom() {
        box.scrollTop = box.scrollHeight;
    }

    function appendMessage(m) {
        const empty = document.getElementById('chat-empty');
        if (empty) empty.remove();

        const own = Number(m.user_id) === currentUserId;
        const row = document.createElement('div');
        row.className = 'chat-msg' + (own ? ' chat-msg-own' : '');
        row.dataset.id = m.id;

        const avatar = document.createElement('span');
        avatar.className = 'avatar avatar-sm';
        if (m.sender_photo) {
            const img = document.createElement('img');
            img.src = uploadsBase + m.sender_photo;
            img.alt = m.sender_name;
            avatar.appendChild(img);
        } else {
            avatar.textContent = initials(m.sender_name);
        }

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';

        const meta = document.createElement('div');
        meta.className = 'chat-meta';

        const name = document.createElement('strong');
        name.textContent = m.sender_name;

        const badge = document.createElement('span');
        badge.className = 'badge text-bg-' + (roleBadge[m.sender_role] || 'secondary');
        badge.textContent = roleLabel[m.sender_role] || m.sender_role;

        const time = document.createElement('small');
        time.className = 'text-muted';
        time.textContent = m.created_at;

        const text = document.createElement('div');
        text.className = 'chat-text';
        text.textContent = m.body;

        meta.appendChild(name);
        meta.appendChild(badge);
        meta.appendChild(time);
        bubble.appendChild(meta);
        bubble.appendChild(text);
        row.appendChild(avatar);
        row.appendChild(bubble);
        box.appendChild(row);
    }

    async function poll() {
        try {
            const res = await fetch(messagesUrl + '?after=' + encodeURIComponent(lastId), {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;
            const data = await res.json();
            if (!data.messages || !data.messages.length) return;
            data.messages.forEach(function (m) {
                appendMessage(m);
                lastId = Math.max(lastId, Number(m.id));
            });
            scrollToBottom();
        } catch (e) {
            /* network hiccup: try again on the next tick */
        }
    }

    scrollToBottom();
    setInterval(poll, 5000);
})();
</script>
