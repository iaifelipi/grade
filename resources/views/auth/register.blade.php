@extends('layouts.guest')

@section('title','Admin Register • Grade')

@section('content')
<div class="mb-3">
    <h2 class="h4 mb-1">Cadastro administrativo</h2>
    <p class="text-muted mb-0">Crie um usuário de sistema (web guard).</p>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.register.store') }}" class="grade-auth-compact-form">
    @csrf

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

    <div class="grade-auth-compact-footer">
        <a href="{{ route('admin.login') }}" class="btn btn-outline-secondary">Entrar</a>
        <button class="btn btn-dark">Registrar</button>
    </div>
</form>
@endsection
