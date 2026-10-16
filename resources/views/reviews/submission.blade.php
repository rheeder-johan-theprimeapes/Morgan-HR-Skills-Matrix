<x-layout :title="'Submitted review revision '.$page['submission_number']">
<div class="w-full space-y-6">
<a class="text-sm font-semibold text-emerald-800 underline" href="{{ route('reviews.show',$page['review_id']) }}">Review and revision history</a>
<div><p class="text-sm text-slate-600">Immutable submission {{ $page['submission_number'] }} · {{ $page['state'] }}</p><h1 class="mt-2 text-3xl font-semibold">{{ $page['person']['name'] }}</h1><p class="mt-2">{{ $page['cycle']['name'] }} · {{ $page['framework']['name'] }}</p></div>
@if(session('status'))<p role="status" class="rounded-xl bg-teal-50 p-4">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="rounded-xl bg-red-50 p-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach<a class="underline" href="{{ route('submissions.show',[$page['review_id'],$page['id']]) }}">Discard unsaved input and reload</a></div>@endif
<section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-semibold">Frozen score and observations</h2><p class="mt-3 text-2xl">{{ number_format($page['score']['score'],2) }} / 100 · {{ $page['score']['band'] }}</p><p class="mt-3 whitespace-pre-wrap">{{ $page['manager_section']['summary'] }}</p><p class="mt-3 whitespace-pre-wrap">{{ $page['manager_section']['role_notes'] ?? '' }}</p><dl class="mt-4 space-y-3">
@foreach(['reflection'=>'Employee reflection','achievements'=>'Employee achievements / examples','development_needs'=>'Employee development needs'] as $field=>$label)
<div><dt class="font-semibold">{{ $label }}</dt><dd class="mt-1 whitespace-pre-wrap">{{ $page['employee_section'][$field] ?? 'Not recorded' }}</dd></div>
@endforeach
</dl><details class="mt-4"><summary class="cursor-pointer font-semibold">Frozen component breakdown</summary>@foreach($page['score']['components'] as $component)<p class="mt-2">{{ $component['name'] }} · {{ $component['mean'] ?? 'Excluded' }} · denominator {{ $component['denominator'] }} · weight {{ $component['weight'] }}</p>@endforeach</details></section>
<section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-semibold">Objectives</h2>@foreach($page['manager_section']['objectives'] as $goal)<div class="mt-4 border-t pt-4"><p>{{ $goal['description'] }}</p><p>{{ $goal['measure'] }} · Due {{ $goal['due_on'] }}</p></div>@endforeach</section>
<section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-semibold">Frozen private evidence</h2>
@foreach($page['assessments'] as $assessment)
<div class="mt-4 border-t border-slate-200 pt-4"><p class="font-semibold">{{ $assessment['skill_name'] }} · {{ $assessment['assessed_on'] }} · {{ $assessment['rating'] }}</p><p class="mt-2 whitespace-pre-wrap">{{ $assessment['evidence_notes'] ?? 'No written evidence notes' }}</p><p class="mt-2 whitespace-pre-wrap">{{ $assessment['comment'] ?? '' }}</p></div>
@endforeach
@forelse($page['evidence'] as $file)<p class="mt-3">@if($file['available'])<a class="underline" href="{{ route('submissions.evidence',[$page['review_id'],$page['id'],$file['id']]) }}">{{ $file['name'] }}</a>@else{{ $file['name'] }} · Unavailable at submission @endif</p>@empty<p class="mt-3">No file attachments. Recorded observations remain part of the private revision.</p>@endforelse</section>
@if(isset($page['actual_pay']))
<details class="rounded-2xl border border-slate-200 bg-white p-6"><summary class="cursor-pointer font-semibold">Restricted actual-pay evidence</summary>
@forelse($page['actual_pay'] as $pay)<div class="mt-4 border-t pt-4"><p>{{ $pay['currency'] }} {{ number_format($pay['amount_minor']/100,2) }} / {{ $pay['basis'] }} · effective {{ substr($pay['effective_on'],0,10) }}</p><p class="mt-2 whitespace-pre-wrap">Source: {{ $pay['source'] }}</p>@if($pay['weekly_hours_hundredths']!==null)<p>Explicit working year: {{ $pay['weekly_hours_hundredths']/100 }} hours/week × {{ $pay['weeks_per_year_hundredths']/100 }} weeks/year</p>@endif</div>@empty<p class="mt-4">No actual-pay evidence recorded at submission.</p>@endforelse
</details>
@endif
@if(isset($page['benchmark']))
<details class="rounded-2xl border border-slate-200 bg-white p-6"><summary class="cursor-pointer font-semibold">Restricted benchmark evidence</summary><div class="mt-4 space-y-2"><h2 class="font-semibold">{{ $page['benchmark']['title'] }}</h2><p>{{ $page['benchmark']['currency'] }} {{ number_format($page['benchmark']['amount_minor']/100,2) }} / {{ $page['benchmark']['basis'] }} · source dated {{ substr($page['benchmark']['source_date'],0,10) }}</p><p>Validity {{ substr($page['benchmark']['valid_from'],0,10) }} to {{ substr($page['benchmark']['valid_to'],0,10) }}</p>@foreach(['company_context'=>'Company context','duties'=>'Duties','limitations'=>'Limitations','source'=>'Source'] as $field=>$label)<p class="whitespace-pre-wrap">{{ $label }}: {{ $page['benchmark'][$field] }}</p>@endforeach</div></details>
@endif
@if(isset($page['pay_proposal']))
<section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-semibold">Internal manager proposal</h2><p class="mt-3">{{ ucfirst(str_replace('_',' ',$page['pay_proposal']['outcome'])) }}</p>@if($page['pay_proposal']['amount_minor']!==null)<p>{{ $page['pay_proposal']['currency'] }} {{ number_format($page['pay_proposal']['amount_minor']/100,2) }} / {{ $page['pay_proposal']['basis'] }} · proposed effective date {{ $page['pay_proposal']['effective_on'] }}</p>@endif<p class="mt-3 whitespace-pre-wrap">{{ $page['pay_proposal']['reason'] }}</p></section>
@endif
@if(isset($page['operational_context']))
<details class="rounded-2xl border border-slate-200 bg-white p-6"><summary class="cursor-pointer font-semibold">Restricted operational context</summary>
@forelse($page['operational_context'] as $context)<div class="mt-4 space-y-2 border-t pt-4"><p class="font-semibold">{{ App\OperationalMetric::from($context['metric'])->label() }} · {{ $context['is_interpretation'] ? 'Human interpretation' : 'Factual observation' }}</p><p>{{ substr($context['period_start'],0,10) }} to {{ substr($context['period_end'],0,10) }} · {{ $context['value_hundredths']===null ? 'Narrative' : $context['value_hundredths']/100 }} {{ $context['unit'] }}</p><p class="whitespace-pre-wrap">{{ $context['notes'] }}</p><p class="whitespace-pre-wrap">Source: {{ $context['source'] }}</p></div>@empty<p class="mt-4">No operational evidence recorded at submission.</p>@endforelse
</details>
@endif
<section class="space-y-4"><h2 class="text-xl font-semibold">Revision review history</h2>
@foreach($page['transitions'] as $transition)
<div class="rounded-xl border border-slate-200 p-4"><p class="font-semibold">{{ ucfirst(str_replace('_',' ',$transition['action'])) }} · Submission {{ $transition['submission_number'] }} · {{ $transition['actor'] }} · {{ $transition['created_at'] }}</p><p class="mt-2 whitespace-pre-wrap">{{ $transition['reason'] }}</p></div>
@endforeach
</section>
@if(isset($page['decision']))
<section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-semibold">{{ $page['decision']['outcome']==='approve' ? 'Approved human pay outcome' : 'Human return decision' }}</h2><p>{{ $page['decision']['decided_at'] }}</p>
@if(($page['decision']['approved_pay']['outcome'] ?? null)==='change')<p>{{ $page['decision']['approved_pay']['currency'] }} {{ number_format($page['decision']['approved_pay']['amount_minor']/100,2,'.','') }} / {{ $page['decision']['approved_pay']['basis'] }} · effective {{ $page['decision']['approved_pay']['effective_on'] }}</p>
@elseif(($page['decision']['approved_pay']['outcome'] ?? null)==='no_change')<p>No change to pay.</p>@endif
<p class="whitespace-pre-wrap">{{ $page['decision']['reason'] }}</p><p class="text-sm">Actual-pay records and payroll remain separate.</p></section>
@endif
@if($page['can_record_feedback'])<a class="font-semibold text-emerald-800 underline" href="{{ route('feedback.edit',$page['review_id']) }}">Feedback and employee release</a>@endif
@include('reviews._decision')
@if($page['can_hr_review'])<form class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6" method="post" action="{{ route('submissions.hr',[$page['review_id'],$page['id']]) }}">@csrf<input type="hidden" name="expected_version" value="{{ old('expected_version',$page['head_version']) }}"><h2 class="text-xl font-semibold">HR completeness review</h2><label class="block">Outcome<select class="mt-1 block w-full rounded-lg border border-slate-300 bg-transparent p-3" name="action" required><option value="review" @selected(old('action')==='review')>Evidence reviewed — send to SLT</option><option value="return" @selected(old('action')==='return')>Return for revision</option></select></label><label class="block">Review / return reason<textarea class="mt-1 block w-full rounded-lg border border-slate-300 bg-transparent p-3" name="reason" required maxlength="4000">{{ old('reason') }}</textarea></label><button class="rounded-lg bg-teal-800 px-5 py-3 font-semibold text-white">Record HR outcome</button></form>@endif
</div>
</x-layout>
