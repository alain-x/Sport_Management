<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Validator;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;

class MatchController extends Controller
{
    public function index(): void
    {
        $filters = [
            'tournament_id' => ($_GET['tournament_id'] ?? '') !== '' ? (int) $_GET['tournament_id'] : null,
            'status' => ($_GET['status'] ?? '') !== '' && in_array($_GET['status'], MatchModel::STATUSES, true)
                ? $_GET['status']
                : null,
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 10;

        $total = MatchModel::countFiltered($filters);
        $matches = MatchModel::paginate($perPage, ($page - 1) * $perPage, $filters);

        $query = array_filter($filters, static fn ($v) => $v !== null);

        $this->render('matches/index', [
            'pageTitle' => 'Matches',
            'matches' => $matches,
            'filters' => $filters,
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
            'pagination' => paginate($total, $perPage, $page, '/matches', $query),
        ]);
    }

    public function create(): void
    {
        $this->scopeTeamId(); // Managers must be linked to a team.

        $this->render('matches/form', [
            'pageTitle' => 'Schedule Match',
            'match' => null,
            'teams' => Team::options(),
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
        ]);
    }

    public function store(): void
    {
        $input = $this->collect();
        $errors = $this->rules($input);

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/matches/create');
        }

        $ownTeam = $this->scopeTeamId();
        if ($ownTeam !== null
            && !in_array($ownTeam, [$input['home_team_id'], $input['away_team_id']], true)) {
            $this->abort403('You can only schedule matches involving your own team.');
        }

        $id = MatchModel::create($input);
        flash('success', 'Match scheduled successfully.');
        redirect('/matches?page=1');
    }

    public function edit(string $id): void
    {
        $match = MatchModel::withTeams((int) $id);

        if ($match === null) {
            $this->notFound();

            return;
        }
        $this->guardMatchScope($match);

        $this->render('matches/form', [
            'pageTitle' => 'Edit Match',
            'match' => $match,
            'teams' => Team::options(),
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
        ]);
    }

    public function update(string $id): void
    {
        $match = MatchModel::withTeams((int) $id);

        if ($match === null) {
            $this->notFound();

            return;
        }
        $this->guardMatchScope($match);

        $input = $this->collect();
        $errors = $this->rules($input);

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/matches/' . $id . '/edit');
        }

        $ownTeam = $this->scopeTeamId();
        if ($ownTeam !== null
            && !in_array($ownTeam, [$input['home_team_id'], $input['away_team_id']], true)) {
            $this->abort403('You can only manage matches involving your own team.');
        }

        MatchModel::update((int) $id, $input);
        flash('success', 'Match updated successfully.');
        redirect('/matches');
    }

    public function destroy(string $id): void
    {
        $match = MatchModel::withTeams((int) $id);

        if ($match === null) {
            $this->notFound();

            return;
        }
        $this->guardMatchScope($match);

        MatchModel::delete((int) $id);
        flash('success', 'Match deleted.');
        redirect('/matches');
    }

    /** Admins manage every match; managers only fixtures involving their team. */
    private function guardMatchScope(array $match): void
    {
        if (is_admin()) {
            return;
        }

        $ownTeam = $this->scopeTeamId();
        $involved = $ownTeam !== null
            && in_array($ownTeam, [(int) $match['home_team_id'], (int) $match['away_team_id']], true);

        if (!$involved) {
            $this->abort403('This match does not involve your team.');
        }
    }

    /** @return array<string, string> */
    private function rules(array $input): array
    {
        $errors = Validator::validate($input, [
            'tournament_id' => 'integer|exists:tournaments,id',
            'home_team_id' => 'required|integer|exists:teams,id',
            'away_team_id' => 'required|integer|exists:teams,id',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'match_date' => 'date',
            'venue' => 'max:150',
            'status' => 'required|in:' . implode(',', MatchModel::STATUSES),
        ]);

        if ($errors === [] && $input['home_team_id'] === $input['away_team_id']) {
            $errors['away_team_id'][] = 'A team cannot play against itself.';
        }

        if ($input['status'] === 'finished') {
            if ($input['home_score'] === null) {
                $errors['home_score'][] = 'Home score is required for finished matches.';
            }
            if ($input['away_score'] === null) {
                $errors['away_score'][] = 'Away score is required for finished matches.';
            }
        }

        if ($input['status'] !== 'finished' && $input['match_date'] === null) {
            $errors['match_date'][] = 'Match date and time are required.';
        }

        if ($input['home_score'] !== null && $input['home_score'] < 0) {
            $errors['home_score'][] = 'Score cannot be negative.';
        }
        if ($input['away_score'] !== null && $input['away_score'] < 0) {
            $errors['away_score'][] = 'Score cannot be negative.';
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function collect(): array
    {
        $tournamentId = trim((string) ($_POST['tournament_id'] ?? ''));
        $homeScore = trim((string) ($_POST['home_score'] ?? ''));
        $awayScore = trim((string) ($_POST['away_score'] ?? ''));
        $dateTime = trim((string) ($_POST['match_date'] ?? ''));
        $date = null;

        if ($dateTime !== '') {
            $ts = strtotime($dateTime);
            $date = $ts !== false ? date('Y-m-d H:i:s', $ts) : $dateTime;
        }

        return [
            'tournament_id' => $tournamentId === '' ? null : (int) $tournamentId,
            'home_team_id' => (int) ($_POST['home_team_id'] ?? 0),
            'away_team_id' => (int) ($_POST['away_team_id'] ?? 0),
            'home_score' => $homeScore === '' ? null : (int) $homeScore,
            'away_score' => $awayScore === '' ? null : (int) $awayScore,
            'match_date' => $date,
            'venue' => trim((string) ($_POST['venue'] ?? '')) ?: null,
            'status' => (string) ($_POST['status'] ?? 'scheduled'),
        ];
    }
}
