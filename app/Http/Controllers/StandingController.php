<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class StandingController extends Controller
{
    public function index(): RedirectResponse|View
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
            return $this->view('standings.index', [
                'pageTitle' => 'Standings',
                'tournaments' => [],
                'tournament' => null,
                'rows' => [],
            ]);
        }

        return redirect('/standings/'.$current['id']);
    }

    public function show(string $id)
    {
        $tournament = Tournament::findArray((int) $id);

        if ($tournament === null) {
            abort(404);
        }

        return $this->view('standings.show', [
            'pageTitle' => 'Standings - '.$tournament['name'],
            'tournament' => $tournament,
            'tournaments' => Tournament::allWithCounts('start_date DESC'),
            'rows' => MatchModel::standings((int) $id),
            'finishedCount' => count(MatchModel::finishedForTournament((int) $id)),
        ]);
    }
}
