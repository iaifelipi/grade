@extends('layouts.app')

@section('title','Perfil & Preferências')
@section('page-title','Perfil')

@section('content')
<div class="container grade-profile">
    <div class="grade-profile-hero">
        <div class="grade-profile-identity">
            <div class="grade-profile-avatar">
                <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
            </div>
            <div>
                <h2 class="grade-profile-title">
                    {{ trim(explode(' ', $user->name)[0] ?? $user->name) }}
                </h2>
                <div class="grade-profile-sub">
                    <span>{{ $user->email }}</span>
                    <span class="grade-profile-dot"></span>
                    <button type="button" class="grade-uuid-chip" data-slug="{{ $conta->slug ?? '' }}">
                        Usuário: {{ $conta->slug ?? '—' }}
                    </button>
                </div>
                <div class="grade-profile-plan">
                    Plano: <strong>{{ $conta->plan ?? 'free' }}</strong>
                </div>
            </div>
        </div>
        <div class="grade-profile-status">
            @if(session('status') === 'profile-updated')
                <div class="alert alert-success mb-0">Dados pessoais atualizados.</div>
            @elseif(session('status') === 'password-updated')
                <div class="alert alert-success mb-0">Senha atualizada.</div>
            @elseif(session('status') === 'preferences-updated')
                <div class="alert alert-success mb-0">Preferências salvas.</div>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <strong>Revise os campos:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grade-profile-grid">
        <aside class="grade-profile-nav">
            <div class="grade-profile-nav-card">
                <a href="#profile-personal" class="grade-profile-nav-link">Dados pessoais</a>
                <a href="#profile-security" class="grade-profile-nav-link">Segurança</a>
                <a href="#profile-preferences" class="grade-profile-nav-link">Preferências</a>
                <a href="#profile-location" class="grade-profile-nav-link">Localização</a>
                <a href="#profile-danger" class="grade-profile-nav-link text-danger">Zona de risco</a>
            </div>
            <div class="grade-profile-tip">
                <div class="grade-profile-tip-title">Dica rápida</div>
                <p>Use o modo escuro para reduzir fadiga visual em longas sessões.</p>
            </div>
        </aside>

        <div class="grade-profile-content">
            <section id="profile-personal" class="grade-profile-card">
                <div class="grade-profile-card-header">
                    <h3>Dados pessoais</h3>
                    <span>Atualize nome e email</span>
                </div>
                <form method="POST" action="{{ route('profile.update') }}" class="grade-profile-form">
                    @csrf
                    @method('PATCH')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome completo</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>
                    <div class="grade-profile-actions">
                        <button type="submit" class="btn btn-primary">Salvar alterações</button>
                    </div>
                </form>
            </section>

            <section id="profile-security" class="grade-profile-card">
                <div class="grade-profile-card-header">
                    <h3>Segurança</h3>
                    <span>Altere sua senha com segurança</span>
                </div>
                <form method="POST" action="{{ route('password.update') }}" class="grade-profile-form">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Senha atual</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nova senha</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confirmar nova senha</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>
                    </div>
                    <div class="grade-profile-actions">
                        <button type="submit" class="btn btn-primary">Atualizar senha</button>
                    </div>
                </form>
            </section>

            <section id="profile-preferences" class="grade-profile-card">
                <div class="grade-profile-card-header">
                    <h3>Preferências</h3>
                    <span>Idioma, fuso e tema</span>
                </div>
                <form method="POST" action="{{ route('profile.preferences') }}" class="grade-profile-form">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Idioma</label>
                            <select name="locale" class="form-select">
                                <option value="">Automático</option>
                                <option value="pt-BR" @selected(old('locale', $user->locale) === 'pt-BR')>Português (Brasil)</option>
                                <option value="en" @selected(old('locale', $user->locale) === 'en')>English</option>
                                <option value="es" @selected(old('locale', $user->locale) === 'es')>Español</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fuso horário</label>
                            <select name="timezone" class="form-select">
                                <option value="">Automático</option>
                                <option value="America/Sao_Paulo" @selected(old('timezone', $user->timezone) === 'America/Sao_Paulo')>America/Sao_Paulo</option>
                                <option value="America/New_York" @selected(old('timezone', $user->timezone) === 'America/New_York')>America/New_York</option>
                                <option value="Europe/Lisbon" @selected(old('timezone', $user->timezone) === 'Europe/Lisbon')>Europe/Lisbon</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tema</label>
                            <div class="grade-theme-toggle">
                                <label class="grade-theme-option">
                                    <input type="radio" name="theme" value="light" @checked(old('theme', $user->theme) === 'light')>
                                    <span>Claro</span>
                                </label>
                                <label class="grade-theme-option">
                                    <input type="radio" name="theme" value="dark" @checked(old('theme', $user->theme) === 'dark')>
                                    <span>Escuro</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Usuário (slug)</label>
                            <input name="tenant_slug"
                                   class="form-control"
                                   value="{{ old('tenant_slug', $conta->slug ?? '') }}"
                                   placeholder="ex: felipe-team">
                            <small class="text-muted">Use letras minúsculas e hífens.</small>
                        </div>
                    </div>
                    <div class="grade-profile-actions">
                        <button type="submit" class="btn btn-primary">Salvar preferências</button>
                    </div>
                </form>
            </section>

            <section id="profile-location" class="grade-profile-card">
                <div class="grade-profile-card-header">
                    <h3>Localização</h3>
                    <span>Informações para relatórios e filtros</span>
                </div>
                <form method="POST" action="{{ route('profile.preferences') }}" class="grade-profile-form">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="location_city" class="form-control" value="{{ old('location_city', $user->location_city) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <input type="text" name="location_state" class="form-control" value="{{ old('location_state', $user->location_state) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">País</label>
                            <input type="text" name="location_country" class="form-control" value="{{ old('location_country', $user->location_country) }}">
                        </div>
                    </div>
                    <div class="grade-profile-actions">
                        <button type="submit" class="btn btn-primary">Salvar localização</button>
                    </div>
                </form>
            </section>

            <section id="profile-danger" class="grade-profile-card grade-profile-danger">
                <div class="grade-profile-card-header">
                    <h3>Zona de risco</h3>
                    <span>Ações irreversíveis</span>
                </div>
                <form method="POST" action="{{ route('profile.destroy') }}" class="grade-profile-form">
                    @csrf
                    @method('DELETE')
                    <p class="text-muted mb-3">Excluir sua conta remove definitivamente seus dados pessoais.</p>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Confirme sua senha</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <button type="submit" class="btn btn-outline-danger">Excluir minha conta</button>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>
@endsection
