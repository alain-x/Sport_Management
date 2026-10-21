<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Support\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index()
    {
        return $this->view('tournaments.index', [
            'pageTitle' => 'Tournaments',
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireAdmin();

        $input = $this->collect($request);
        $errors = $this->rules($input);

        if ($errors !== []) {
            return $this->failedValidation($request, $errors, '/tournaments');
        }

        $tournament = Tournament::create($input);

        return redirect('/tournaments/'.$tournament->id)->with('success', 'Tournament created successfully.');
    }

    public function show(string $id)
    {
        $tournament = Tournament::withMatches((int) $id);

        if ($tournament === null) {
            abort(404);
        }

        return $this->view('tournaments.show', [
            'pageTitle' => $tournament['name'],
            'tournament' => $tournament,
        ]);
    }

    public function edit(string $id)
    {
        $this->requireAdmin();

        $tournament = Tournament::findArray((int) $id);

        if ($tournament === null) {
            abort(404);
        }

        return $this->view('tournaments.form', [
            'pageTitle' => 'Edit Tournament',
            'tournament' => $tournament,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->requireAdmin();

        $tournament = Tournament::find((int) $id);
        if ($tournament === null) {
            abort(404);
        }

        $input = $this->collect($request);
        $errors = $this->rules($input);

        if ($errors !== []) {
            return $this->failedValidation($request, $errors, '/tournaments/'.$id.'/edit');
        }

        $tournament->update($input);

        return redirect('/tournaments/'.$id)->with('success', 'Tournament updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->requireAdmin();

        $tournament = Tournament::find((int) $id);
        if ($tournament === null) {
            abort(404);
        }

        $tournament->delete();

        return redirect('/tournaments')->with('success', 'Tournament deleted. Its matches are now unassigned.');
    }

    /** @return array<string, array<int, string>> */
    private function rules(array $input): array
    {
        $errors = Validator::validate($input, [
            'name' => 'required|min:3|max:150',
            'sport' => 'required|max:60',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => 'required|in:'.implode(',', Tournament::STATUSES),
            'description' => 'max:2000',
        ]);

        if ($errors === []
            && $input['start_date'] !== null
            && $input['end_date'] !== null
            && $input['end_date'] < $input['start_date']) {
            $errors['end_date'][] = 'End date must be after the start date.';
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function collect(Request $request): array
    {
        $trimOrNull = static fn (string $key): ?string => trim((string) $request->input($key, '')) ?: null;

        return [
            'name' => trim((string) $request->input('name', '')),
            'sport' => trim((string) $request->input('sport', 'Football')),
            'start_date' => $trimOrNull('start_date'),
            'end_date' => $trimOrNull('end_date'),
            'status' => (string) $request->input('status', 'upcoming'),
            'description' => $trimOrNull('description'),
        ];
    }

    private function failedValidation(Request $request, array $errors, string $fallback): RedirectResponse
    {
        return redirect($fallback)->withInput($request->except('_token'))->withErrors($errors);
    }
}
