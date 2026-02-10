@extends('layouts.guest')

@section('title','Confirmar senha • PIXIP')

@section('content')
<div class="pixip-auth-header">
    <div class="pixip-auth-icon">
        <i class="bi bi-lock-fill"></i>
    </div>
    <div class="pixip-auth-title">Confirmar senha</div>
    <div class="pixip-auth-subtitle">Área segura, confirme sua senha para continuar</div>
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

<form method="POST" action="{{ route('password.confirm') }}">
    @csrf

    <div class="mb-3">
        <label class="form-label">Senha atual</label>
        <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password">
    </div>

    <button class="btn btn-primary w-100 mt-2">
        Confirmar acesso
    </button>
</form>
@endsection
