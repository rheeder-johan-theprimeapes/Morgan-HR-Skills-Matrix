<x-layout title="Set up access">
    <div class="mx-auto max-w-lg">
        <a href="{{ route('login') }}" class="text-sm font-medium text-emerald-800 underline underline-offset-4">Back to sign in</a>
        <h1 class="mt-6 text-3xl font-semibold tracking-tight">Set up your access</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">Enter the private setup code HR gave you. It works once and expires after 30 minutes. Use this page for first-time setup or recovery.</p>
        <form method="POST" action="{{ route('account-setup.store') }}" class="mt-8 grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
            @csrf
            <x-field name="login_id" label="Employee ID" autocomplete="username" maxlength="40" :required="true" />
            <x-field name="code" label="Setup code" type="password" autocomplete="one-time-code" maxlength="64" :required="true" />
            <p class="text-sm text-slate-600">Choose a password or passphrase with at least 12 characters.</p>
            <x-field name="password" label="New password" type="password" autocomplete="new-password" :required="true" />
            <x-field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" :required="true" />
            <x-button class="w-full">Save password</x-button>
        </form>
    </div>
</x-layout>
