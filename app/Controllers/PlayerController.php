<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Upload;
use App\Core\Validator;
use App\Models\Player;
use App\Models\PlayerMetric;
use App\Models\PlayerWelfare;
use App\Models\Team;

class PlayerController extends Controller
{
    public function index(): void
    {
        $scopeTeam = $this->scopeTeamId();
        $search = trim((string) ($_GET['q'] ?? ''));
        $requestedTeam = ($_GET['team_id'] ?? '') !== '' ? (int) $_GET['team_id'] : null;
        $teamId = $scopeTeam ?? $requestedTeam;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 10;

        $total = Player::countFiltered($search, $teamId);
        $players = Player::paginate($perPage, ($page - 1) * $perPage, $search, $teamId);

        $query = array_filter(['q' => $search, 'team_id' => $teamId], static fn ($v) => $v !== null && $v !== '');

        $this->render('players/index', [
            'pageTitle' => 'Players',
            'players' => $players,
            'search' => $search,
            'teamId' => $teamId,
            'teams' => Team::options(),
            'scopeTeam' => $scopeTeam !== null ? Team::find($scopeTeam) : null,
            'pagination' => paginate($total, $perPage, $page, '/players', $query),
        ]);
    }

    public function create(): void
    {
        $scopeTeam = $this->scopeTeamId();

        $this->render('players/form', [
            'pageTitle' => 'Add Player',
            'player' => null,
            'teams' => Team::options(),
            'scopeTeam' => $scopeTeam !== null ? Team::find($scopeTeam) : null,
        ]);
    }

    public function store(): void
    {
        $scopeTeam = $this->scopeTeamId();

        $input = $this->collect();
        if ($scopeTeam !== null) {
            $input['team_id'] = $scopeTeam;
        }
        $errors = $this->rules($input);

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/players/create');
        }

        [$photo, $photoError] = Upload::image($_FILES['photo'] ?? null, 'players');
        if ($photoError !== null) {
            $this->failedValidation($input, ['photo' => [$photoError]], '/players/create');
        }
        if ($photo !== null) {
            $input['photo'] = $photo;
        }

        $id = Player::create($input);
        flash('success', 'Player added successfully.');
        redirect('/players?page=1&q=' . urlencode($input['last_name']));
    }

    /** Player detail page: profile, statistics, physical and mental tabs. */
    public function show(string $id): void
    {
        $player = Player::withTeam((int) $id);

        if ($player === null) {
            $this->notFound();

            return;
        }

        $canManage = is_admin() || auth_team_id() === (int) $player['team_id'];

        $this->render('players/show', [
            'pageTitle' => $player['first_name'] . ' ' . $player['last_name'],
            'player' => $player,
            'canManage' => $canManage,
            'metrics' => $canManage ? PlayerMetric::forPlayer((int) $id) : [],
            'latestMetric' => $canManage ? PlayerMetric::latestFor((int) $id) : null,
            'welfare' => $canManage ? PlayerWelfare::forPlayer((int) $id) : [],
            'latestWelfare' => $canManage ? PlayerWelfare::latestFor((int) $id) : null,
        ]);
    }

    public function edit(string $id): void
    {
        $player = Player::withTeam((int) $id);

        if ($player === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $player['team_id']);

        $this->render('players/form', [
            'pageTitle' => 'Edit Player',
            'player' => $player,
            'teams' => Team::options(),
            'scopeTeam' => is_admin() ? null : Team::find((int) $player['team_id']),
        ]);
    }

    public function update(string $id): void
    {
        $player = Player::find((int) $id);

        if ($player === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $player['team_id']);

        $input = $this->collect();
        $errors = $this->rules($input, (int) $id);

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/players/' . $id . '/edit');
        }

        [$photo, $photoError] = Upload::image($_FILES['photo'] ?? null, 'players', $player['photo'] ?? null);
        if ($photoError !== null) {
            $this->failedValidation($input, ['photo' => [$photoError]], '/players/' . $id . '/edit');
        }
        if ($photo !== null) {
            $input['photo'] = $photo;
        }

        Player::update((int) $id, $input);
        flash('success', 'Player updated successfully.');
        redirect('/players/' . $id);
    }

    public function destroy(string $id): void
    {
        $player = Player::find((int) $id);

        if ($player === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $player['team_id']);

        Upload::delete($player['photo'] ?? null);
        Player::delete((int) $id);
        flash('success', 'Player deleted.');
        redirect('/players');
    }

    /** Season statistics panel on the detail page. */
    public function updateStats(string $id): void
    {
        $player = Player::find((int) $id);

        if ($player === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $player['team_id']);

        $int = static function (string $key, int $default = 0): int {
            $raw = trim((string) ($_POST[$key] ?? ''));

            return $raw === '' ? $default : max(0, (int) $raw);
        };
        $ratingRaw = trim((string) ($_POST['rating'] ?? ''));

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
        if ($errors === [] && $input['rating'] !== null && !is_numeric((string) $input['rating'])) {
            $errors['rating'][] = 'Rating must be a number.';
        }

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/players/' . $id);
        }

        Player::update((int) $id, $input);
        flash('success', 'Statistics updated.');
        redirect('/players/' . $id . '#statistics');
    }

    /** Photo upload from the detail page. */
    public function uploadPhoto(string $id): void
    {
        $player = Player::find((int) $id);

        if ($player === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $player['team_id']);

        [$photo, $photoError] = Upload::image($_FILES['photo'] ?? null, 'players', $player['photo'] ?? null);

        if ($photoError !== null) {
            flash('error', $photoError);
        } elseif ($photo === null) {
            flash('error', 'Please choose an image file first.');
        } else {
            Player::update((int) $id, ['photo' => $photo]);
            flash('success', 'Profile photo updated.');
        }

        redirect('/players/' . $id);
    }

    // ------------------------------------------------------------------
    // Physical metrics (fitness tests).
    // ------------------------------------------------------------------

    public function storeMetric(string $id): void
    {
        $player = Player::find((int) $id);

        if ($player === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $player['team_id']);

        $input = $this->metricCollect();
        $errors = $this->metricRules($input);

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/players/' . $id);
        }

        $input['player_id'] = (int) $id;
        $input['recorded_by'] = (int) (auth_user()['id'] ?? 0);
        PlayerMetric::create($input);

        flash('success', 'Physical test recorded.');
        redirect('/players/' . $id . '#physical');
    }

    public function destroyMetric(string $metricId): void
    {
        $metric = PlayerMetric::withPlayer((int) $metricId);

        if ($metric === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $metric['team_id']);

        PlayerMetric::delete((int) $metricId);
        flash('success', 'Physical test entry deleted.');
        redirect('/players/' . (int) $metric['player_id'] . '#physical');
    }

    // ------------------------------------------------------------------
    // Mental / wellbeing assessments.
    // ------------------------------------------------------------------

    public function storeWelfare(string $id): void
    {
        $player = Player::find((int) $id);

        if ($player === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $player['team_id']);

        $input = $this->welfareCollect();
        $errors = $this->welfareRules($input);

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/players/' . $id);
        }

        $input['player_id'] = (int) $id;
        $input['assessed_by'] = (int) (auth_user()['id'] ?? 0);
        PlayerWelfare::create($input);

        flash('success', 'Wellbeing assessment recorded.');
        redirect('/players/' . $id . '#mental');
    }

    public function destroyWelfare(string $welfareId): void
    {
        $entry = PlayerWelfare::withPlayer((int) $welfareId);

        if ($entry === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $entry['team_id']);

        PlayerWelfare::delete((int) $welfareId);
        flash('success', 'Assessment entry deleted.');
        redirect('/players/' . (int) $entry['player_id'] . '#mental');
    }

    // ------------------------------------------------------------------
    // Validation / collection helpers.
    // ------------------------------------------------------------------

    /** @return array<string, string> */
    private function rules(array $input, ?int $ignoreId = null): array
    {
        $errors = Validator::validate($input, [
            'team_id' => 'required|integer|exists:teams,id',
            'first_name' => 'required|min:2|max:80',
            'last_name' => 'required|min:2|max:80',
            'jersey_number' => 'integer',
            'position' => 'in:' . implode(',', Player::POSITIONS),
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
    private function collect(): array
    {
        $nullable = static fn (string $key): ?string => trim((string) ($_POST[$key] ?? '')) ?: null;
        $count = static fn (string $key): int => max(0, (int) ($_POST[$key] ?? 0));
        $rating = trim((string) ($_POST['rating'] ?? ''));

        return [
            'team_id' => (int) ($_POST['team_id'] ?? 0),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'jersey_number' => $nullable('jersey_number') !== null ? (int) $_POST['jersey_number'] : null,
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

    /** @return array<string, string> */
    private function metricRules(array $input): array
    {
        $errors = Validator::validate($input, [
            'test_date' => 'required|date',
            'fitness' => 'required|in:' . implode(',', PlayerMetric::FITNESS),
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
    private function metricCollect(): array
    {
        $num = static function (string $key): ?float {
            $raw = trim((string) ($_POST[$key] ?? ''));

            return $raw === '' ? null : (is_numeric($raw) ? (float) $raw : $raw);
        };

        return [
            'test_date' => trim((string) ($_POST['test_date'] ?? '')),
            'height_cm' => $num('height_cm'),
            'weight_kg' => $num('weight_kg'),
            'top_speed' => $num('top_speed'),
            'stamina' => $num('stamina'),
            'strength' => $num('strength'),
            'agility' => $num('agility'),
            'body_fat' => $num('body_fat'),
            'fitness' => (string) ($_POST['fitness'] ?? 'good'),
            'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
        ];
    }

    /** @return array<string, string> */
    private function welfareRules(array $input): array
    {
        $errors = Validator::validate($input, [
            'assessment_date' => 'required|date',
            'confidence' => 'required|integer',
            'focus' => 'required|integer',
            'motivation' => 'required|integer',
            'stress' => 'required|integer',
            'wellbeing' => 'required|in:' . implode(',', PlayerWelfare::WELLBEING),
            'notes' => 'max:1000',
        ]);

        foreach (['confidence', 'focus', 'motivation', 'stress'] as $field) {
            if ($errors === [] && isset($input[$field])
                && ((int) $input[$field] < 1 || (int) $input[$field] > 10)) {
                $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' must be between 1 and 10.';
            }
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function welfareCollect(): array
    {
        return [
            'assessment_date' => trim((string) ($_POST['assessment_date'] ?? '')),
            'confidence' => (int) ($_POST['confidence'] ?? 0),
            'focus' => (int) ($_POST['focus'] ?? 0),
            'motivation' => (int) ($_POST['motivation'] ?? 0),
            'stress' => (int) ($_POST['stress'] ?? 0),
            'wellbeing' => (string) ($_POST['wellbeing'] ?? 'good'),
            'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
        ];
    }
}
