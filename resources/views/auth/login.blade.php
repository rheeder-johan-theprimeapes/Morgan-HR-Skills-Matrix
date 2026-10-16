<x-layout title="Sign in">
    <div class="mx-auto max-w-md">
        <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-emerald-800">Your workplace</p>
        <h1 class="text-3xl font-semibold tracking-tight">Welcome back</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">Sign in with the employee ID and password issued for your account.</p>
        <form method="POST" action="{{ route('login.store') }}" class="mt-8 grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
            @csrf
            <x-field name="login_id" label="Employee ID" autocomplete="username" maxlength="40" :required="true" autofocus />
            <x-field name="password" label="Password" type="password" autocomplete="current-password" :required="true" />
            <x-button class="w-full">Sign in</x-button>
        </form>
        <p class="mt-6 text-center text-sm"><a href="{{ route('account-setup') }}" class="font-medium text-emerald-800 underline underline-offset-4">Set up or recover access</a></p>
        <p class="mt-4 text-center text-xs leading-5 text-slate-500">Accounts are provisioned by HR. An email address is not required.</p>
    </div>
</x-layout>
