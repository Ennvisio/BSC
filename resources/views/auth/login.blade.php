<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<link rel="apple-touch-icon" href="{{url('/images/logo.png')}}">
	<link rel="shortcut icon" href="{{url('/images/logo.png')}}">
	<title>Sign in — Bangladesh Shipping Corporation</title>

	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
	<link href="https://use.fontawesome.com/releases/v5.0.6/css/all.css" rel="stylesheet">
	<link rel="stylesheet" href="{{url('/css/login.css')}}?v={{ file_exists(base_path('css/login.css')) ? filemtime(base_path('css/login.css')) : 1 }}">
</head>
<body>
	<div class="login-shell">
		<div class="login-brand">
			<div class="login-brand-inner">
				<div class="login-brand-mark"><img src="{{url('/images/logo.png')}}" alt="Logo"></div>
				<span class="bn">বাংলাদেশ শিপিং কর্পোরেশন</span>
				<h1>Bangladesh Shipping Corporation</h1>
				<p>Ship Repair Department — fleet requisitions, surveys, certificates, and vessel records in one place.</p>
				<span class="tag"><i class="fas fa-ship mr-1"></i> Ship Repair Dept.</span>
			</div>
		</div>

		<div class="login-form-side">
			<div class="login-card">
				<h2>Welcome back</h2>
				<div class="sub">Sign in to continue to your dashboard.</div>

				<form method="POST" action="{{ route('login') }}" novalidate>
					@csrf

					<div class="login-field">
						<label for="email">Email address</label>
						<div class="login-input-wrap">
							<i class="fas fa-envelope field-icon"></i>
							<input id="email" type="email" class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
								name="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus>
						</div>
						@if ($errors->has('email'))
						<div class="login-error">{{ $errors->first('email') }}</div>
						@endif
					</div>

					<div class="login-field">
						<label for="password">Password</label>
						<div class="login-input-wrap">
							<i class="fas fa-lock field-icon"></i>
							<input id="password" type="password" class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
								name="password" placeholder="••••••••" required>
							<button type="button" class="login-toggle-pw" id="togglePassword" tabindex="-1" aria-label="Show password">
								<i class="fas fa-eye"></i>
							</button>
						</div>
						@if ($errors->has('password'))
						<div class="login-error">{{ $errors->first('password') }}</div>
						@endif
					</div>

					<div class="login-row">
						<label class="login-check">
							<input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
							Remember me
						</label>
						@if (Route::has('password.request'))
						<a class="login-forgot" href="{{ route('password.request') }}">Forgot password?</a>
						@endif
					</div>

					<button type="submit" class="login-submit">Sign in</button>
				</form>

				<div class="login-foot">&copy; {{ date('Y') }} Bangladesh Shipping Corporation</div>
			</div>
		</div>
	</div>

	<script>
		document.getElementById('togglePassword').addEventListener('click', function () {
			var input = document.getElementById('password');
			var icon = this.querySelector('i');
			var isHidden = input.type === 'password';
			input.type = isHidden ? 'text' : 'password';
			icon.classList.toggle('fa-eye', !isHidden);
			icon.classList.toggle('fa-eye-slash', isHidden);
			this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
		});
	</script>
</body>
</html>
