@php
/**
 * Standalone login page (no sidebar layout).
 * @var string $appName
 */
$flashes = flash();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in &middot; {!! e($appName) !!}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{!! e(url('/assets/css/style.css')) !!}" rel="stylesheet">
</head>
<body class="login-page">
<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-5 col-lg-4">
            <div class="text-center mb-4 text-white">
                <i class="bi bi-trophy-fill display-3"></i>
                <h1 class="h4 mt-2 mb-0">{!! e($appName) !!}</h1>
                <p class="opacity-75 mb-0">Sport management made simple</p>
            </div>

            <div class="card shadow border-0">
                <div class="card-body p-4">
                    <h2 class="h6 text-uppercase text-muted mb-3">Administrator sign in</h2>

                    @foreach($flashes as $type => $message)
                        <div class="alert alert-{!! $type === 'error' ? 'danger' : e($type) !!} py-2 small">
                            {!! e($message) !!}
                        </div>
                    @endforeach

                    <form method="post" action="{!! e(url('/login')) !!}">
                        {!! csrf_field() !!}

                        <div class="mb-3">
                            <label class="form-label" for="email">Email address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input id="email" type="email" name="email" autocomplete="email"
                                       class="form-control {!! error('email') ? 'is-invalid' : '' !!}"
                                       value="{!! e(old('email', '')) !!}" placeholder="email">
                                @if(error('email'))
                                    <div class="invalid-feedback">{!! e(error('email')) !!}</div>
                                @endif
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input id="password" type="password" name="password" autocomplete="current-password"
                                       class="form-control {!! error('password') ? 'is-invalid' : '' !!}"
                                       placeholder="">
                                @if(error('password'))
                                    <div class="invalid-feedback">{!! e(error('password')) !!}</div>
                                @endif
                            </div>
                        </div>

                        <button class="btn btn-primary w-100" type="submit">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Sign in
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-white-50 small mt-3 mb-0">
                Demo account: <code class="text-white">admin@sport.test</code> / <code class="text-white">password</code>
            </p>
        </div>
    </div>
</div>
</body>
</html>
