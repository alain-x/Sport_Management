<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Support\Upload;
use App\Support\UploadException;
use App\Support\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 8;

        $total = Team::countSearch($search);
        $teams = Team::paginate($perPage, ($page - 1) * $perPage, $search);

        return $this->view('teams.index', [
            'pageTitle' => 'Teams',
            'teams' => $teams,
            'search' => $search,
            'pagination' => paginate($total, $perPage, $page, '/teams', $search !== '' ? ['q' => $search] : []),
        ]);
    }

    public function create()
    {
        $this->requireAdmin();

        return $this->view('teams.form', [
            'pageTitle' => 'Add Team',
            'team' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireAdmin();

        $input = $this->collect($request);
        $errors = $this->rules($input, null);

        if ($errors !== []) {
            return $this->failedValidation($request, $errors, '/teams/create');
        }

        try {
            if ($request->hasFile('logo')) {
                $input['logo'] = Upload::image($request->file('logo'), 'teams');
            }
        } catch (UploadException $e) {
            return $this->failedValidation($request, ['logo' => [$e->getMessage()]], '/teams/create');
        }

        $id = Team::create($input)->id;

        return redirect('/teams/'.$id)->with('success', 'Team created successfully.');
    }

    public function show(string $id)
    {
        $team = Team::withPlayers((int) $id);

        if ($team === null) {
            abort(404);
        }

        $canManage = is_staff() && (is_admin() || auth_team_id() === (int) $team['id']);

        return $this->view('teams.show', [
            'pageTitle' => $team['name'],
            'team' => $team,
            'canManage' => $canManage,
            'staffCount' => User::countForTeam((int) $team['id']),
        ]);
    }

    public function edit(string $id)
    {
        $team = Team::findArray((int) $id);

        if ($team === null) {
            abort(404);
        }
        $this->requireTeam((int) $team['id']);

        return $this->view('teams.form', [
            'pageTitle' => 'Edit Team',
            'team' => $team,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $team = Team::find((int) $id);

        if ($team === null) {
            abort(404);
        }
        $this->requireTeam((int) $team->id);

        $input = $this->collect($request);
        $errors = $this->rules($input, (int) $id);

        if ($errors !== []) {
            return $this->failedValidation($request, $errors, '/teams/'.$id.'/edit');
        }

        try {
            if ($request->hasFile('logo')) {
                $input['logo'] = Upload::image($request->file('logo'), 'teams', $team->logo);
            }
        } catch (UploadException $e) {
            return $this->failedValidation($request, ['logo' => [$e->getMessage()]], '/teams/'.$id.'/edit');
        }

        $team->update($input);

        return redirect('/teams/'.$id)->with('success', 'Team updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->requireAdmin();

        $team = Team::find((int) $id);

        if ($team === null) {
            abort(404);
        }

        Upload::delete($team->logo);
        $team->delete();

        return redirect('/teams')->with('success', 'Team and its related records deleted.');
    }

    // ------------------------------------------------------------------
    // Team staff (/teams/{id}/staff).
    // ------------------------------------------------------------------

    public function staff(string $id)
    {
        $team = Team::withStaff((int) $id);

        if ($team === null) {
            abort(404);
        }
        $this->requireTeam((int) $team['id']);

        return $this->view('teams.staff', [
            'pageTitle' => $team['name'].' - Staff',
            'team' => $team,
        ]);
    }

    public function storeStaff(Request $request, string $id): RedirectResponse
    {
        $team = Team::find((int) $id);

        if ($team === null) {
            abort(404);
        }
        $this->requireTeam((int) $team->id);

        $input = [
            'name' => trim((string) $request->input('name', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
            'phone' => trim((string) $request->input('phone', '')) ?: null,
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
        ];

        $errors = Validator::validate($input, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|min:8|max:100',
            'phone' => 'max:30',
        ]);

        if ($input['password'] !== '' && $input['password'] !== $input['password_confirmation']) {
            $errors['password_confirmation'][] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            return $this->failedValidation($request, $errors, '/teams/'.$id.'/staff');
        }

        User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => 'manager',
            'team_id' => (int) $team->id,
            'phone' => $input['phone'],
        ]);

        return redirect('/teams/'.$id.'/staff')->with('success', 'Staff member added to '.$team->name.'.');
    }

    /** @return array<string, mixed> */
    private function collect(Request $request): array
    {
        $founded = trim((string) $request->input('founded_year', ''));

        return [
            'name' => trim((string) $request->input('name', '')),
            'code' => strtoupper(trim((string) $request->input('code', ''))),
            'coach' => trim((string) $request->input('coach', '')) ?: null,
            'city' => trim((string) $request->input('city', '')) ?: null,
            'founded_year' => $founded === '' ? null : (int) $founded,
            'description' => trim((string) $request->input('description', '')) ?: null,
        ];
    }

    /** @return array<string, array<int, string>> */
    private function rules(array $input, ?int $ignoreId): array
    {
        $errors = Validator::validate($input, [
            'name' => 'required|min:3|max:120|unique:teams,name'.($ignoreId !== null ? ','.$ignoreId : ''),
            'code' => 'required|max:8',
            'coach' => 'max:120',
            'city' => 'max:100',
            'founded_year' => 'integer',
            'description' => 'max:2000',
        ]);

        if ($errors === [] && $input['founded_year'] !== null
            && ($input['founded_year'] < 1850 || $input['founded_year'] > (int) date('Y'))) {
            $errors['founded_year'][] = 'Founded year must be between 1850 and '.date('Y').'.';
        }

        return $errors;
    }

    private function failedValidation(Request $request, array $errors, string $fallback): RedirectResponse
    {
        return redirect($fallback)
            ->withInput($request->except(['_token', 'logo']))
            ->withErrors($errors);
    }
}
