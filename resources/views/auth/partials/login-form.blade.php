<div class="grade-auth-header">
    <div class="grade-auth-icon">
        <i class="bi bi-envelope-fill"></i>
    </div>
    <div class="grade-auth-title">Bem-vindo de volta</div>
    <div class="grade-auth-subtitle">Entre com seus dados para continuar</div>
</div>

<form method="POST" action="{{ route('login') }}" data-auth-form="login">
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

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
    </div>

    <div class="mb-3">
        <label class="form-label">Senha</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <div class="grade-auth-actions">
        <div class="form-check">
            <input type="checkbox" name="remember" class="form-check-input">
            <label class="form-check-label">Lembrar</label>
        </div>

        <button type="button" class="grade-auth-link btn btn-link p-0" data-auth-switch="forgot">
            Esqueci a senha
        </button>
    </div>

    <button class="btn btn-primary w-100 mt-4" data-auth-submit>
        Entrar
    </button>
</form>
