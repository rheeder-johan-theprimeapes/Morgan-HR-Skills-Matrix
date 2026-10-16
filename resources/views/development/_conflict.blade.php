@if($errors->has('expected_revision') && in_array($page['current']['state'],['completed','cancelled'],true) && in_array(old('mode'),['plan','progress'],true))
<section class="grid gap-4 rounded-2xl border border-amber-300 bg-amber-50 p-6 text-amber-950" aria-label="Retained unsubmitted input">
<h2 class="text-xl font-semibold">Unsubmitted input retained</h2>
<p>Submitted revision {{ old('expected_revision') }} · Current revision {{ $page['revision'] }}. Another update closed this action. Copy your text before reloading; saving this rejected revision is disabled.</p>
@php($retainedFields = old('mode') === 'plan' ? ['title'=>'Submitted title','description'=>'Submitted plan','starts_on'=>'Submitted start date','due_on'=>'Submitted due date','resource_label'=>'Submitted resource label','resource_url'=>'Submitted resource address'] : ['state'=>'Submitted status','notes'=>'Submitted progress / completion notes','completed_on'=>'Submitted completion date','reason'=>'Submitted reason'])
@foreach($retainedFields as $field=>$label)
@if(old($field)!==null)
<label for="retained-{{ $field }}" class="font-semibold">{{ $label }}</label><textarea id="retained-{{ $field }}" readonly rows="3" class="w-full rounded-lg border border-amber-400 bg-transparent p-3">{{ old($field) }}</textarea>
@endif
@endforeach
<button type="button" disabled class="justify-self-start rounded-lg border border-amber-400 px-4 py-2">Saving disabled for this rejected revision</button>
<a href="{{ route('development.show',$page['id']) }}" class="font-semibold underline">Discard unsaved input and reload</a><p class="text-sm">Files must be selected again after reloading.</p>
</section>
@endif
