<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;
use App\Support\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'tournament_id' => $request->query('tournament_id', '') !== '' ? (int) $request->query('tournament_id') : null,
            'status' => $request->query('status', '') !== '' && in_array($request->query('status'), MatchModel::STATUSES, true)
                ? (string) $request->query('status')
                : null,
        ];
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 10;

        $total = MatchModel::countFiltered($filters);
        $matches = MatchModel::paginate($perPage, ($page - 1) * $perPage, $filters);

        $query = array_filter($filters, static fn ($v) => $v !== null);

        return $this->view('matches.index', [
            'pageTitle' => 'Matches',
            'matches' => $matches,
            'filters' => $filters,
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
            'pagination' => paginate($total, $perPage, $page, '/matches', $query),
        ]);
    }

    public function create()
    {
        $this->requireStaff();
        $this->scopeTeamId(); // Managers must be linked to a team.

        return $this->view('matches.form', [
            'pageTitle' => 'Schedule Match',
            'match' => null,
            'teams' => Team::options(),
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireStaff();
        $input = $this->collect($request);
        $errors = $this->rules($input);

        if ($errors !== []) {
            return $this->failedValidation($request, $errors, '/matches/create');
        }

        $ownTeam = $this->scopeTeamId();
        if ($ownTeam !== null
            && ! in_array($ownTeam, [$input['home_team_id'], $input['away_team_id']], true)) {
            abort(403, 'You can only schedule matches involving your own team.');
        }

        MatchModel::create($input);

        return redirect('/matches?page=1')->with('success', 'Match scheduled successfully.');
    }

    public function edit(string $id)
    {
        $this->requireStaff();
        $match = MatchModel::withTeams((int) $id);

        if ($match === null) {
            abort(404);
        }
        $this->guardMatchScope($match);

        return $this->view('matches.form', [
            'pageTitle' => 'Edit Match',
            'match' => $match,
            'teams' => Team::options(),
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->requireStaff();
        $match = MatchModel::find((int) $id);

        if ($match === null) {
            abort(404);
        }
        $this->guardMatchScope(MatchModel::withTeams((int) $id));

        $input = $this->collect($request);
        $errors = $this->rules($input);

        if ($errors !== []) {
            return $this->failedValidation($request, $errors, '/matches/'.$id.'/edit');
        }

        $ownTeam = $this->scopeTeamId();
        if ($ownTeam !== null
            && ! in_array($ownTeam, [$input['home_team_id'], $input['away_team_id']], true)) {
            abort(403, 'You can only manage matches involving your own team.');
        }

        $match->update($input);

        return redirect('/matches')->with('success', 'Match updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->requireStaff();
        $match = MatchModel::withTeams((int) $id);

        if ($match === null) {
            abort(404);
        }
        $this->guardMatchScope($match);

        MatchModel::whereKey((int) $id)->delete();

        return redirect('/matches')->with('success', 'Match deleted.');
    }

    /** Admins manage every match; managers only fixtures involving their team. */
    private function guardMatchScope(?array $match): void
    {
        if (is_admin()) {
            return;
        }

        $ownTeam = $this->scopeTeamId();
        $involved = $ownTeam !== null && $match !== null
            && in_array($ownTeam, [(int) $match['home_team_id'], (int) $match['away_team_id']], true);

        if (! $involved) {
            abort(403, 'This match does not involve your team.');
        }
    }

    /** @return array<string, array<int, string>> */
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
            'status' => 'required|in:'.implode(',', MatchModel::STATUSES),
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
    private function collect(Request $request): array
    {
        $tournamentId = trim((string) $request->input('tournament_id', ''));
        $homeScore = trim((string) $request->input('home_score', ''));
        $awayScore = trim((string) $request->input('away_score', ''));
        $dateTime = trim((string) $request->input('match_date', ''));
        $date = null;

        if ($dateTime !== '') {
            $ts = strtotime($dateTime);
            $date = $ts !== false ? date('Y-m-d H:i:s', $ts) : $dateTime;
        }

        return [
            'tournament_id' => $tournamentId === '' ? null : (int) $tournamentId,
            'home_team_id' => (int) $request->input('home_team_id', 0),
            'away_team_id' => (int) $request->input('away_team_id', 0),
            'home_score' => $homeScore === '' ? null : (int) $homeScore,
            'away_score' => $awayScore === '' ? null : (int) $awayScore,
            'match_date' => $date,
            'venue' => trim((string) $request->input('venue', '')) ?: null,
            'status' => (string) $request->input('status', 'scheduled'),
        ];
    }

    private function failedValidation(Request $request, array $errors, string $fallback): RedirectResponse
    {
        return redirect($fallback)->withInput($request->except('_token'))->withErrors($errors);
    }
}
