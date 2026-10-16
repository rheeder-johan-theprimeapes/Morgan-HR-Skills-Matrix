<x-layout :title="$document['title']">
<a href="{{ route('historical-documents.index',$person['id']) }}" class="text-sm text-emerald-800 underline dark:text-emerald-400">{{ $person['name'] }} · Historical documents</a>
<p class="mt-6 text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400">Unstructured historical document</p>
<h1 class="mt-2 break-words text-3xl font-semibold">{{ $document['title'] }}</h1>
<div class="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 text-sm dark:border-slate-800 dark:bg-slate-900">
<p>Document date {{ $document['document_date'] }} · Stored {{ $document['uploaded_at'] }}</p>
<p class="whitespace-pre-wrap break-words">Source: {{ $document['source'] }}</p>
<p>{{ $document['visibility_label'] }} · Visibility revision {{ $document['revision'] }}</p>
<a href="{{ route('historical-documents.download',$document['id']) }}" class="w-fit break-all font-medium text-emerald-800 underline dark:text-emerald-400">Download {{ $document['original_name'] }} ({{ number_format($document['byte_size']) }} bytes)</a>
<p>This attachment does not create a current score or change any approved review or pay record.</p>
</div>
@if($canAdminister)
<section class="mt-8 max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"><h2 class="text-xl font-semibold">Change document visibility</h2>
@if($errors->any())<div role="alert" class="mt-4 text-sm text-red-800 dark:text-red-300"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><a href="{{ route('historical-documents.show',$document['id']) }}" class="mt-2 inline-block underline">Discard retained input and reload current visibility</a></div>@endif
<form method="POST" action="{{ route('historical-documents.visibility',$document['id']) }}" class="mt-5 grid gap-5">@csrf
<input type="hidden" name="expected_revision" value="{{ old('expected_revision',$document['revision']) }}">
@include('historical-documents._visibility',['currentVisibility'=>$document['visibility']])
<div><x-button>Save visibility revision</x-button></div>
</form></section>
<section class="mt-8"><h2 class="text-xl font-semibold">Private visibility history</h2>
@foreach($history as $row)
<article class="mt-4 rounded-xl border border-slate-200 p-5 text-sm dark:border-slate-800"><h3 class="font-semibold">Revision {{ $row['revision'] }} · {{ $row['visibility'] }}</h3><p class="mt-2">{{ $row['actor'] }} · {{ $row['recorded_at'] }}</p><p class="mt-2 whitespace-pre-wrap">{{ $row['reason'] }}</p><p class="mt-2">Audience content review {{ $row['reviewed'] ? 'confirmed' : 'not required for HR-only storage' }}.</p></article>
@endforeach
<div class="mt-6">{{ $history->links() }}</div>
</section>
@endif
</x-layout>
