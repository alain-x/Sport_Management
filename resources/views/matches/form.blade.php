@php
/**
 * Schedule / edit match form.
 * @var array|null $match
 * @var array $teams
 * @var array $tournaments
 */
$isEdit = $match !== null;
$action = $isEdit ? url('/matches/' . $match['id']) : url('/matches');
$value = static fn (string $key, string $default = '') => old($key, $match[$key] ?? $default);

$dateTimeValue = '';
if ($isEdit && !empty($match['match_date'])) {
    $ts = strtotime((string) $match['match_date']);
    $dateTimeValue = $ts !== false ? date('Y-m-d\TH:i', $ts) : '';
}
@endphp
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="post" action="{!! e($action) !!}">
                    {!! csrf_field() !!}

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="home_team_id">Home team <span class="text-danger">*</span></label>
                            <select id="home_team_id" name="home_team_id"
                                    class="form-select {!! error('home_team_id') ? 'is-invalid' : '' !!}" required>
                                <option value="">Select home team</option>
                                @foreach($teams as $team)
                                    <option value="{!! (int) $team['id'] !!}"
                                        {!! (int) $value('home_team_id') === (int) $team['id'] ? 'selected' : '' !!}>
                                        {!! e($team['name']) !!}
                                    </option>
                                @endforeach
                            </select>
                            @if(error('home_team_id'))<div class="invalid-feedback">{!! e(error('home_team_id')) !!}</div>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="away_team_id">Away team <span class="text-danger">*</span></label>
                            <select id="away_team_id" name="away_team_id"
                                    class="form-select {!! error('away_team_id') ? 'is-invalid' : '' !!}" required>
                                <option value="">Select away team</option>
                                @foreach($teams as $team)
                                    <option value="{!! (int) $team['id'] !!}"
                                        {!! (int) $value('away_team_id') === (int) $team['id'] ? 'selected' : '' !!}>
                                        {!! e($team['name']) !!}
                                    </option>
                                @endforeach
                            </select>
                            @if(error('away_team_id'))<div class="invalid-feedback">{!! e(error('away_team_id')) !!}</div>@endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="tournament_id">Tournament</label>
                            <select id="tournament_id" name="tournament_id"
                                    class="form-select {!! error('tournament_id') ? 'is-invalid' : '' !!}">
                                <option value="">Friendly (no tournament)</option>
                                @foreach($tournaments as $tournament)
                                    <option value="{!! (int) $tournament['id'] !!}"
                                        {!! (int) $value('tournament_id') === (int) $tournament['id'] ? 'selected' : '' !!}>
                                        {!! e($tournament['name']) !!}
                                    </option>
                                @endforeach
                            </select>
                            @if(error('tournament_id'))<div class="invalid-feedback">{!! e(error('tournament_id')) !!}</div>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="match_date">Date &amp; time</label>
                            <input id="match_date" name="match_date" type="datetime-local"
                                   class="form-control {!! error('match_date') ? 'is-invalid' : '' !!}"
                                   value="{!! e(old('match_date', $dateTimeValue)) !!}">
                            @if(error('match_date'))<div class="invalid-feedback">{!! e(error('match_date')) !!}</div>@endif
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="home_score">Home score</label>
                            <input id="home_score" name="home_score" type="number" min="0"
                                   class="form-control {!! error('home_score') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('home_score')) !!}" placeholder="2">
                            @if(error('home_score'))<div class="invalid-feedback">{!! e(error('home_score')) !!}</div>@endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="away_score">Away score</label>
                            <input id="away_score" name="away_score" type="number" min="0"
                                   class="form-control {!! error('away_score') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('away_score')) !!}" placeholder="1">
                            @if(error('away_score'))<div class="invalid-feedback">{!! e(error('away_score')) !!}</div>@endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="status">Status</label>
                            <select id="status" name="status" class="form-select">
                                @foreach(['scheduled', 'live', 'finished'] as $status)
                                    <option value="{!! $status !!}" {!! $value('status', 'scheduled') === $status ? 'selected' : '' !!}>
                                        {!! ucfirst($status) !!}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="venue">Venue</label>
                            <input id="venue" name="venue" class="form-control {!! error('venue') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('venue')) !!}" placeholder="e.g. National Arena">
                            @if(error('venue'))<div class="invalid-feedback">{!! e(error('venue')) !!}</div>@endif
                        </div>
                    </div>

                    <div class="form-text mt-2">
                        Scores are required when the status is <strong>Finished</strong>; the standings table is calculated
                        from finished matches.
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{!! e(url('/matches')) !!}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check-lg me-1"></i>{!! $isEdit ? 'Save changes' : 'Schedule match' !!}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
