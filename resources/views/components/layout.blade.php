@props(['title' => 'Morgan'])
<!DOCTYPE html>
<html lang="en-GB" class="scheme-only-light" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Morgan</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-slate-50 font-sans text-slate-900 antialiased selection:bg-emerald-100 selection:text-emerald-950">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4 focus:text-slate-900">Skip to content</a>
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex w-full max-w-app flex-wrap items-center justify-between gap-4 px-4 py-5 md:px-8">
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="flex items-center gap-3 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-600">
                <span aria-hidden="true" class="grid size-10 place-items-center rounded-xl bg-emerald-800 text-xl font-semibold text-white">M</span>
                <span><span class="block text-lg font-semibold tracking-tight">Morgan</span><span class="block text-xs text-slate-500">People &amp; skills</span></span>
            </a>
            @auth
                <details class="group relative xl:hidden">
                    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 [&::-webkit-details-marker]:hidden">
                        Menu
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="size-4 transition-transform group-open:rotate-180 motion-reduce:transition-none"><path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </summary>
                    <div class="absolute right-0 top-full z-40 mt-3 grid max-h-[calc(100dvh-6rem)] w-64 max-w-[calc(100vw-2rem)] gap-3 overflow-y-auto rounded-xl border border-slate-200 bg-white p-3">
                        <nav aria-label="Main navigation" class="grid gap-1"><x-navigation-links /></nav>
                        <div class="border-t border-slate-200 pt-2"><form method="POST" action="{{ route('logout') }}">@csrf<button class="min-h-11 rounded-lg px-2 text-sm text-slate-600 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" type="submit">Sign out</button></form></div>
                    </div>
                </details>
                <div class="hidden items-center gap-5 text-sm xl:flex">
                    <nav aria-label="Main navigation" class="flex flex-wrap gap-x-2 gap-y-1"><x-navigation-links /></nav>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="min-h-11 rounded-lg px-2 text-sm text-slate-600 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" type="submit">Sign out</button></form>
                </div>
            @endauth
        </div>
    </header>
    <main id="main-content" class="mx-auto w-full max-w-app px-4 py-8 md:px-8 md:py-12">
        @if(session('status'))<div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">{{ session('status') }}</div>@endif
        {{ $slot }}
    </main>
    <footer class="mx-auto w-full max-w-app px-4 pb-8 text-xs text-slate-500 md:px-8">Private workplace access. Contact HR if you need help.</footer>
</body>
</html>
