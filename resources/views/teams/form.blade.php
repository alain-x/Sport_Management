@php
/**
 * Create/edit team form (with logo upload).
 * @var array|null $team
 */
$isEdit = $team !== null;
$action = $isEdit ? url('/teams/' . $team['id']) : url('/teams');
$value = static fn (string $key, string $default = '') => old($key, $team[$key] ?? $default);
$logo = old('logo', $team['logo'] ?? null);
@endphp
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="post" action="{!! e($action) !!}" enctype="multipart/form-data">
                    {!! csrf_field() !!}

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="name">Team name <span class="text-danger">*</span></label>
                            <input id="name" name="name" class="form-control {!! error('name') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('name')) !!}" placeholder="e.g. Thunder FC" required>
                            @if(error('name'))<div class="invalid-feedback">{!! e(error('name')) !!}</div>@endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="code">Short code <span class="text-danger">*</span></label>
                            <input id="code" name="code" maxlength="8" class="form-control text-uppercase {!! error('code') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('code')) !!}" placeholder="THU" required>
                            @if(error('code'))<div class="invalid-feedback">{!! e(error('code')) !!}</div>@endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="coach">Head coach</label>
                            <input id="coach" name="coach" class="form-control {!! error('coach') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('coach')) !!}" placeholder="e.g. Alex Turner">
                            @if(error('coach'))<div class="invalid-feedback">{!! e(error('coach')) !!}</div>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="city">City</label>
                            <input id="city" name="city" class="form-control {!! error('city') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('city')) !!}" placeholder="e.g. Manchester">
                            @if(error('city'))<div class="invalid-feedback">{!! e(error('city')) !!}</div>@endif
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="founded_year">Founded year</label>
                            <input id="founded_year" name="founded_year" type="number" min="1850" max="{!! date('Y') !!}"
                                   class="form-control {!! error('founded_year') ? 'is-invalid' : '' !!}"
                                   value="{!! e((string) $value('founded_year')) !!}" placeholder="1920">
                            @if(error('founded_year'))<div class="invalid-feedback">{!! e(error('founded_year')) !!}</div>@endif
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="logo">Team logo <span class="text-muted small">(JPG/PNG/WebP, max 3 MB)</span></label>
                            <input id="logo" name="logo" type="file" accept="image/*"
                                   class="form-control {!! error('logo') ? 'is-invalid' : '' !!}">
                            @if(error('logo'))<div class="invalid-feedback">{!! e(error('logo')) !!}</div>@endif
                            @if($logo)
                                <div class="form-text">
                                    <img src="{!! e(upload_url($logo)) !!}" alt="" class="rounded me-1" style="width: 24px; height: 24px; object-fit: cover">
                                    Current logo shown &mdash; choose a file to replace it.
                                </div>
                            @endif
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="description">Club description</label>
                            <textarea id="description" name="description" rows="3"
                                      class="form-control {!! error('description') ? 'is-invalid' : '' !!}"
                                      placeholder="History, playing style, honours...">{!! e((string) $value('description')) !!}</textarea>
                            @if(error('description'))<div class="invalid-feedback">{!! e(error('description')) !!}</div>@endif
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{!! e($isEdit ? url('/teams/' . $team['id']) : url('/teams')) !!}" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check-lg me-1"></i>{!! $isEdit ? 'Save changes' : 'Create team' !!}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
