<x-layout title="Historical documents">
<a href="{{ route('employees.show',$person['id']) }}" class="text-sm text-emerald-800 underline dark:text-emerald-400">{{ $person['name'] }}</a>
<h1 class="mt-4 text-3xl font-semibold">Historical documents</h1>
<p class="mt-3 max-w-3xl text-sm text-slate-600 dark:text-slate-300">Private unstructured history. These files do not create a current score or change an approved review or pay record.</p>
<div class="mt-6 grid gap-4">
@forelse($documents as $document)
<article class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><a class="font-semibold text-emerald-800 underline dark:text-emerald-400" href="{{ route('historical-documents.show',$document['id']) }}">{{ $document['title'] }}</a><p class="mt-2 text-sm">Document date {{ $document['document_date'] }} · {{ $document['visibility_label'] }}</p></article>
@empty
<p class="rounded-xl border border-slate-200 p-5 text-sm dark:border-slate-800">No historical documents are available to you.</p>
@endforelse
</div>
<div class="mt-6">{{ $documents->links() }}</div>
@if($canAdminister)
<section class="mt-10 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
<h2 class="text-xl font-semibold">Upload historical document</h2>
<p class="mt-2 text-sm">PDF/JPEG/PNG, maximum 10 MiB. HR only is the default. Keep historical source details accurate; the file and provenance cannot be replaced after upload.</p>
@if($errors->any())<div role="alert" class="mt-4 text-sm text-red-800 dark:text-red-300"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p>Retained text can be corrected; select the file again before retrying.</p></div>@endif
<form method="POST" action="{{ route('historical-documents.store',$person['id']) }}" enctype="multipart/form-data" class="mt-5 grid max-w-2xl gap-5">
@csrf
<input type="hidden" name="expected_revision" value="{{ old('expected_revision',0) }}">
<x-field name="title" label="Document title" required maxlength="180" />
<x-field name="document_date" label="Original document date" type="date" required />
<label for="document-source" class="text-sm font-medium">Source / archive reference (required)</label>
<textarea id="document-source" name="source" required maxlength="4000" rows="3" class="rounded-lg border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">{{ old('source') }}</textarea>
<x-field name="attachment" label="Historical file (PDF/JPEG/PNG, maximum 10 MiB)" type="file" accept=".pdf,.jpg,.jpeg,.png" required />
@include('historical-documents._visibility')
<div><x-button>Store historical document privately</x-button></div>
</form>
</section>
@endif
</x-layout>
