@if (session('status'))
    <div class="alert alert-success">
        {{ session('status') }}
    </div>
@endif

<form method="POST" action="{{ route('password.email') }}" data-auth-form="forgot" class="grade-auth-compact-form">
    @csrf
    <div class="alert alert-danger d-none" data-auth-errors></div>
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grade-auth-fields">
        <label class="grade-auth-field-box">
            <span class="grade-auth-field-kicker">E-mail</span>
            <input type="email" name="email" value="{{ old('email') }}" class="grade-auth-field-input" required autofocus>
        </label>
    </div>

    <p class="grade-auth-compact-help">Vamos enviar um link para redefinir sua senha.</p>

    <div class="grade-auth-compact-footer">
        <a href="{{ route('admin.login') }}" class="btn btn-outline-secondary">Voltar</a>
        <button class="btn btn-dark" data-auth-submit>Enviar link</button>
    </div>
</form>
