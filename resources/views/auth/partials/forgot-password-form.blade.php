<div class="pixip-auth-header">
    <div class="pixip-auth-icon">
        <i class="bi bi-shield-lock-fill"></i>
    </div>
    <div class="pixip-auth-title">Recuperar senha</div>
    <div class="pixip-auth-subtitle">Vamos enviar um link para redefinir sua senha</div>
</div>

@if (session('status'))
    <div class="alert alert-success">
        {{ session('status') }}
    </div>
@endif

<form method="POST" action="{{ route('password.email') }}" data-auth-form="forgot">
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

    <button class="btn btn-primary w-100 mt-3" data-auth-submit>
        Enviar link de recuperação
    </button>
</form>

<div class="pixip-auth-note">
    Lembrou da senha?
    <button type="button" class="pixip-auth-link btn btn-link p-0 align-baseline" data-auth-switch="login">Voltar ao login</button>
</div>
