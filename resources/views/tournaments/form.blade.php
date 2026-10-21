@php
/**
 * Standalone tournament edit form (create happens via the modal on the index page).
 * @var array $tournament
 */
$action = url('/tournaments/' . $tournament['id']);
$value = static fn (string $key, string $default = '') => old($key, $tournament[$key] ?? $default);
@endphp
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="post" action="{!! e($action) !!}">
                    {!! csrf_field() !!}

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input id="name" name="name" class="form-control {!! error('name') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('name')) !!}" required>
                            @if(error('name'))<div class="invalid-feedback">{!! e(error('name')) !!}</div>@endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="sport">Sport <span class="text-danger">*</span></label>
                            <input id="sport" name="sport" class="form-control {!! error('sport') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('sport', 'Football')) !!}" required>
                            @if(error('sport'))<div class="invalid-feedback">{!! e(error('sport')) !!}</div>@endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="start_date">Start date</label>
                            <input id="start_date" name="start_date" type="date"
                                   class="form-control {!! error('start_date') ? 'is-invalid' : '' !!}"
                                   value="{!! e(substr((string) $value('start_date'), 0, 10)) !!}">
                            @if(error('start_date'))<div class="invalid-feedback">{!! e(error('start_date')) !!}</div>@endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="end_date">End date</label>
                            <input id="end_date" name="end_date" type="date"
                                   class="form-control {!! error('end_date') ? 'is-invalid' : '' !!}"
                                   value="{!! e(substr((string) $value('end_date'), 0, 10)) !!}">
                            @if(error('end_date'))<div class="invalid-feedback">{!! e(error('end_date')) !!}</div>@endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="status">Status</label>
                            <select id="status" name="status" class="form-select {!! error('status') ? 'is-invalid' : '' !!}">
                                @foreach(['upcoming', 'ongoing', 'completed'] as $status)
                                    <option value="{!! $status !!}" {!! $value('status', 'upcoming') === $status ? 'selected' : '' !!}>
                                        {!! ucfirst($status) !!}
                                    </option>
                                @endforeach
                            </select>
                            @if(error('status'))<div class="invalid-feedback">{!! e(error('status')) !!}</div>@endif
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" rows="4"
                                      class="form-control {!! error('description') ? 'is-invalid' : '' !!}">{!! e((string) $value('description')) !!}</textarea>
                            @if(error('description'))<div class="invalid-feedback">{!! e(error('description')) !!}</div>@endif
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{!! e(url('/tournaments')) !!}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check-lg me-1"></i>Save changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
