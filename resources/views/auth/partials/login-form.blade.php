<form method="POST" action="{{ route('login') }}" data-auth-form="login" class="grade-auth-compact-form">
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
            <span class="grade-auth-field-kicker">E-mail ou usuário</span>
            <input type="text" name="login" value="{{ old('login') }}" class="grade-auth-field-input" required autofocus autocomplete="username">
        </label>

        <label class="grade-auth-field-box">
            <span class="grade-auth-field-kicker">Senha</span>
            <input type="password" name="password" class="grade-auth-field-input" required>
        </label>
    </div>

    <div class="grade-auth-compact-actions">
        <label class="form-check">
            <input type="checkbox" name="remember" class="form-check-input">
            <span class="form-check-label">Lembrar</span>
        </label>
        <button type="button" class="grade-auth-link btn btn-link p-0" data-auth-switch="forgot">
            Esqueci a senha
        </button>
    </div>

    <div class="grade-auth-compact-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-dark" data-auth-submit>Entrar</button>
    </div>
</form>
