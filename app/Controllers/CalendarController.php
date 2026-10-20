<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Validator;
use App\Models\EventModel;
use App\Models\MatchModel;
use App\Models\Tournament;
use App\Models\Team;

class CalendarController extends Controller
{
    /** Month-grid calendar with events and fixtures overlaid. */
    public function index(): void
    {
        $scopeTeam = $this->scopeTeamId();

        $monthParam = (string) ($_GET['month'] ?? date('Y-m'));
        $ts = strtotime($monthParam . '-01');
        if ($ts === false) {
            $ts = (int) strtotime(date('Y-m-01'));
        }
        $monthKey = date('Y-m', $ts);

        $from = date('Y-m-01 00:00:00', $ts);
        $to = date('Y-m-d H:i:s', strtotime('+1 month', $ts));

        $byDate = [];
        foreach (EventModel::between($from, $to, $scopeTeam) as $event) {
            $byDate[date('Y-m-d', strtotime($event['starts_at']))][] = ['kind' => 'event', 'row' => $event];
        }
        foreach (MatchModel::between($from, $to, $scopeTeam) as $match) {
            if ($match['match_date'] === null) {
                continue;
            }
            $byDate[date('Y-m-d', strtotime($match['match_date']))][] = ['kind' => 'match', 'row' => $match];
        }

        // 6 weeks x 7 days grid, starting on the Sunday of the week containing the 1st.
        $startDow = (int) date('w', $ts);
        $cursor = (int) strtotime(date('Y-m-01', $ts) . ' -' . $startDow . ' days');
        $cells = [];
        for ($i = 0; $i < 42; $i++) {
            $cells[] = [
                'key' => date('Y-m-d', $cursor),
                'day' => (int) date('j', $cursor),
                'inMonth' => date('Y-m', $cursor) === $monthKey,
                'isToday' => date('Y-m-d', $cursor) === date('Y-m-d'),
            ];
            $cursor = (int) strtotime('+1 day', $cursor);
        }

        $this->render('calendar/index', [
            'pageTitle' => 'Calendar',
            'monthKey' => $monthKey,
            'monthLabel' => date('F Y', $ts),
            'prevMonth' => date('Y-m', strtotime('-1 month', $ts)),
            'nextMonth' => date('Y-m', strtotime('+1 month', $ts)),
            'cells' => $cells,
            'byDate' => $byDate,
            'upcoming' => EventModel::upcoming(6, $scopeTeam),
            'scopeTeamId' => $scopeTeam,
        ]);
    }

    public function create(): void
    {
        $scopeTeam = $this->scopeTeamId();

        $this->render('events/form', [
            'pageTitle' => 'New Event',
            'event' => null,
            'teams' => $scopeTeam === null ? Team::options() : [],
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
            'backMonth' => $this->monthParam(),
            'scopeTeamId' => $scopeTeam,
        ]);
    }

    public function store(): void
    {
        $scopeTeam = $this->scopeTeamId();

        $input = $this->collect();
        $errors = $this->rules($input);
        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/events/create');
        }

        $input['team_id'] = $scopeTeam !== null ? $scopeTeam : $input['team_id'];
        $input['created_by'] = (int) (auth_user()['id'] ?? 0);
        $input['starts_at'] = date(
            'Y-m-d H:i:s',
            (int) strtotime($input['event_date'] . ' ' . $input['event_time'])
        );

        $id = EventModel::create([
            'title' => $input['title'],
            'type' => $input['type'],
            'starts_at' => $input['starts_at'],
            'team_id' => $input['team_id'],
            'tournament_id' => $input['tournament_id'],
            'description' => $input['description'],
            'created_by' => $input['created_by'],
        ]);

        flash('success', 'Event created.');
        redirect('/calendar?month=' . date('Y-m', strtotime($input['starts_at'])) . '#event-' . $id);
    }

    public function edit(string $id): void
    {
        $scopeTeam = $this->scopeTeamId();
        $event = EventModel::withDetails((int) $id);
        if ($event === null) {
            $this->notFound();

            return;
        }
        $this->requireEventAccess($event, $scopeTeam);

        $this->render('events/form', [
            'pageTitle' => 'Edit Event',
            'event' => $event,
            'teams' => $scopeTeam === null ? Team::options() : [],
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
            'backMonth' => date('Y-m', strtotime($event['starts_at'])),
            'scopeTeamId' => $scopeTeam,
        ]);
    }

    public function update(string $id): void
    {
        $scopeTeam = $this->scopeTeamId();
        $event = EventModel::withDetails((int) $id);
        if ($event === null) {
            $this->notFound();

            return;
        }
        $this->requireEventAccess($event, $scopeTeam);

        $input = $this->collect();
        $errors = $this->rules($input, (int) $id);
        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/events/' . $id . '/edit');
        }

        $input['team_id'] = $scopeTeam !== null ? $scopeTeam : $input['team_id'];
        $startsAt = date('Y-m-d H:i:s', (int) strtotime($input['event_date'] . ' ' . $input['event_time']));

        EventModel::update((int) $id, [
            'title' => $input['title'],
            'type' => $input['type'],
            'starts_at' => $startsAt,
            'team_id' => $input['team_id'],
            'tournament_id' => $input['tournament_id'],
            'description' => $input['description'],
        ]);

        flash('success', 'Event updated.');
        redirect('/calendar?month=' . date('Y-m', strtotime($startsAt)) . '#event-' . $id);
    }

    public function destroy(string $id): void
    {
        $scopeTeam = $this->scopeTeamId();
        $event = EventModel::withDetails((int) $id);
        if ($event === null) {
            $this->notFound();

            return;
        }
        $this->requireEventAccess($event, $scopeTeam);

        EventModel::delete((int) $id);
        flash('success', 'Event deleted.');
        redirect('/calendar?month=' . date('Y-m', strtotime($event['starts_at'])));
    }

    /** Admins manage every event; managers only their own team's. */
    private function requireEventAccess(array $event, ?int $scopeTeam): void
    {
        if ($scopeTeam === null) {
            return;
        }
        if ((int) ($event['team_id'] ?? 0) !== $scopeTeam) {
            $this->abort403('You can only manage events belonging to your own team.');
        }
    }

    /** @return array<string, string> */
    private function rules(array $input, ?int $ignoreId = null): array
    {
        unset($ignoreId); // Titles are not unique; nothing to ignore.

        return Validator::validate($input, [
            'title' => 'required|min:3|max:150',
            'type' => 'required|in:' . implode(',', EventModel::TYPES),
            'event_date' => 'required|date',
            'event_time' => 'required',
            'team_id' => 'integer|exists:teams,id',
            'tournament_id' => 'integer|exists:tournaments,id',
            'description' => 'max:2000',
        ]);
    }

    /** @return array<string, mixed> */
    private function collect(): array
    {
        $teamId = trim((string) ($_POST['team_id'] ?? ''));
        $tournamentId = trim((string) ($_POST['tournament_id'] ?? ''));
        $time = trim((string) ($_POST['event_time'] ?? ''));
        if ($time !== '' && preg_match('#^\d{1,2}:\d{2}$#', $time)) {
            $time = date('H:i:s', (int) strtotime($time));
        }

        return [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'type' => (string) ($_POST['type'] ?? 'training'),
            'event_date' => trim((string) ($_POST['event_date'] ?? '')),
            'event_time' => $time === '' ? '09:00' : $time,
            'team_id' => $teamId === '' ? null : (int) $teamId,
            'tournament_id' => $tournamentId === '' ? null : (int) $tournamentId,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
        ];
    }

    private function monthParam(): string
    {
        $ts = strtotime((string) ($_GET['month'] ?? date('Y-m')) . '-01');

        return $ts !== false ? date('Y-m', $ts) : date('Y-m');
    }
}
