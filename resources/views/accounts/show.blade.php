<x-layout title="Manage account">
    <a href="{{ route('accounts.index') }}" class="text-sm text-emerald-800 underline underline-offset-4">Back to accounts</a>
    <h1 class="mt-6 text-3xl font-semibold tracking-tight">{{ $account->name }}</h1>
    <div class="mt-8 max-w-2xl rounded-2xl border border-slate-200 bg-white p-6">
        <dl class="grid gap-4 text-sm"><div><dt class="text-slate-500">Employee ID</dt><dd class="mt-1 font-medium">{{ $account->login_id ?? 'Not linked' }}</dd></div><div><dt class="text-slate-500">Email</dt><dd class="mt-1">{{ $account->email ?? 'Not provided' }}</dd></div><div><dt class="text-slate-500">Account status</dt><dd class="mt-1">{{ $account->is_active && $account->employee?->is_active ? 'Active' : 'Disabled or unlinked' }}</dd></div></dl>
        <h2 class="mt-8 text-lg font-semibold">Setup or recovery access</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">Verify this person's identity before issuing access. Issuing a new code signs them out, clears their current password and invalidates previous codes.</p>
        @error('account')<p role="alert" class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
        <form method="POST" action="{{ route('accounts.access', $account) }}" class="mt-5">@csrf<x-button :disabled="!$account->is_active || !$account->employee?->is_active">Issue private setup code</x-button></form>
    </div>
</x-layout>
