@extends('layouts.guest')

@section('title','Tenant Login • Grade')

@section('content')
<div class="mb-3">
    <h2 class="h4 mb-1">Tenant Access</h2>
    <p class="text-muted mb-0">Use your tenant workspace credentials.</p>
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

<form method="POST" action="{{ route('tenant.login.store') }}" class="grade-auth-compact-form">
    @csrf

    <div class="grade-auth-fields">
        <label class="grade-auth-field-box">
            <span class="grade-auth-field-kicker">Tenant (slug or UUID)</span>
            <input type="text" name="tenant" value="{{ old('tenant') }}" class="grade-auth-field-input" required autofocus>
        </label>

        <label class="grade-auth-field-box">
            <span class="grade-auth-field-kicker">Email or Username</span>
            <input type="text" name="login" value="{{ old('login') }}" class="grade-auth-field-input" required>
        </label>

        <label class="grade-auth-field-box">
            <span class="grade-auth-field-kicker">Password</span>
            <input type="password" name="password" class="grade-auth-field-input" required>
        </label>
    </div>

    <div class="grade-auth-compact-actions">
        <label class="form-check">
            <input type="checkbox" name="remember" class="form-check-input">
            <span class="form-check-label">Remember me</span>
        </label>
    </div>

    <div class="grade-auth-compact-footer">
        <a href="{{ route('home') }}" class="btn btn-outline-secondary">Back</a>
        <button class="btn btn-dark">Enter tenant area</button>
    </div>
</form>
@endsection
