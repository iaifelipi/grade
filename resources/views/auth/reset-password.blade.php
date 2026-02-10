@extends('layouts.guest')

@section('title','Redefinir senha • PIXIP')

@section('content')
<div class="pixip-auth-header">
    <div class="pixip-auth-icon">
        <i class="bi bi-key-fill"></i>
    </div>
    <div class="pixip-auth-title">Redefinir senha</div>
    <div class="pixip-auth-subtitle">Crie uma nova senha segura</div>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('password.store') }}">
    @csrf

    <input type="hidden" name="token" value="{{ $request->route('token') }}">

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" class="form-control" required autofocus>
    </div>

    <div class="mb-3">
        <label class="form-label">Nova senha</label>
        <input id="password" type="password" name="password" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Confirmar senha</label>
        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required>
    </div>

    <button class="btn btn-primary w-100 mt-2">
        Redefinir senha
    </button>
</form>

<div class="pixip-auth-note">
    Já tem acesso? <a class="pixip-auth-link" href="{{ route('login') }}">Voltar ao login</a>
</div>
@endsection
