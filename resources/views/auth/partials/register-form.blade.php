@php($canRegister = \Illuminate\Support\Facades\Route::has('register'))

<form
    method="POST"
    action="{{ $canRegister ? route('register') : '#' }}"
    data-auth-form="register"
    class="grade-auth-compact-form"
    @if(!$canRegister) onsubmit="return false" @endif
>
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
            <span class="grade-auth-field-kicker">Nome completo</span>
            <input name="name" value="{{ old('name') }}" class="grade-auth-field-input" required>
        </label>

        <label class="grade-auth-field-box">
            <span class="grade-auth-field-kicker">E-mail</span>
            <input name="email" type="email" value="{{ old('email') }}" class="grade-auth-field-input" required>
        </label>

        <label class="grade-auth-field-box">
            <span class="grade-auth-field-kicker">Senha</span>
            <input name="password" type="password" class="grade-auth-field-input" required>
        </label>

        <label class="grade-auth-field-box">
            <span class="grade-auth-field-kicker">Confirmar senha</span>
            <input name="password_confirmation" type="password" class="grade-auth-field-input" required>
        </label>
    </div>

    @if(!$canRegister)
        <div class="alert alert-warning py-2 mt-2 mb-0">
            Cadastro desabilitado neste ambiente.
        </div>
    @endif

    <div class="grade-auth-compact-footer">
        <button type="button" class="btn btn-outline-secondary" data-auth-switch="login">Entrar</button>
        <button class="btn btn-dark" data-auth-submit @disabled(!$canRegister)>Registrar</button>
    </div>
</form>
