@extends('layouts.guest')

@section('title','Verificar email • PIXIP')

@section('content')
<div class="pixip-auth-header">
    <div class="pixip-auth-icon">
        <i class="bi bi-envelope-check-fill"></i>
    </div>
    <div class="pixip-auth-title">Verifique seu email</div>
    <div class="pixip-auth-subtitle">Enviamos um link de confirmação para sua caixa de entrada</div>
</div>

@if (session('status') == 'verification-link-sent')
    <div class="alert alert-success">
        Um novo link de verificação foi enviado.
    </div>
@endif

<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button class="btn btn-primary w-100">
        Reenviar email de verificação
    </button>
</form>

<div class="pixip-auth-divider">ou</div>

<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" class="btn btn-outline-secondary w-100">
        Sair da conta
    </button>
</form>
@endsection
