<?php

namespace App\Http\Controllers\Api;

use App\Models\Attendance;
use App\Models\EventModel;
use App\Models\Player;
use App\Support\Upload;
use App\Support\UploadException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Calendar events and training attendance (including mobile check-in with a
 * photo captured from the device camera).
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

        return $this->ok(['items' => EventModel::between($from, $to, $teamId)]);
    }

    /** GET /api/events/{id} (with attendance roster for staff / own status for players) */
    public function show(string $id): JsonResponse
    {
        $event = EventModel::withDetails((int) $id);
        if ($event === null) {
            abort(404, 'Event not found.');
        }

        if (! $this->isAdmin() && (int) $event['team_id'] !== (int) $this->user()->team_id) {
            abort(404);
        }

        $event['attendance'] = Attendance::forEvent((int) $id);
        $event['tally'] = Attendance::countByStatus((int) $id);

        if ($this->isPlayer() && $this->user()->player_id !== null) {
            $event['my_attendance'] = Attendance::forPlayerEvent((int) $this->user()->player_id, (int) $id);
        }

        return $this->ok($event);
    }

    /** POST /api/events/{id}/attendance - a player checks in (optional photo). */
    public function checkIn(Request $request, string $id): JsonResponse
    {
        $event = EventModel::findArray((int) $id);
        if ($event === null) {
            abort(404, 'Event not found.');
        }

        $playerId = $this->user()?->player_id;
        if (! $this->isPlayer() || $playerId === null) {
            abort(403, 'Only player accounts can check in to training sessions.');
        }

        if (($event['type'] ?? '') !== 'training') {
            return $this->validationError(['event' => ['Attendance can only be recorded for training sessions.']]);
        }

        if ((int) $event['team_id'] !== (int) $this->user()->team_id) {
            abort(403, 'This training session is not for your team.');
        }

        $status = in_array((string) $request->input('status', ''), Attendance::STATUSES, true)
            ? (string) $request->input('status')
            : 'present';
        $notes = trim((string) $request->input('notes', '')) ?: null;

        try {
            $photo = Upload::image($request->file('photo'), 'attendance');
        } catch (UploadException $e) {
            return $this->validationError(['photo' => [$e->getMessage()]]);
        }

        Attendance::record((int) $id, (int) $playerId, $status, $photo, $notes);

        return $this->message('Attendance recorded.', [
            'attendance' => Attendance::forPlayerEvent((int) $playerId, (int) $id),
        ]);
    }

    /** GET /api/events/{id}/attendance - staff roster view. */
    public function roster(string $id): JsonResponse
    {
        $event = EventModel::withDetails((int) $id);
        if ($event === null) {
            abort(404, 'Event not found.');
        }
        $this->requireTeam((int) $event['team_id']);

        return $this->ok([
            'event' => $event,
            'team' => Player::where('team_id', (int) $event['team_id'])->get()->toArray(),
            'attendance' => Attendance::forEvent((int) $id),
            'tally' => Attendance::countByStatus((int) $id),
        ]);
    }
}
