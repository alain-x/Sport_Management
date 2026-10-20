<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Upload;
use App\Core\Validator;
use App\Models\Team;
use App\Models\User;

class TeamController extends Controller
{
    public function index(): void
    {
        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 8;

        $total = Team::countSearch($search);
        $teams = Team::paginate($perPage, ($page - 1) * $perPage, $search);

        $this->render('teams/index', [
            'pageTitle' => 'Teams',
            'teams' => $teams,
            'search' => $search,
            'pagination' => paginate($total, $perPage, $page, '/teams', $search !== '' ? ['q' => $search] : []),
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();

        $this->render('teams/form', [
            'pageTitle' => 'Add Team',
            'team' => null,
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();

        $input = $this->collect();
        $errors = $this->rules($input, null);

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/teams/create');
        }

        [$logo, $logoError] = Upload::image($_FILES['logo'] ?? null, 'teams');
        if ($logoError !== null) {
            $this->failedValidation($input, ['logo' => [$logoError]], '/teams/create');
        }
        if ($logo !== null) {
            $input['logo'] = $logo;
        }

        $id = Team::create($input);
        flash('success', 'Team created successfully.');
        redirect('/teams/' . $id);
    }

    public function show(string $id): void
    {
        $team = Team::withPlayers((int) $id);

        if ($team === null) {
            $this->notFound();

            return;
        }

        $canManage = is_admin() || auth_team_id() === (int) $team['id'];

        $this->render('teams/show', [
            'pageTitle' => $team['name'],
            'team' => $team,
            'canManage' => $canManage,
            'staffCount' => User::countForTeam((int) $team['id']),
        ]);
    }

    public function edit(string $id): void
    {
        $team = Team::find((int) $id);

        if ($team === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $team['id']);

        $this->render('teams/form', [
            'pageTitle' => 'Edit Team',
            'team' => $team,
        ]);
    }

    public function update(string $id): void
    {
        $team = Team::find((int) $id);

        if ($team === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $team['id']);

        $input = $this->collect();
        $errors = $this->rules($input, (int) $id);

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/teams/' . $id . '/edit');
        }

        [$logo, $logoError] = Upload::image($_FILES['logo'] ?? null, 'teams', $team['logo'] ?? null);
        if ($logoError !== null) {
            $this->failedValidation($input, ['logo' => [$logoError]], '/teams/' . $id . '/edit');
        }
        if ($logo !== null) {
            $input['logo'] = $logo;
        }

        Team::update((int) $id, $input);
        flash('success', 'Team updated successfully.');
        redirect('/teams/' . $id);
    }

    public function destroy(string $id): void
    {
        $this->requireAdmin();

        $team = Team::find((int) $id);

        if ($team === null) {
            $this->notFound();

            return;
        }

        Upload::delete($team['logo'] ?? null);
        Team::delete((int) $id);
        flash('success', 'Team and its related records deleted.');
        redirect('/teams');
    }

    // ------------------------------------------------------------------
    // Team staff (/teams/{id}/staff).
    // ------------------------------------------------------------------

    public function staff(string $id): void
    {
        $team = Team::withStaff((int) $id);

        if ($team === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $team['id']);

        $this->render('teams/staff', [
            'pageTitle' => $team['name'] . ' - Staff',
            'team' => $team,
        ]);
    }

    public function storeStaff(string $id): void
    {
        $team = Team::find((int) $id);

        if ($team === null) {
            $this->notFound();

            return;
        }
        $this->requireTeam((int) $team['id']);

        $input = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirmation' => (string) ($_POST['password_confirmation'] ?? ''),
        ];

        $errors = Validator::validate($input, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|min:8|max:100',
            'phone' => 'max:30',
        ]);

        if ($errors === [] && $input['password'] !== $input['password_confirmation']) {
            $errors['password_confirmation'][] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/teams/' . $id . '/staff');
        }

        User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => password_hash($input['password'], PASSWORD_DEFAULT),
            'role' => 'manager',
            'team_id' => (int) $team['id'],
            'phone' => $input['phone'],
        ]);

        flash('success', 'Staff member added to ' . $team['name'] . '.');
        redirect('/teams/' . $id . '/staff');
    }

    /** @return array<string, string> */
    private function rules(array $input, ?int $ignoreId): array
    {
        $errors = Validator::validate($input, [
            'name' => 'required|min:3|max:120|unique:teams,name' . ($ignoreId !== null ? ',' . $ignoreId : ''),
            'code' => 'required|max:8',
            'coach' => 'max:120',
            'city' => 'max:100',
            'founded_year' => 'integer',
            'description' => 'max:2000',
        ]);

        if ($errors === [] && $input['founded_year'] !== null
            && ($input['founded_year'] < 1850 || $input['founded_year'] > date('Y'))) {
            $errors['founded_year'][] = 'Founded year must be between 1850 and ' . date('Y') . '.';
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function collect(): array
    {
        $founded = trim((string) ($_POST['founded_year'] ?? ''));

        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'code' => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'coach' => trim((string) ($_POST['coach'] ?? '')) ?: null,
            'city' => trim((string) ($_POST['city'] ?? '')) ?: null,
            'founded_year' => $founded === '' ? null : (int) $founded,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
        ];
    }
}
