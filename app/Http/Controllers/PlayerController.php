<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\PlayerMetric;
use App\Models\PlayerWelfare;
use App\Models\Team;
use App\Models\User;
use App\Support\Upload;
use App\Support\UploadException;
use App\Support\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function index(Request $request)
    {
        $this->requireStaff();
        $scopeTeam = $this->scopeTeamId();
        $search = trim((string) $request->query('q', ''));
        $requestedTeam = $request->query('team_id', '') !== '' ? (int) $request->query('team_id') : null;
        $teamId = $scopeTeam ?? $requestedTeam;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 10;

        $total = Player::countFiltered($search, $teamId);
        $players = Player::paginate($perPage, ($page - 1) * $perPage, $search, $teamId);

        $query = array_filter(['q' => $search, 'team_id' => $teamId], static fn ($v) => $v !== null && $v !== '');

        return $this->view('players.index', [
            'pageTitle' => 'Players',
            'players' => $players,
            'search' => $search,
            'teamId' => $teamId,
            'teams' => Team::options(),
            'scopeTeam' => $scopeTeam !== null ? Team::findArray($scopeTeam) : null,
            'pagination' => paginate($total, $perPage, $page, '/players', $query),
        ]);
    }

    public function create()
    {
        $this->requireStaff();
        $scopeTeam = $this->scopeTeamId();

        return $this->view('players.form', [
            'pageTitle' => 'Add Player',
            'player' => null,
            'teams' => Team::options(),
            'scopeTeam' => $scopeTeam !== null ? Team::findArray($scopeTeam) : null,
            'loginUser' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireStaff();
        $scopeTeam = $this->scopeTeamId();

        $input = $this->collect($request);
        if ($scopeTeam !== null) {
            $input['team_id'] = $scopeTeam;
        }
        $errors = $this->rules($input);

        // Optional player login (1:1 with the player record).
        $login = $this->loginCollect($request);
        $errors = array_merge($errors, $this->loginRules($login));

        if ($errors !== []) {
            return $this->failedValidation($request, $errors, '/players/create', $input, $login);
        }

        try {
            if ($request->hasFile('photo')) {
                $input['photo'] = Upload::image($request->file('photo'), 'players');
            }
        } catch (UploadException $e) {
            return $this->failedValidation($request, ['photo' => [$e->getMessage()]], '/players/create', $input, $login);
        }

        $id = Player::create($input)->id;

        if ($login['create']) {
            $this->createPlayerLogin((int) $id, $input, $login);

            return redirect('/players?page=1&q='.urlencode($input['last_name']))
                ->with('success', 'Player and login account added successfully.');
        }

        return redirect('/players?page=1&q='.urlencode($input['last_name']))
            ->with('success', 'Player added successfully.');
    }

    /** Player detail page: profile, statistics, physical and mental tabs. */
    public function show(string $id)
    {
        $player = Player::withTeam((int) $id);

        if ($player === null) {
            abort(404);
        }

        // Staff of the same team (or admins) manage and see private data.
        $isStaffOfTeam = is_staff() && (is_admin() || auth_team_id() === (int) $player['team_id']);
        // A player may view their OWN record (performance + wellbeing), but never edit it.
        $isSelf = is_player() && auth_player_id() === (int) $player['id'];

        $canManage = $isStaffOfTeam || $isSelf; // Controls visibility of the private tabs.
        $canEdit = $isStaffOfTeam;              // Controls edit forms / destructive actions.

        return $this->view('players.show', [
            'pageTitle' => $player['first_name'].' '.$player['last_name'],
            'player' => $player,
            'canManage' => $canManage,
            'canEdit' => $canEdit,
            'metrics' => $canManage ? PlayerMetric::forPlayer((int) $id) : [],
            'latestMetric' => $canManage ? PlayerMetric::latestFor((int) $id) : null,
            'welfare' => $canManage ? PlayerWelfare::forPlayer((int) $id) : [],
            'latestWelfare' => $canManage ? PlayerWelfare::latestFor((int) $id) : null,
        ]);
    }

    public function edit(string $id)
    {
        $player = Player::withTeam((int) $id);

        if ($player === null) {
            abort(404);
        }
        $this->requireTeam((int) $player['team_id']);

        return $this->view('players.form', [
            'pageTitle' => 'Edit Player',
            'player' => $player,
            'teams' => Team::options(),
            'scopeTeam' => is_admin() ? null : Team::findArray((int) $player['team_id']),
            'loginUser' => User::findByPlayerId((int) $id),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $player = Player::find((int) $id);

        if ($player === null) {
            abort(404);
        }
        $this->requireTeam((int) $player->team_id);

        $input = $this->collect($request);
        $errors = $this->rules($input, (int) $id);

        // A login can be added later if the player was created without one.
        $login = $this->loginCollect($request);
        $existingLogin = User::findByPlayerId((int) $id);
        if ($existingLogin === null) {
            $errors = array_merge($errors, $this->loginRules($login));
        }

        if ($errors !== []) {
            return $this->failedValidation($request, $errors, '/players/'.$id.'/edit', $input, $login);
        }

        try {
            if ($request->hasFile('photo')) {
                $input['photo'] = Upload::image($request->file('photo'), 'players', $player->photo);
            }
        } catch (UploadException $e) {
            return $this->failedValidation($request, ['photo' => [$e->getMessage()]], '/players/'.$id.'/edit', $input, $login);
        }

        $player->update($input);

        if ($existingLogin === null && $login['create']) {
            $this->createPlayerLogin((int) $id, $input, $login);

            return redirect('/players/'.$id)->with('success', 'Player updated and login account created.');
        }

        if ($existingLogin !== null) {
            // Keep the login's team in sync so role scoping stays correct after a transfer.
            User::whereKey((int) $existingLogin['id'])->update(['team_id' => (int) $input['team_id']]);
        }

        return redirect('/players/'.$id)->with('success', 'Player updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $player = Player::find((int) $id);

        if ($player === null) {
            abort(404);
        }
        $this->requireTeam((int) $player->team_id);

        Upload::delete($player->photo);
        $player->delete();

        return redirect('/players')->with('success', 'Player deleted.');
    }

    /** Season statistics panel on the detail page. */
    public function updateStats(Request $request, string $id): RedirectResponse
    {
        $player = Player::find((int) $id);

        if ($player === null) {
            abort(404);
        }
        $this->requireTeam((int) $player->team_id);

        $int = static function (string $key, int $default = 0) use ($request): int {
            $raw = trim((string) $request->input($key, ''));

            return $raw === '' ? $default : max(0, (int) $raw);
        };
        $ratingRaw = trim((string) $request->input('rating', ''));

        $input = [
            'appearances' => $int('appearances'),
            'minutes_played' => $int('minutes_played'),
            'goals' => $int('goals'),
            'assists' => $int('assists'),
            'yellow_cards' => $int('yellow_cards'),
            'red_cards' => $int('red_cards'),
            'rating' => $ratingRaw === '' ? null : (is_numeric($ratingRaw) ? (float) $ratingRaw : $ratingRaw),
        ];

        $errors = Validator::validate($input, [
            'appearances' => 'integer',
            'minutes_played' => 'integer',
            'goals' => 'integer',
            'assists' => 'integer',
            'yellow_cards' => 'integer',
            'red_cards' => 'integer',
            'rating' => 'numeric',
        ]);

        if ($errors === [] && is_numeric($input['rating'])
            && ((float) $input['rating'] < 0 || (float) $input['rating'] > 10)) {
            $errors['rating'][] = 'Rating must be between 0 and 10.';
        }
        if ($errors === [] && $input['rating'] !== null && ! is_numeric((string) $input['rating'])) {
            $errors['rating'][] = 'Rating must be a number.';
        }

        if ($errors !== []) {
            return redirect('/players/'.$id)->withInput($request->except('_token'))->withErrors($errors);
        }

        $player->update($input);

        return redirect('/players/'.$id.'#statistics')->with('success', 'Statistics updated.');
    }

    /** Photo upload from the detail page. */
    public function uploadPhoto(Request $request, string $id): RedirectResponse
    {
        $player = Player::find((int) $id);

        if ($player === null) {
            abort(404);
        }
        $this->requireTeam((int) $player->team_id);

        try {
            $photo = Upload::image($request->file('photo'), 'players', $player->photo);
        } catch (UploadException $e) {
            return redirect('/players/'.$id)->with('error', $e->getMessage());
        }

        if ($photo === null) {
            return redirect('/players/'.$id)->with('error', 'Please choose an image file first.');
        }

        $player->update(['photo' => $photo]);

        return redirect('/players/'.$id)->with('success', 'Profile photo updated.');
    }

    // ------------------------------------------------------------------
    // Physical metrics (fitness tests).
    // ------------------------------------------------------------------

    public function storeMetric(Request $request, string $id): RedirectResponse
    {
        $player = Player::find((int) $id);

        if ($player === null) {
            abort(404);
        }
        $this->requireTeam((int) $player->team_id);

        $input = $this->metricCollect($request);
        $errors = $this->metricRules($input);

        if ($errors !== []) {
            return redirect('/players/'.$id)->withInput($request->except('_token'))->withErrors($errors);
        }

        $input['player_id'] = (int) $id;
        $input['recorded_by'] = (int) ($this->user()?->id ?? 0);
        PlayerMetric::create($input);

        return redirect('/players/'.$id.'#physical')->with('success', 'Physical test recorded.');
    }

    public function destroyMetric(string $id): RedirectResponse
    {
        $metric = PlayerMetric::withPlayer((int) $id);

        if ($metric === null) {
            abort(404);
        }
        $this->requireTeam((int) $metric['team_id']);

        PlayerMetric::whereKey((int) $id)->delete();

        return redirect('/players/'.(int) $metric['player_id'].'#physical')->with('success', 'Physical test entry deleted.');
    }

    // ------------------------------------------------------------------
    // Mental / wellbeing assessments.
    // ------------------------------------------------------------------

    public function storeWelfare(Request $request, string $id): RedirectResponse
    {
        $player = Player::find((int) $id);

        if ($player === null) {
            abort(404);
        }
        $this->requireTeam((int) $player->team_id);

        $input = $this->welfareCollect($request);
        $errors = $this->welfareRules($input);

        if ($errors !== []) {
            return redirect('/players/'.$id)->withInput($request->except('_token'))->withErrors($errors);
        }

        $input['player_id'] = (int) $id;
        $input['assessed_by'] = (int) ($this->user()?->id ?? 0);
        PlayerWelfare::create($input);

        return redirect('/players/'.$id.'#mental')->with('success', 'Wellbeing assessment recorded.');
    }

    public function destroyWelfare(string $id): RedirectResponse
    {
        $entry = PlayerWelfare::withPlayer((int) $id);

        if ($entry === null) {
            abort(404);
        }
        $this->requireTeam((int) $entry['team_id']);

        PlayerWelfare::whereKey((int) $id)->delete();

        return redirect('/players/'.(int) $entry['player_id'].'#mental')->with('success', 'Assessment entry deleted.');
    }

    // ------------------------------------------------------------------
    // Validation / collection helpers.
    // ------------------------------------------------------------------

    /** @return array{create:bool,email:string,password:string} */
    private function loginCollect(Request $request): array
    {
        return [
            'create' => (string) $request->input('create_login', '') !== '',
            'email' => strtolower(trim((string) $request->input('login_email', ''))),
            'password' => (string) $request->input('login_password', ''),
        ];
    }

    /** @return array<string, array<int, string>> */
    private function loginRules(array $login): array
    {
        if (! $login['create']) {
            return [];
        }

        return Validator::validate($login, [
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|min:6|max:100',
        ]);
    }

    /** Create the 1:1 player login (role=player, mirrored team + player link). */
    private function createPlayerLogin(int $playerId, array $player, array $login): void
    {
        User::create([
            'name' => trim($player['first_name'].' '.$player['last_name']),
            'email' => $login['email'],
            'password' => $login['password'],
            'role' => 'player',
            'team_id' => (int) $player['team_id'],
            'player_id' => $playerId,
            'phone' => null,
        ]);
    }

    /** @return array<string, array<int, string>> */
    private function rules(array $input, ?int $ignoreId = null): array
    {
        $errors = Validator::validate($input, [
            'team_id' => 'required|integer|exists:teams,id',
            'first_name' => 'required|min:2|max:80',
            'last_name' => 'required|min:2|max:80',
            'jersey_number' => 'integer',
            'position' => 'in:'.implode(',', Player::POSITIONS),
            'date_of_birth' => 'date',
            'nationality' => 'max:80',
            'foot' => 'in:left,right,both',
            'goals' => 'integer',
            'assists' => 'integer',
            'appearances' => 'integer',
            'minutes_played' => 'integer',
            'yellow_cards' => 'integer',
            'red_cards' => 'integer',
            'rating' => 'numeric',
        ]);

        if (($input['jersey_number'] ?? null) !== null
            && ($input['jersey_number'] < 0 || $input['jersey_number'] > 99)) {
            $errors['jersey_number'][] = 'Jersey number must be between 0 and 99.';
        }

        if ($errors === [] && $input['rating'] !== null
            && ((float) $input['rating'] < 0 || (float) $input['rating'] > 10)) {
            $errors['rating'][] = 'Rating must be between 0 and 10.';
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function collect(Request $request): array
    {
        $nullable = static fn (string $key): ?string => trim((string) $request->input($key, '')) ?: null;
        $count = static fn (string $key): int => max(0, (int) $request->input($key, 0));
        $rating = trim((string) $request->input('rating', ''));

        return [
            'team_id' => (int) $request->input('team_id', 0),
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'jersey_number' => $nullable('jersey_number') !== null ? (int) $request->input('jersey_number') : null,
            'position' => $nullable('position'),
            'date_of_birth' => $nullable('date_of_birth'),
            'nationality' => $nullable('nationality'),
            'foot' => $nullable('foot'),
            'goals' => $count('goals'),
            'assists' => $count('assists'),
            'appearances' => $count('appearances'),
            'minutes_played' => $count('minutes_played'),
            'yellow_cards' => $count('yellow_cards'),
            'red_cards' => $count('red_cards'),
            'rating' => $rating === '' ? null : (is_numeric($rating) ? (float) $rating : $rating),
        ];
    }

    /** @return array<string, array<int, string>> */
    private function metricRules(array $input): array
    {
        $errors = Validator::validate($input, [
            'test_date' => 'required|date',
            'fitness' => 'required|in:'.implode(',', PlayerMetric::FITNESS),
            'height_cm' => 'numeric',
            'weight_kg' => 'numeric',
            'top_speed' => 'numeric',
            'stamina' => 'numeric',
            'strength' => 'numeric',
            'agility' => 'numeric',
            'body_fat' => 'numeric',
            'notes' => 'max:1000',
        ]);

        $ranges = [
            'height_cm' => [100, 250, 'Height'],
            'weight_kg' => [25, 250, 'Weight'],
            'top_speed' => [0, 60, 'Top speed'],
            'stamina' => [0, 100, 'Stamina'],
            'strength' => [0, 100, 'Strength'],
            'agility' => [0, 100, 'Agility'],
            'body_fat' => [0, 100, 'Body fat'],
        ];

        foreach ($ranges as $field => [$min, $max, $label]) {
            if ($errors === [] && $input[$field] !== null
                && ((float) $input[$field] < $min || (float) $input[$field] > $max)) {
                $errors[$field][] = sprintf('%s must be between %s and %s.', $label, $min, $max);
            }
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function metricCollect(Request $request): array
    {
        $num = static function (string $key) use ($request): mixed {
            $raw = trim((string) $request->input($key, ''));

            return $raw === '' ? null : (is_numeric($raw) ? (float) $raw : $raw);
        };

        return [
            'test_date' => trim((string) $request->input('test_date', '')),
            'height_cm' => $num('height_cm'),
            'weight_kg' => $num('weight_kg'),
            'top_speed' => $num('top_speed'),
            'stamina' => $num('stamina'),
            'strength' => $num('strength'),
            'agility' => $num('agility'),
            'body_fat' => $num('body_fat'),
            'fitness' => (string) $request->input('fitness', 'good'),
            'notes' => trim((string) $request->input('notes', '')) ?: null,
        ];
    }

    /** @return array<string, array<int, string>> */
    private function welfareRules(array $input): array
    {
        $errors = Validator::validate($input, [
            'assessment_date' => 'required|date',
            'confidence' => 'required|integer',
            'focus' => 'required|integer',
            'motivation' => 'required|integer',
            'stress' => 'required|integer',
            'wellbeing' => 'required|in:'.implode(',', PlayerWelfare::WELLBEING),
            'notes' => 'max:1000',
        ]);

        foreach (['confidence', 'focus', 'motivation', 'stress'] as $field) {
            if ($errors === [] && isset($input[$field])
                && ((int) $input[$field] < 1 || (int) $input[$field] > 10)) {
                $errors[$field][] = ucfirst(str_replace('_', ' ', $field)).' must be between 1 and 10.';
            }
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function welfareCollect(Request $request): array
    {
        return [
            'assessment_date' => trim((string) $request->input('assessment_date', '')),
            'confidence' => (int) $request->input('confidence', 0),
            'focus' => (int) $request->input('focus', 0),
            'motivation' => (int) $request->input('motivation', 0),
            'stress' => (int) $request->input('stress', 0),
            'wellbeing' => (string) $request->input('wellbeing', 'good'),
            'notes' => trim((string) $request->input('notes', '')) ?: null,
        ];
    }

    /**
     * Flash input + errors and bounce back. The player form needs the login
     * fields re-populated too, so they are folded into the old input.
     */
    private function failedValidation(Request $request, array $errors, string $fallback, array $input = [], array $login = []): RedirectResponse
    {
        $old = array_merge($input, [
            'create_login' => $login['create'] ?? false,
            'login_email' => $login['email'] ?? '',
        ]);

        if (empty($old['create_login']) && ! $request->boolean('create_login')) {
            unset($old['create_login']);
        }

        return redirect($fallback)->withInput($old)->withErrors($errors);
    }
}
