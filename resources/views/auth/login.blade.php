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
            </div>

            <div class="card login-card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <h2 class="h5 fw-semibold mb-1">Welcome back</h2>

                    @foreach($flashes as $type => $message)
                        <div class="alert alert-{!! $type === 'error' ? 'danger' : e($type) !!} py-2 small" role="alert">
                            {!! e($message) !!}
                        </div>
                    @endforeach

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

@if($loginError)
    <div class="login-toast-wrap" id="loginToastWrap" role="alert" aria-live="assertive">
        <div class="login-toast" id="loginToast">
            <i class="bi bi-exclamation-triangle-fill login-toast-icon"></i>
            <div class="login-toast-body">
                <strong class="d-block">Sign in failed</strong>
                <span class="small">{{ e($loginError) }}</span>
            </div>
            <button type="button" class="btn-close btn-close-white ms-2" id="loginToastClose" aria-label="Close"></button>
            <span class="login-toast-progress" id="loginToastProgress"></span>
        </div>
    </div>
@endif

<script>
    (function () {
        var toggle = document.getElementById('togglePassword');
        var input = document.getElementById('password');
        var icon = document.getElementById('togglePasswordIcon');
        if (toggle && input) {
            toggle.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                if (icon) {
                    icon.classList.toggle('bi-eye', !show);
                    icon.classList.toggle('bi-eye-slash', show);
                }
                toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        }

        // Login error pops as a toast for 3 seconds, then slides away.
        var wrap = document.getElementById('loginToastWrap');
        var toast = document.getElementById('loginToast');
        var progress = document.getElementById('loginToastProgress');
        var close = document.getElementById('loginToastClose');
        if (wrap && toast) {
            var HIDE_MS = 3000;
            var t1, t2;

            var dismiss = function () {
                clearTimeout(t1);
                clearTimeout(t2);
                toast.classList.add('login-toast-hide');
                setTimeout(function () { wrap.remove(); }, 350);
            };

            // restart the countdown bar animation
            if (progress) {
                progress.style.animation = 'none';
                void progress.offsetWidth;
                progress.style.animation = 'login-toast-progress ' + HIDE_MS + 'ms linear forwards';
            }

            requestAnimationFrame(function () { toast.classList.add('login-toast-in'); });
            t1 = setTimeout(dismiss, HIDE_MS);
            if (close) { close.addEventListener('click', dismiss); }
            // Pause on hover so the reader can still read it.
            toast.addEventListener('mouseenter', function () { clearTimeout(t1); });
            toast.addEventListener('mouseleave', function () { t1 = setTimeout(dismiss, 1200); });
        }

        // Shake the card the moment a failed attempt renders, to draw the eye.
        var card = document.querySelector('.login-card');
        if (card && wrap) {
            card.classList.add('login-shake');
            setTimeout(function () { card.classList.remove('login-shake'); }, 500);
        }
    })();
</script>
</body>
</html>
