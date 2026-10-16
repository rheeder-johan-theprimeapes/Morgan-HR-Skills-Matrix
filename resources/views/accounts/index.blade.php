<x-layout title="Accounts">
    <h1 class="text-3xl font-semibold tracking-tight">Accounts</h1>
    <p class="mt-3 text-sm text-slate-600">Provision individual access with a stable employee ID. Email is optional.</p>
    <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold">Provision an account</h2>
        <form method="POST" action="{{ route('accounts.store') }}" class="mt-5 grid gap-5 md:grid-cols-3">
            @csrf
            <x-field name="employee_number" label="Employee ID" maxlength="40" :required="true" />
            <x-field name="name" label="Full name" maxlength="255" :required="true" />
            <x-field name="email" label="Email (optional)" type="email" maxlength="255" />
            <div class="md:col-span-3"><x-button>Provision account</x-button></div>
        </form>
    </section>
    <div class="mt-8 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="tabular-nums w-full text-left text-sm">
            <caption class="sr-only">Provisioned employee accounts</caption>
            <thead class="border-b border-slate-200 bg-slate-100 text-xs uppercase tracking-wide text-slate-600"><tr><th class="p-4">Employee ID</th><th class="p-4">Name</th><th class="p-4">Access</th><th class="p-4"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($accounts as $account)
                    <tr><td class="whitespace-nowrap p-4 font-medium">{{ $account->login_id ?? 'Unlinked' }}</td><td class="p-4">{{ $account->name }}</td><td class="p-4">{{ !$account->is_active || !$account->employee?->is_active ? 'Disabled or unlinked' : ($account->password === null ? 'Setup required' : 'Ready') }}</td><td class="p-4 text-right"><a href="{{ route('accounts.show', $account) }}" class="font-medium text-emerald-800 underline underline-offset-4">Manage<span class="sr-only"> {{ $account->name }}</span></a></td></tr>
                @empty<tr><td colspan="4" class="p-6 text-slate-500">No accounts have been provisioned.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $accounts->links() }}</div>
</x-layout>
