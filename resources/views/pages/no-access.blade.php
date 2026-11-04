@extends('layouts.master', ['activeNav' => $activeNav ?? ''])

@section('title', 'No Modules Enabled')

@section('content')
<div class="mx-auto mt-12 max-w-lg rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600">
        @heroicon('o-lock-closed', 'h-6 w-6')
    </div>
    <h1 class="mt-4 text-xl font-bold text-gray-900">No modules enabled</h1>
    <p class="mt-2 text-sm text-gray-600">Your account is active, but no modules have been enabled for it yet. Ask an administrator to update your permissions.</p>
    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Sign out</button>
    </form>
</div>
@endsection
