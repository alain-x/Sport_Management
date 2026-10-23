<?php

namespace App\Http\Controllers\Api;

use App\Models\Attendance;
use App\Models\EventModel;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Support\Upload;
use App\Support\UploadException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Calendar events and training attendance.
 *
 * Staff (admin + team manager/coach) create and manage events for their own
 * team. Players check in to their own team's training sessions by taking a
 * selfie with the device camera; the server records the exact time taken.
 */
class EventApiController extends ApiController
{
    /** GET /api/events?from=&to=&team_id= */
    public function index(Request $request): JsonResponse
    {
        $from = (string) $request->input('from', now()->startOfMonth()->toDateTimeString());
        $to = (string) $request->input('to', now()->endOfMonth()->toDateTimeString());

        // Players/team staff are pinned to their own team; admins may filter.
        if ($this->isAdmin()) {
            $teamId = $request->filled('team_id') ? (int) $request->input('team_id') : null;
        } else {
            $teamId = (int) $this->user()->team_id;
        }

        $items = EventModel::between($from, $to, $teamId);

        // Attach attendance tallies (and the player's own check-in) so the list
        // can show progress without an extra request per event.
        $eventIds = array_map(static fn (array $e): int => (int) $e['id'], $items);
        $counts = Attendance::countsForEvents($eventIds);
        $mine = $this->isPlayer() && $this->user()->player_id !== null
            ? Attendance::forPlayerEvents((int) $this->user()->player_id, $eventIds)
            : [];

        foreach ($items as &$event) {
            $id = (int) $event['id'];
            $event['attendance_counts'] = $counts[$id] ?? array_fill_keys(Attendance::STATUSES, 0) + ['total' => 0];
            $event['my_attendance'] = $mine[$id] ?? null;
            $event['category'] = $event['type'];
        }
        unset($event);

        return $this->ok([
            'items' => $items,
            'categories' => EventModel::TYPES,
            'team' => $this->isAdmin() ? null : Team::findArray((int) $this->teamId()),
        ]);
    }

    /** GET /api/events/{id} (with attendance roster for staff / own status for players). */
    public function show(string $id): JsonResponse
    {
        $event = EventModel::withDetails((int) $id);
        if ($event === null) {
            abort(404, 'Event not found.');
        }

        if (! $this->isAdmin() && (int) $event['team_id'] !== (int) $this->user()->team_id) {
            abort(404);
        }

        $event['category'] = $event['type'];
        $event['attendance'] = Attendance::forEvent((int) $id);
        $event['tally'] = Attendance::countByStatus((int) $id);
        $event['squad_size'] = $event['team_id'] !== null
            ? Player::where('team_id', (int) $event['team_id'])->count()
            : 0;

        if ($this->isPlayer() && $this->user()->player_id !== null) {
            $event['my_attendance'] = Attendance::forPlayerEvent((int) $this->user()->player_id, (int) $id);
        }

        return $this->ok($event);
    }

    /** POST /api/events - staff (admin or team coach) creates an event. */
    public function store(Request $request): JsonResponse
    {
        $this->requireStaff();

        $data = $this->validated($request);

        // Managers/coaches can only register events for their own team.
        if (! $this->isAdmin()) {
            $data['team_id'] = $this->teamId();
        }

        // League defaults when the form leaves the check-in window blank.
        $data['checkin_opens_minutes'] ??= EventModel::DEFAULT_CHECKIN_OPENS_MINUTES;
        $data['checkin_late_minutes'] ??= EventModel::DEFAULT_CHECKIN_LATE_MINUTES;

        $data['created_by'] = (int) $this->user()->id;

        $event = EventModel::create($data);

        return $this->ok(EventModel::withDetails((int) $event->id), 201);
    }

    /** POST /api/events/{id} - staff updates an event. */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->requireStaff();

        $event = EventModel::find((int) $id);
        if ($event === null) {
            abort(404, 'Event not found.');
        }
        $this->requireTeam((int) $event->team_id);

        $data = $this->validated($request, false);
        if (! $this->isAdmin()) {
            $data['team_id'] = $this->teamId();
        }

        // A cleared minute field resets that rule to the default; a missing
        // one leaves the event's existing setting untouched.
        if (array_key_exists('checkin_opens_minutes', $data)
            && $data['checkin_opens_minutes'] === null) {
            $data['checkin_opens_minutes'] = EventModel::DEFAULT_CHECKIN_OPENS_MINUTES;
        }
        if (array_key_exists('checkin_late_minutes', $data)
            && $data['checkin_late_minutes'] === null) {
            $data['checkin_late_minutes'] = EventModel::DEFAULT_CHECKIN_LATE_MINUTES;
        }

        $event->update($data);

        return $this->ok(EventModel::withDetails((int) $event->id));
    }

    /** POST /api/events/{id}/delete - staff deletes an event. */
    public function destroy(string $id): JsonResponse
    {
        $this->requireStaff();

        $event = EventModel::find((int) $id);
        if ($event === null) {
            abort(404, 'Event not found.');
        }
        $this->requireTeam((int) $event->team_id);

        $event->delete();

        return $this->message('Event deleted.');
    }

    /**
     * POST /api/events/{id}/attendance - a player checks in with a camera selfie.
     *
     * The photo is mandatory (the app only offers the camera, never the
     * gallery) and the server stamps the time it was received. Check-in opens
     * a configurable number of minutes before the session (20 by default);
     * anything later than the grace period (10 minutes by default) is late.
     */
    public function checkIn(Request $request, string $id): JsonResponse
    {
        /** @var EventModel|null $event */
        $event = EventModel::find((int) $id);
        if ($event === null) {
            abort(404, 'Event not found.');
        }

        $playerId = $this->user()?->player_id;
        if (! $this->isPlayer() || $playerId === null) {
            abort(403, 'Only player accounts can check in to training sessions.');
        }

        if ((string) $event->type !== 'training') {
            return $this->validationError(['event' => ['Attendance can only be recorded for training sessions.']]);
        }

        if ((int) $event->team_id !== (int) $this->user()->team_id) {
            abort(403, 'This training session is not for your team.');
        }

        // Players may not check in before the manager's early window opens.
        $opensAt = $event->checkinOpensAt();
        if ($opensAt !== null && now()->lt($opensAt)) {
            return $this->validationError([
                'event' => [sprintf(
                    'Check-in is not open yet. It opens at %s, %d minutes before the session.',
                    $opensAt->format('H:i'),
                    (int) $event->checkin_opens_minutes,
                )],
            ]);
        }

        if (! $request->hasFile('photo') || ! $request->file('photo')?->isValid()) {
            return $this->validationError([
                'photo' => ['A selfie taken with the camera is required to check in.'],
            ]);
        }

        try {
            $photo = Upload::image($request->file('photo'), 'attendance');
        } catch (UploadException $e) {
            return $this->validationError(['photo' => [$e->getMessage()]]);
        }

        // Present until the grace period ends, late afterwards.
        $lateAt = $event->checkinLateAt();
        $status = ($lateAt !== null && now()->gt($lateAt)) ? 'late' : 'present';
        $notes = trim((string) $request->input('notes', '')) ?: null;

        // touch = true refreshes the recorded check-in time on re-submission.
        Attendance::record((int) $event->id, (int) $playerId, $status, $photo, $notes, true);

        $record = Attendance::forPlayerEvent((int) $playerId, (int) $event->id);

        return $this->ok([
            'message' => 'Attendance recorded.',
            'attendance' => $record,
            'checked_in_at' => $record['checked_in_at'] ?? now()->toDateTimeString(),
            'status' => $record['status'] ?? $status,
            'checkin_opens_at' => $opensAt?->format('Y-m-d H:i:s'),
            'checkin_late_at' => $lateAt?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * POST /api/events/{id}/checkin-rules - staff adjusts the check-in window.
     *
     * Send both values to update them, or null to reset a value to the league
     * default (20 minutes early / 10 minutes late).
     */
    public function checkinRules(Request $request, string $id): JsonResponse
    {
        $this->requireStaff();

        /** @var EventModel|null $event */
        $event = EventModel::find((int) $id);
        if ($event === null) {
            abort(404, 'Event not found.');
        }
        $this->requireTeam((int) $event->team_id);

        $data = $request->validate([
            'checkin_opens_minutes' => ['nullable', 'integer', 'min:0', 'max:'.EventModel::MAX_CHECKIN_MINUTES],
            'checkin_late_minutes' => ['nullable', 'integer', 'min:0', 'max:'.EventModel::MAX_CHECKIN_MINUTES],
        ]);

        $updates = [];
        $defaults = [
            'checkin_opens_minutes' => EventModel::DEFAULT_CHECKIN_OPENS_MINUTES,
            'checkin_late_minutes' => EventModel::DEFAULT_CHECKIN_LATE_MINUTES,
        ];
        foreach ($defaults as $key => $default) {
            // Only touch the values that were sent; a null clears a custom rule.
            if ($request->exists($key)) {
                $updates[$key] = $data[$key] ?? $default;
            }
        }

        if ($updates === []) {
            return $this->validationError([
                'checkin_opens_minutes' => ['Send the minutes to update, or null to reset them.'],
            ]);
        }

        $event->update($updates);

        return $this->ok(EventModel::withDetails((int) $event->id));
    }

    /** GET /api/events/{id}/attendance - staff roster with every squad member. */
    public function roster(string $id): JsonResponse
    {
        $event = EventModel::withDetails((int) $id);
        if ($event === null) {
            abort(404, 'Event not found.');
        }
        if ($event['team_id'] === null) {
            if (! $this->isAdmin()) {
                abort(403, 'This shared event does not have a team roster.');
            }
        } else {
            $this->requireTeam((int) $event['team_id']);
        }

        $attendance = Attendance::forEvent((int) $id);
        $byPlayer = [];
        foreach ($attendance as $row) {
            $byPlayer[(int) $row['player_id']] = $row;
        }

        $roster = [];
        foreach (Player::rosterForTeam((int) $event['team_id']) as $player) {
            $row = $byPlayer[(int) $player['id']] ?? null;
            $roster[] = $player + [
                'attendance' => $row,
                'status' => $row['status'] ?? null,
                'checked_in_at' => $row['checked_in_at'] ?? null,
                'photo' => $row['photo'] ?? null,
            ];
        }

        $event['category'] = $event['type'];

        return $this->ok([
            'event' => $event,
            'team' => Team::findArray((int) $event['team_id']),
            'squad' => $roster,
            'attendance' => $attendance,
            'tally' => Attendance::withRate(Attendance::countByStatus((int) $id)),
            'statuses' => Attendance::STATUSES,
        ]);
    }

    /** POST /api/events/{id}/attendance/{playerId} - staff adjusts a status. */
    public function updateAttendance(Request $request, string $id, string $playerId): JsonResponse
    {
        $event = EventModel::withDetails((int) $id);
        if ($event === null) {
            abort(404, 'Event not found.');
        }
        $this->requireTeam((int) $event['team_id']);

        $player = Player::find((int) $playerId);
        if ($player === null || (int) $player->team_id !== (int) $event['team_id']) {
            return $this->validationError(['player' => ['This player is not part of the event squad.']]);
        }

        $status = (string) $request->input('status', '');
        if (! in_array($status, Attendance::STATUSES, true)) {
            return $this->validationError(['status' => ['Unknown attendance status.']]);
        }

        $notes = trim((string) $request->input('notes', '')) ?: null;

        Attendance::setStatus((int) $id, (int) $playerId, $status, $notes);

        return $this->ok([
            'attendance' => Attendance::forPlayerEvent((int) $playerId, (int) $id),
            'tally' => Attendance::withRate(Attendance::countByStatus((int) $id)),
        ]);
    }

    /** GET /api/event-options - dropdown data for the event form. */
    public function options(): JsonResponse
    {
        $this->requireStaff();

        return $this->ok([
            'categories' => EventModel::TYPES,
            'team' => $this->isAdmin() ? null : Team::findArray((int) $this->teamId()),
            'teams' => $this->isAdmin() ? Team::options() : [],
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
            'checkin_defaults' => [
                'opens_minutes' => EventModel::DEFAULT_CHECKIN_OPENS_MINUTES,
                'late_minutes' => EventModel::DEFAULT_CHECKIN_LATE_MINUTES,
                'max_minutes' => EventModel::MAX_CHECKIN_MINUTES,
            ],
        ]);
    }

    /** Shared validation for create/update. */
    private function validated(Request $request, bool $requireTeam = false): array
    {
        $max = EventModel::MAX_CHECKIN_MINUTES;

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'type' => ['required', 'string', 'in:'.implode(',', EventModel::TYPES)],
            'starts_at' => ['required', 'date'],
            'checkin_opens_minutes' => ['nullable', 'integer', 'min:0', 'max:'.$max],
            'checkin_late_minutes' => ['nullable', 'integer', 'min:0', 'max:'.$max],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'tournament_id' => ['nullable', 'integer', 'exists:tournaments,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($requireTeam && ! $this->isAdmin() && $this->user()->team_id === null) {
            abort(403, 'Your account is not linked to a team.');
        }

        $data['starts_at'] = date('Y-m-d H:i:s', (int) strtotime((string) $data['starts_at']));
        $data['description'] = trim((string) ($data['description'] ?? '')) ?: null;
        $data['team_id'] = isset($data['team_id']) ? (int) $data['team_id'] : null;
        $data['tournament_id'] = isset($data['tournament_id']) ? (int) $data['tournament_id'] : null;

        return $data;
    }
}
