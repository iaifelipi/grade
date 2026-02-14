@extends('layouts.guest')

@section('title','Admin Login • Grade')

@section('content')
<div class="mb-3">
    <h2 class="h4 mb-1">Acesso administrativo</h2>
    <p class="text-muted mb-0">Entre com seu usuário de sistema.</p>
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

<form method="POST" action="{{ route('admin.login.store') }}" class="grade-auth-compact-form">
    @csrf

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
        <a class="grade-auth-link btn btn-link p-0" href="{{ route('password.request') }}">Esqueci a senha</a>
    </div>

    <div class="grade-auth-compact-footer">
        <a href="{{ route('home') }}" class="btn btn-outline-secondary">Voltar</a>
        <button class="btn btn-dark">Entrar</button>
    </div>
</form>
@endsection
