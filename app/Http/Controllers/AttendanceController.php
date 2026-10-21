<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\EventModel;
use App\Models\Team;
use App\Support\Upload;
use App\Support\UploadException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Training attendance.
 *
 * Players record their own presence at a training session, optionally
 * attaching a check-in photo (taken with the device camera). Staff can view
 * the per-session roster and adjust a player's status.
 */
class AttendanceController extends Controller
{
    /** A player checks in to their own team's training session (with an optional photo). */
    public function store(Request $request, string $id): RedirectResponse
    {
        $event = EventModel::findArray((int) $id);

        if ($event === null) {
            abort(404);
        }

        $playerId = auth_player_id();
        if ($playerId === null) {
            abort(403, 'Only player accounts can check in to training sessions.');
        }

        if (($event['type'] ?? '') !== 'training') {
            return redirect('/')->with('error', 'Attendance can only be recorded for training sessions.');
        }

        if ((int) $event['team_id'] !== auth_team_id()) {
            abort(403, 'This training session is not for your team.');
        }

        $status = in_array((string) $request->input('status', ''), Attendance::STATUSES, true)
            ? (string) $request->input('status')
            : 'present';
        $notes = trim((string) $request->input('notes', '')) ?: null;

        try {
            $photo = Upload::image($request->file('photo'), 'attendance');
        } catch (UploadException $e) {
            return redirect('/')
                ->withInput(['status' => $status, 'notes' => $notes])
                ->withErrors(['photo' => [$e->getMessage()]]);
        }

        Attendance::record((int) $id, $playerId, $status, $photo, $notes);

        return redirect('/')->with('success', 'Your attendance for "'.$event['title'].'" has been recorded.');
    }

    /** Staff view: who has checked in to a given training session. */
    public function roster(string $id)
    {
        $event = EventModel::withDetails((int) $id);

        if ($event === null) {
            abort(404);
        }
        $this->requireTeam((int) $event['team_id']);

        $existing = [];
        foreach (Attendance::forEvent((int) $id) as $row) {
            $existing[(int) $row['player_id']] = $row;
        }

        $team = Team::withPlayers((int) $event['team_id']);

        return $this->view('attendance.roster', [
            'pageTitle' => 'Attendance - '.$event['title'],
            'event' => $event,
            'team' => $team,
            'existing' => $existing,
            'tally' => Attendance::countByStatus((int) $id),
            'statuses' => Attendance::STATUSES,
        ]);
    }

    /** Staff can adjust a player's attendance status for a session. */
    public function update(Request $request, string $id, string $playerId): RedirectResponse
    {
        $event = EventModel::findArray((int) $id);

        if ($event === null) {
            abort(404);
        }
        $this->requireTeam((int) $event['team_id']);

        $status = (string) $request->input('status', '');
        if (! in_array($status, Attendance::STATUSES, true)) {
            return redirect('/events/'.$id.'/attendance')->with('error', 'Unknown attendance status.');
        }

        // Keep any existing photo (staff edits only change the status/notes).
        Attendance::record((int) $id, (int) $playerId, $status, null, null);

        return redirect('/events/'.$id.'/attendance')->with('success', 'Attendance updated.');
    }
}
