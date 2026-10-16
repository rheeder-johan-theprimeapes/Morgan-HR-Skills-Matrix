<x-layout :title="$profile['name']">
    <a href="{{ route('employees.index') }}" class="text-sm text-emerald-800 underline underline-offset-4">Back to people</a>
    <div class="mt-6 flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ $profile['employee_number'] }}</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $profile['name'] }}</h1></div>@if($canEdit)<a href="{{ route('employees.edit', $profile['id']) }}" class="font-medium text-emerald-800 underline underline-offset-4">Edit employee</a>@endif</div>
    <dl class="mt-8 grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 text-sm md:grid-cols-2"><div><dt class="text-slate-500">Department</dt><dd class="mt-2 font-medium">{{ $profile['department'] ?? 'Not assigned' }}</dd></div><div><dt class="text-slate-500">Job role</dt><dd class="mt-2 font-medium">{{ $profile['job_role'] ?? 'Not assigned' }}</dd></div><div><dt class="text-slate-500">Reporting manager</dt><dd class="mt-2 font-medium">{{ $profile['manager'] ?? 'Not assigned' }}</dd></div><div><dt class="text-slate-500">Status</dt><dd class="mt-2 font-medium">{{ $profile['is_active'] ? 'Active' : 'Retired' }}</dd></div></dl>
    <a href="{{ route('employees.targets', $profile['id']) }}" class="mt-6 inline-block text-sm font-medium text-emerald-800 underline">Skills and targets</a>
    @if($canAssess)<a href="{{ route('assessments.index', $profile['id']) }}" class="ml-5 mt-6 inline-block text-sm font-medium text-emerald-800 underline">Assessments and evidence</a>@endif
    @if($canAssess)<a href="{{ route('development.index', $profile['id']) }}" class="ml-5 mt-6 inline-block text-sm font-medium text-emerald-800 underline">Development actions</a>@endif
    @if($canDocuments)<a href="{{ route('historical-documents.index', $profile['id']) }}" class="ml-5 mt-6 inline-block text-sm font-medium text-emerald-800 underline">Historical documents</a>@endif
    @if($canScore)<a href="{{ route('scores.index', $profile['id']) }}" class="ml-5 mt-6 inline-block text-sm font-medium text-emerald-800 underline">Manager scoring preview</a>@endif
    @if($canContext)<a href="{{ route('context.index', $profile['id']) }}" class="ml-5 mt-6 inline-block text-sm font-medium text-emerald-800 underline">Operational context</a>@endif
    @if($canPay)<a href="{{ route('pay.index', $profile['id']) }}" class="ml-5 mt-6 inline-block text-sm font-medium text-emerald-800 underline">Pay history</a>@endif
    @if($canLink)
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-lg font-semibold">Account access</h2>
            @if($linkedAccount)<p class="mt-3 text-sm">Linked login: {{ $linkedAccount['login_id'] }}</p><a href="{{ route('accounts.show', $linkedAccount['id']) }}" class="mt-4 inline-block text-sm font-medium text-emerald-800 underline underline-offset-4">Manage setup or recovery</a>
            @else
                <p class="mt-3 text-sm text-slate-600">This person has no linked account. Provision one, or explicitly choose an intended unlinked account. Linking requires fresh setup.</p>
                @error('account')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
                <form method="POST" action="{{ route('employees.account', $profile['id']) }}" class="mt-5 flex flex-wrap items-end gap-4">@csrf<x-field name="email" label="Email (optional)" type="email" /><x-button>Provision account</x-button></form>
                @if($unlinkedAccounts !== [])<form method="POST" action="{{ route('employees.account-link', $profile['id']) }}" class="mt-6 grid max-w-lg gap-3">@csrf<label for="account_id" class="text-sm font-medium">Intended existing account</label><select id="account_id" name="account_id" required class="rounded-lg border border-slate-300 bg-white p-3"><option value="">Select an account</option>@foreach($unlinkedAccounts as $account)<option value="{{ $account['id'] }}">{{ $account['name'] }} ({{ $account['login_id'] ?? 'No login ID' }})</option>@endforeach</select>@error('account_id')<p class="text-sm text-red-700">{{ $message }}</p>@enderror<div><x-button>Link selected account</x-button></div></form>@endif
            @endif
        </section>
    @endif
</x-layout>
