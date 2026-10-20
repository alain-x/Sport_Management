<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Upload;
use App\Core\Validator;
use App\Models\Team;
use App\Models\User;

class UserController extends Controller
{
    // ------------------------------------------------------------------
    // Administrator account management (/users).
    // ------------------------------------------------------------------

    public function index(): void
    {
        $this->requireAdmin();

        $search = trim((string) ($_GET['q'] ?? ''));
        $role = in_array($_GET['role'] ?? '', ['admin', 'manager'], true) ? (string) $_GET['role'] : '';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 10;

        $total = User::countFiltered($search, $role);
        $users = User::paginateAll($perPage, ($page - 1) * $perPage, $search, $role);

        $query = array_filter(['q' => $search, 'role' => $role], static fn ($v) => $v !== '');

        $this->render('users/index', [
            'pageTitle' => 'Users',
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'pagination' => paginate($total, $perPage, $page, '/users', $query),
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();

        $this->render('users/form', [
            'pageTitle' => 'Add User',
            'user' => null,
            'teams' => Team::options(),
            'staffMode' => false,
            'returnTo' => '/users',
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();

        $input = $this->collect();
        $errors = $this->validateUser($input, null, true);

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/users/create');
        }

        $data = $input;
        $data['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        if ($data['role'] === 'admin') {
            $data['team_id'] = null;
        }
        unset($data['password_confirmation']);

        User::create($data);
        flash('success', 'User account created.');
        redirect('/users');
    }

    public function edit(string $id): void
    {
        $this->requireAdmin();

        $user = User::find((int) $id);
        if ($user === null) {
            $this->notFound();

            return;
        }

        $this->render('users/form', [
            'pageTitle' => 'Edit User',
            'user' => $user,
            'teams' => Team::options(),
            'staffMode' => false,
            'returnTo' => '/users',
        ]);
    }

    public function update(string $id): void
    {
        $this->requireAdmin();

        $user = User::find((int) $id);
        if ($user === null) {
            $this->notFound();

            return;
        }

        $input = $this->collect();
        $errors = $this->validateUser($input, (int) $id, false);

        $isSelf = (int) $id === (int) (auth_user()['id'] ?? 0);
        if ($isSelf && $input['role'] !== $user['role']) {
            $errors['role'][] = 'You cannot change your own role.';
        }

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/users/' . $id . '/edit');
        }

        $data = [
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'role' => $input['role'],
            'team_id' => $input['role'] === 'admin' ? null : $input['team_id'],
        ];
        if ($input['password'] !== '') {
            $data['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }

        User::update((int) $id, $data);
        flash('success', 'User account updated.');
        redirect('/users');
    }

    public function destroy(string $id): void
    {
        $this->requireAdmin();

        $user = User::find((int) $id);
        if ($user === null) {
            $this->notFound();

            return;
        }

        if ((int) $id === (int) (auth_user()['id'] ?? 0)) {
            flash('error', 'You cannot delete your own account.');
            redirect('/users');
        }

        Upload::delete($user['photo'] ?? null);
        User::delete((int) $id);
        flash('success', 'User account deleted.');
        redirect('/users');
    }

    // ------------------------------------------------------------------
    // Team staff management (scoped: administrators + the team itself).
    // ------------------------------------------------------------------

    public function editStaff(string $id): void
    {
        $user = User::find((int) $id);
        if ($user === null) {
            $this->notFound();

            return;
        }
        $this->guardStaffAccess($user);

        $this->render('users/form', [
            'pageTitle' => 'Edit Staff Member',
            'user' => $user,
            'teams' => Team::options(),
            'staffMode' => true,
            'returnTo' => '/teams/' . (int) $user['team_id'] . '/staff',
        ]);
    }

    public function updateStaff(string $id): void
    {
        $user = User::find((int) $id);
        if ($user === null) {
            $this->notFound();

            return;
        }
        $this->guardStaffAccess($user);

        $input = $this->collect();
        $input['role'] = $user['role'];      // Staff mode cannot change role/team.
        $input['team_id'] = $user['team_id'];

        $errors = $this->validateUser($input, (int) $id, false);
        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/staff/' . $id . '/edit');
        }

        $data = [
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
        ];
        if ($input['password'] !== '') {
            $data['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }

        User::update((int) $id, $data);
        flash('success', 'Staff member updated.');

        $back = '/teams/' . (int) $user['team_id'] . '/staff';
        redirect($back);
    }

    public function destroyStaff(string $id): void
    {
        $user = User::find((int) $id);
        if ($user === null) {
            $this->notFound();

            return;
        }
        $this->guardStaffAccess($user);

        if ((int) $id === (int) (auth_user()['id'] ?? 0)) {
            flash('error', 'You cannot delete your own account.');
            redirect('/teams/' . (int) $user['team_id'] . '/staff');
        }

        Upload::delete($user['photo'] ?? null);
        User::delete((int) $id);
        flash('success', 'Staff member removed.');
        redirect('/teams/' . (int) $user['team_id'] . '/staff');
    }

    /** Administrators pass; managers may only touch manager accounts of their own team. */
    private function guardStaffAccess(array $target): void
    {
        if (is_admin()) {
            return;
        }

        $own = auth_team_id();
        if ($own === null) {
            $this->abort403('Your account is not linked to a team.');
        }
        if (($target['role'] ?? '') !== 'manager' || (int) ($target['team_id'] ?? 0) !== $own) {
            $this->abort403('This account belongs to another team.');
        }
    }

    /** @return array<string, array<int, string>> */
    private function validateUser(array $input, ?int $ignoreId, bool $passwordRequired): array
    {
        $errors = Validator::validate($input, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|max:190|unique:users,email' . ($ignoreId !== null ? ',' . $ignoreId : ''),
            'password' => ($passwordRequired ? 'required|' : '') . 'max:100',
            'phone' => 'max:30',
            'role' => 'required|in:admin,manager',
            'team_id' => 'integer|exists:teams,id',
        ]);

        if ($errors === [] && $input['role'] === 'manager' && empty($input['team_id'])) {
            $errors['team_id'][] = 'Choose the team this staff member belongs to.';
        }

        if ($input['password'] !== '' && $input['password'] !== ($input['password_confirmation'] ?? '')) {
            $errors['password_confirmation'][] = 'Passwords do not match.';
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function collect(): array
    {
        $teamId = trim((string) ($_POST['team_id'] ?? ''));

        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirmation' => (string) ($_POST['password_confirmation'] ?? ''),
            'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'role' => (string) ($_POST['role'] ?? 'manager'),
            'team_id' => $teamId === '' ? null : (int) $teamId,
        ];
    }
}
