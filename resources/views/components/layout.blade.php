@props(['title' => 'Morgan'])
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Morgan</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4 focus:text-slate-900">Skip to content</a>
    <header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-5 md:px-8">
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="flex items-center gap-3 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-600">
                <span aria-hidden="true" class="grid size-10 place-items-center rounded-xl bg-emerald-800 text-xl font-semibold text-white dark:bg-emerald-500 dark:text-slate-950">M</span>
                <span><span class="block text-lg font-semibold tracking-tight">Morgan</span><span class="block text-xs text-slate-500 dark:text-slate-400">People &amp; skills</span></span>
            </a>
            @auth
                <div class="flex flex-wrap items-center gap-5 text-sm">
                    <nav aria-label="Main navigation" class="flex flex-wrap gap-5">
                        <a href="{{ route('dashboard') }}" class="font-medium hover:underline" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Overview</a>
                        @can('viewAny', App\Models\Employee::class)
                            <a href="{{ route('employees.index') }}" class="font-medium hover:underline" @if(request()->routeIs('employees.*', 'departments.*', 'job-roles.*')) aria-current="page" @endif>People</a>
                        @endcan
                        <a href="{{ route('reviews.index') }}" class="font-medium hover:underline" @if(request()->routeIs('reviews.*', 'review-cycles.*')) aria-current="page" @endif>Reviews</a>
                        <a href="{{ route('skills-matrix') }}" class="font-medium hover:underline" @if(request()->routeIs('skills-matrix')) aria-current="page" @endif>Matrix</a>
                        @can('viewAny', App\Models\Skill::class)<a href="{{ route('skills.index') }}" class="font-medium hover:underline" @if(request()->routeIs('skills.*', 'skill-groups.*')) aria-current="page" @endif>Catalogue</a>@endcan
                        @can('manage-skills')<a href="{{ route('frameworks.index') }}" class="font-medium hover:underline" @if(request()->routeIs('frameworks.*')) aria-current="page" @endif>Frameworks</a>@endcan
                        @can('manage-benchmarks')<a href="{{ route('benchmarks.index') }}" class="font-medium hover:underline" @if(request()->routeIs('benchmarks.*')) aria-current="page" @endif>Benchmarks</a>@endcan
                        @can('viewAny', App\Models\User::class)
                            <a href="{{ route('accounts.index') }}" class="font-medium hover:underline" @if(request()->routeIs('accounts.*')) aria-current="page" @endif>Accounts</a>
                        @endcan
                    </nav>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-sm text-slate-600 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-300" type="submit">Sign out</button></form>
                </div>
            @endauth
        </div>
    </header>
    @if(config('demo.enabled'))<div class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-center text-xs font-medium text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">Fictional local demonstration · People, ratings and targets are illustrative; HR approval is required for live policy.</div>@endif
    <main id="main-content" class="mx-auto max-w-6xl px-4 py-8 md:px-8 md:py-12">
        @if(session('status'))<div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-100">{{ session('status') }}</div>@endif
        {{ $slot }}
    </main>
    <footer class="mx-auto max-w-6xl px-4 pb-8 text-xs text-slate-500 md:px-8 dark:text-slate-400">Private workplace access. Contact HR if you need help.</footer>
</body>
</html>
