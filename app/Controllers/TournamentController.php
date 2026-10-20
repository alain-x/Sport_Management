<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Validator;
use App\Models\Tournament;

class TournamentController extends Controller
{
    public function index(): void
    {
        $this->render('tournaments/index', [
            'pageTitle' => 'Tournaments',
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();

        $input = $this->collect();
        $errors = $this->rules($input);

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/tournaments');
        }

        $id = Tournament::create($input);
        flash('success', 'Tournament created successfully.');
        redirect('/tournaments/' . $id);
    }

    public function show(string $id): void
    {
        $tournament = Tournament::withMatches((int) $id);

        if ($tournament === null) {
            $this->notFound();

            return;
        }

        $this->render('tournaments/show', [
            'pageTitle' => $tournament['name'],
            'tournament' => $tournament,
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireAdmin();

        $tournament = Tournament::find((int) $id);

        if ($tournament === null) {
            $this->notFound();

            return;
        }

        $this->render('tournaments/form', [
            'pageTitle' => 'Edit Tournament',
            'tournament' => $tournament,
        ]);
    }

    public function update(string $id): void
    {
        $this->requireAdmin();

        if (Tournament::find((int) $id) === null) {
            $this->notFound();

            return;
        }

        $input = $this->collect();
        $errors = $this->rules($input);

        if ($errors !== []) {
            $this->failedValidation($input, $errors, '/tournaments/' . $id . '/edit');
        }

        Tournament::update((int) $id, $input);
        flash('success', 'Tournament updated successfully.');
        redirect('/tournaments/' . $id);
    }

    public function destroy(string $id): void
    {
        $this->requireAdmin();

        if (Tournament::find((int) $id) === null) {
            $this->notFound();

            return;
        }

        Tournament::delete((int) $id);
        flash('success', 'Tournament deleted. Its matches are now unassigned.');
        redirect('/tournaments');
    }

    /** @return array<string, string> */
    private function rules(array $input): array
    {
        $errors = Validator::validate($input, [
            'name' => 'required|min:3|max:150',
            'sport' => 'required|max:60',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => 'required|in:' . implode(',', Tournament::STATUSES),
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
    private function collect(): array
    {
        $trimOrNull = static fn (string $key): ?string => trim((string) ($_POST[$key] ?? '')) ?: null;

        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'sport' => trim((string) ($_POST['sport'] ?? 'Football')),
            'start_date' => $trimOrNull('start_date'),
            'end_date' => $trimOrNull('end_date'),
            'status' => (string) ($_POST['status'] ?? 'upcoming'),
            'description' => $trimOrNull('description'),
        ];
    }
}
