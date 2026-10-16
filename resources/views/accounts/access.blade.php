<x-layout title="Private setup access">
    <div class="max-w-2xl">
        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-800 dark:text-emerald-400">Private handover</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight">Setup access for {{ $account->name }}</h1>
        <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-400">This code is shown once and expires in 30 minutes. Give it directly to the verified person. Keep it out of email chains, shared notes and tickets.</p>
        <div class="mt-6 grid gap-5 rounded-2xl border border-emerald-200 bg-white p-6 dark:border-emerald-800 dark:bg-slate-900">
            <div><p class="text-sm text-slate-500 dark:text-slate-400">Employee ID</p><p class="mt-2 font-semibold">{{ $account->login_id }}</p></div>
            <div><p class="text-sm text-slate-500 dark:text-slate-400">One-time setup code</p><code class="mt-2 block select-all break-all rounded-lg bg-slate-100 p-4 text-base dark:bg-slate-800">{{ $code }}</code></div>
        </div>
        <p class="mt-5 text-sm leading-6 text-slate-600 dark:text-slate-400">The employee selects “Set up or recover access” from the sign-in page, then enters their ID, this code and a new password.</p>
        <a href="{{ route('accounts.show', $account) }}" class="mt-6 inline-block font-medium text-emerald-800 underline underline-offset-4 dark:text-emerald-400">Return to account</a>
    </div>
</x-layout>
