<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Support\Upload;
use App\Support\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // ------------------------------------------------------------------
    // Administrator account management (/users).
    // ------------------------------------------------------------------

    public function index(Request $request)
    {
        $this->requireAdmin();

        $search = trim((string) $request->query('q', ''));
        $role = in_array($request->query('role', ''), ['admin', 'manager', 'player'], true)
            ? (string) $request->query('role')
            : '';
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 10;

        $total = User::countFiltered($search, $role);
        $users = User::paginateAll($perPage, ($page - 1) * $perPage, $search, $role);

        $query = array_filter(['q' => $search, 'role' => $role], static fn ($v) => $v !== '');

        return $this->view('users.index', [
            'pageTitle' => 'Users',
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'pagination' => paginate($total, $perPage, $page, '/users', $query),
        ]);
    }

    public function create()
    {
        $this->requireAdmin();

        return $this->view('users.form', [
            'pageTitle' => 'Add User',
            'user' => null,
            'teams' => Team::options(),
            'staffMode' => false,
            'returnTo' => '/users',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireAdmin();

        $input = $this->collect($request);
        $errors = $this->validateUser($input, null, true);

        if ($errors !== []) {
            return $this->failedValidation($request, $errors, '/users/create');
        }

        $data = $input;
        if ($data['role'] === 'admin') {
            $data['team_id'] = null;
        }
        unset($data['password_confirmation']);

        User::create($data);

        return redirect('/users')->with('success', 'User account created.');
    }

    public function edit(string $id)
    {
        $this->requireAdmin();

        $user = User::findArray((int) $id);
        if ($user === null) {
            abort(404);
        }

        return $this->view('users.form', [
            'pageTitle' => 'Edit User',
            'user' => $user,
            'teams' => Team::options(),
            'staffMode' => false,
            'returnTo' => '/users',
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->requireAdmin();

        $user = User::findArray((int) $id);
        if ($user === null) {
            abort(404);
        }

        // Player logins are managed via the player record: only basic details are editable here.
        if (($user['role'] ?? '') === 'player') {
            return $this->updatePlayerAccount($request, $user, $id);
        }

        $input = $this->collect($request);
        $errors = $this->validateUser($input, (int) $id, false);

        $isSelf = (int) $id === (int) ($this->user()?->id ?? 0);
        if ($isSelf && $input['role'] !== $user['role']) {
            $errors['role'][] = 'You cannot change your own role.';
        }

        if ($errors !== []) {
            return $this->failedValidation($request, $errors, '/users/'.$id.'/edit');
        }

        $data = [
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'role' => $input['role'],
            'team_id' => $input['role'] === 'admin' ? null : $input['team_id'],
        ];
        if ($input['password'] !== '') {
            $data['password'] = $input['password'];
        }

        User::whereKey((int) $id)->update($data);

        return redirect('/users')->with('success', 'User account updated.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->requireAdmin();

        $user = User::findArray((int) $id);
        if ($user === null) {
            abort(404);
        }

        if ((int) $id === (int) ($this->user()?->id ?? 0)) {
            return redirect('/users')->with('error', 'You cannot delete your own account.');
        }

        Upload::delete($user['photo'] ?? null);
        User::whereKey((int) $id)->delete();

        return redirect('/users')->with('success', 'User account deleted.');
    }

    // ------------------------------------------------------------------
    // Team staff management (scoped: administrators + the team itself).
    // ------------------------------------------------------------------

    public function editStaff(string $id)
    {
        $user = User::findArray((int) $id);
        if ($user === null) {
            abort(404);
        }
        $this->guardStaffAccess($user);

        return $this->view('users.form', [
            'pageTitle' => 'Edit Staff Member',
            'user' => $user,
            'teams' => Team::options(),
            'staffMode' => true,
            'returnTo' => '/teams/'.(int) $user['team_id'].'/staff',
        ]);
    }

    public function updateStaff(Request $request, string $id): RedirectResponse
    {
        $user = User::findArray((int) $id);
        if ($user === null) {
            abort(404);
        }
        $this->guardStaffAccess($user);

        $input = $this->collect($request);
        $input['role'] = $user['role'];      // Staff mode cannot change role/team.
        $input['team_id'] = $user['team_id'];

        $errors = $this->validateUser($input, (int) $id, false);
        if ($errors !== []) {
            return $this->failedValidation($request, $errors, '/staff/'.$id.'/edit');
        }

        $data = [
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
        ];
        if ($input['password'] !== '') {
            $data['password'] = $input['password'];
        }

        User::whereKey((int) $id)->update($data);

        return redirect('/teams/'.(int) $user['team_id'].'/staff')->with('success', 'Staff member updated.');
    }

    public function destroyStaff(string $id): RedirectResponse
    {
        $user = User::findArray((int) $id);
        if ($user === null) {
            abort(404);
        }
        $this->guardStaffAccess($user);

        if ((int) $id === (int) ($this->user()?->id ?? 0)) {
            return redirect('/teams/'.(int) $user['team_id'].'/staff')->with('error', 'You cannot delete your own account.');
        }

        Upload::delete($user['photo'] ?? null);
        $teamId = (int) $user['team_id'];
        User::whereKey((int) $id)->delete();

        return redirect('/teams/'.$teamId.'/staff')->with('success', 'Staff member removed.');
    }

    /** Administrators pass; managers may only touch manager accounts of their own team. */
    private function guardStaffAccess(array $target): void
    {
        if (is_admin()) {
            return;
        }

        $own = auth_team_id();
        if ($own === null) {
            abort(403, 'Your account is not linked to a team.');
        }
        if (($target['role'] ?? '') !== 'manager' || (int) ($target['team_id'] ?? 0) !== $own) {
            abort(403, 'This account belongs to another team.');
        }
    }

    /** Player logins: only basic details are editable; role/team/player stay fixed. */
    private function updatePlayerAccount(Request $request, array $user, string $id): RedirectResponse
    {
        $input = [
            'name' => trim((string) $request->input('name', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
            'phone' => trim((string) $request->input('phone', '')) ?: null,
        ];

        $errors = Validator::validate($input, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|max:190|unique:users,email,'.$id,
            'password' => 'max:100',
            'phone' => 'max:30',
        ]);

        if ($input['password'] !== '' && $input['password'] !== $input['password_confirmation']) {
            $errors['password_confirmation'][] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            return redirect('/users/'.$id.'/edit')
                ->withInput($request->except(['_token', 'password', 'password_confirmation']))
                ->withErrors($errors);
        }

        $data = [
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
        ];
        if ($input['password'] !== '') {
            $data['password'] = $input['password'];
        }

        User::whereKey((int) $id)->update($data);

        return redirect('/users')->with('success', 'Player login updated.');
    }

    /** @return array<string, array<int, string>> */
    private function validateUser(array $input, ?int $ignoreId, bool $passwordRequired): array
    {
        $errors = Validator::validate($input, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|max:190|unique:users,email'.($ignoreId !== null ? ','.$ignoreId : ''),
            'password' => ($passwordRequired ? 'required|' : '').'max:100',
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
    private function collect(Request $request): array
    {
        $teamId = trim((string) $request->input('team_id', ''));

        return [
            'name' => trim((string) $request->input('name', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
            'phone' => trim((string) $request->input('phone', '')) ?: null,
            'role' => (string) $request->input('role', 'manager'),
            'team_id' => $teamId === '' ? null : (int) $teamId,
        ];
    }

    private function failedValidation(Request $request, array $errors, string $fallback): RedirectResponse
    {
        return redirect($fallback)
            ->withInput($request->except(['_token', 'password', 'password_confirmation']))
            ->withErrors($errors);
    }
}
