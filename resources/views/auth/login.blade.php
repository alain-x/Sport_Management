@php
/**
 * Standalone login page (no sidebar layout).
 * @var string $appName
 */
$flashes = flash();
$emailError = error('email');
$passwordError = error('password');
// The controller reports a bad sign-in on the email field; surface it as a
// prominent banner so a failed attempt is impossible to miss.
$loginError = $emailError;
$fieldError = $passwordError ?? $emailError;
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
    <div class="row justify-content-center align-items-center min-vh-100 py-4">
        <div class="col-md-6 col-lg-5 col-xl-4">
            <div class="text-center mb-4 text-white">
                <span class="login-brand-icon"><i class="bi bi-trophy-fill"></i></span>
                <h1 class="h4 mt-3 mb-0 fw-semibold">{!! e($appName) !!}</h1>
                <p class="opacity-75 mb-0">Sport management made simple</p>
            </div>

            <div class="card login-card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <h2 class="h5 fw-semibold mb-1">Welcome back</h2>
                    <p class="text-muted small mb-4">Sign in to manage your teams, players and fixtures.</p>

                    @foreach($flashes as $type => $message)
                        <div class="alert alert-{!! $type === 'error' ? 'danger' : e($type) !!} py-2 small" role="alert">
                            {!! e($message) !!}
                        </div>
                    @endforeach

                    @if($loginError)
                        <div class="alert alert-danger d-flex align-items-start gap-2 login-error" role="alert" aria-live="assertive">
                            <i class="bi bi-exclamation-triangle-fill fs-5 lh-1"></i>
                            <div>
                                <strong class="d-block">Sign in failed</strong>
                                <span class="small">{{ e($loginError) }}</span>
                            </div>
                        </div>
                    @endif

                    <form method="post" action="{!! e(url('/login')) !!}" novalidate>
                        {!! csrf_field() !!}

                        <div class="mb-3">
                            <label class="form-label fw-medium" for="email">Email address</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input id="email" type="email" name="email" autocomplete="email"
                                       class="form-control {!! $loginError ? 'is-invalid' : '' !!}"
                                       value="{!! e(old('email', '')) !!}" placeholder="you@example.com" autofocus>
                                @if($loginError)
                                    <div class="invalid-feedback">{{ e($loginError) }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-medium" for="password">Password</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input id="password" type="password" name="password" autocomplete="current-password"
                                       class="form-control {!! $passwordError ? 'is-invalid' : '' !!}"
                                       placeholder="Enter your password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword"
                                        tabindex="-1" aria-label="Show password">
                                    <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                </button>
                                @if($passwordError)
                                    <div class="invalid-feedback">{{ e($passwordError) }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                                <label class="form-check-label small" for="remember">Remember me</label>
                            </div>
                        </div>

                        <button class="btn btn-primary btn-lg w-100 fw-medium" type="submit">
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

<script>
    (function () {
        var toggle = document.getElementById('togglePassword');
        var input = document.getElementById('password');
        var icon = document.getElementById('togglePasswordIcon');
        if (!toggle || !input) return;
        toggle.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            if (icon) {
                icon.classList.toggle('bi-eye', !show);
                icon.classList.toggle('bi-eye-slash', show);
            }
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });

        // Shake the card the moment a failed attempt renders, to draw the eye.
        var card = document.querySelector('.login-card');
        if (card && document.querySelector('.login-error')) {
            card.classList.add('login-shake');
            setTimeout(function () { card.classList.remove('login-shake'); }, 500);
        }
    })();
</script>
</body>
</html>
