@extends('layouts.guest')

@section('title','Accept Tenant Invite')

@section('content')
<div class="mb-3">
    <h2 class="h4 mb-1">Accept tenant invitation</h2>
    <p class="text-muted mb-0">Complete your profile and password to activate your tenant account.</p>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <div><strong>Email:</strong> {{ $invitation->email }}</div>
        <div><strong>Expires:</strong> {{ optional($invitation->expires_at)->format('Y-m-d H:i') ?? '—' }}</div>
    </div>
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

<form method="POST" action="{{ route('tenant.invite.accept.submit', ['token' => $token]) }}" class="row g-3">
    @csrf

    <div class="col-md-6">
        <label class="form-label">First name</label>
        <input type="text" name="first_name" class="form-control" maxlength="120" value="{{ old('first_name', $invitation->first_name) }}" required>
    </div>

    <div class="col-md-6">
        <label class="form-label">Last name</label>
        <input type="text" name="last_name" class="form-control" maxlength="120" value="{{ old('last_name', $invitation->last_name) }}">
    </div>

    <div class="col-md-6">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <div class="col-md-6">
        <label class="form-label">Confirm password</label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>

    <div class="col-12 d-flex justify-content-end gap-2">
        <a href="{{ route('tenant.login') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-dark">Activate account</button>
    </div>
</form>
@endsection
