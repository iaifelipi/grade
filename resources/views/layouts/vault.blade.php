@extends('layouts.app')

@section('topbar-tools')
    @include('partials.vault-topbar')
    @hasSection('vault-topbar-extra')
        @yield('vault-topbar-extra')
    @endif
@endsection
