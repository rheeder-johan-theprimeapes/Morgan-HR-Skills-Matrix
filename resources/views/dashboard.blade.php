<x-layout title="Overview">
    <p class="text-xs font-semibold uppercase tracking-widest text-emerald-800">Your workspace</p>
    <h1 class="mt-3 text-3xl font-semibold tracking-tight">Hello, {{ auth()->user()->name }}</h1>
    <p class="mt-3 text-sm text-slate-600">Your skills, reviews and development in one place.</p>
    <div class="mt-8 grid gap-5 md:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Your account</h2>
            <dl class="mt-4 grid gap-3 text-sm"><div><dt class="text-slate-500">Employee ID</dt><dd class="mt-1 font-medium">{{ auth()->user()->login_id }}</dd></div></dl>
        </section>
        @can('viewAny', App\Models\User::class)
            <section class="rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Account administration</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">Provision an employee account or issue private setup and recovery access after verifying identity.</p>
                <a href="{{ route('accounts.index') }}" class="mt-5 inline-block font-medium text-emerald-800 underline underline-offset-4">Manage accounts</a>
            </section>
        @endcan
    </div>
</x-layout>
