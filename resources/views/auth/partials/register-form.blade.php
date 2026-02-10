<div class="pixip-auth-header">
    <div class="pixip-auth-icon">
        <i class="bi bi-person-plus-fill"></i>
    </div>
    <div class="pixip-auth-title">Crie sua conta</div>
    <div class="pixip-auth-subtitle">Leva menos de um minuto</div>
</div>

<form method="POST" action="{{ route('register') }}" data-auth-form="register">
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
        <label class="form-label">Nome completo</label>
        <input name="name" value="{{ old('name') }}" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input name="email" type="email" value="{{ old('email') }}" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Senha</label>
        <input name="password" type="password" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Confirmar senha</label>
        <input name="password_confirmation" type="password" class="form-control" required>
    </div>

    <button class="btn btn-primary w-100 mt-3" data-auth-submit>
        Criar conta
    </button>
</form>

<div class="pixip-auth-note">
    Já tem conta?
    <button type="button" class="pixip-auth-link btn btn-link p-0 align-baseline" data-auth-switch="login">Entrar</button>
</div>
