<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\MatchModel;
use App\Models\Tournament;

class StandingController extends Controller
{
    public function index(): void
    {
        $tournaments = Tournament::allWithCounts('start_date DESC');
        $current = null;

        foreach ($tournaments as $tournament) {
            if ($tournament['status'] === 'ongoing') {
                $current = $tournament;
                break;
            }
        }

        if ($current === null && $tournaments !== []) {
            $current = $tournaments[0];
        }

        if ($current === null) {
            $this->render('standings/index', [
                'pageTitle' => 'Standings',
                'tournaments' => [],
                'tournament' => null,
                'rows' => [],
            ]);

            return;
        }

        redirect('/standings/' . $current['id']);
    }

    public function show(string $id): void
    {
        $tournament = Tournament::find((int) $id);

        if ($tournament === null) {
            $this->notFound();

            return;
        }

        $this->render('standings/show', [
            'pageTitle' => 'Standings - ' . $tournament['name'],
            'tournament' => $tournament,
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
            'rows' => MatchModel::standings((int) $id),
            'finishedCount' => count(MatchModel::finishedForTournament((int) $id)),
        ]);
    }
}
