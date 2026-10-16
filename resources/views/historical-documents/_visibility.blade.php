<div class="grid gap-3">
<label for="document-visibility" class="text-sm font-medium">Who may read this entire document and its provenance?</label>
<select id="document-visibility" name="visibility" required class="rounded-lg border border-slate-300 bg-white p-3">
@foreach(App\DocumentVisibility::cases() as $visibility)
<option value="{{ $visibility->value }}" @selected(old('visibility', $currentVisibility ?? 'hr_only') === $visibility->value)>{{ $visibility->label() }}</option>
@endforeach
</select>
<label class="flex items-start gap-3 text-sm"><input name="content_review_confirmed" type="checkbox" value="1" @checked(old('content_review_confirmed')) class="mt-1">I reviewed the entire file and provenance for the selected audience. Shared content excludes internal calibration, comparisons and other people's information.</label>
<label for="visibility-reason" class="text-sm font-medium">Reason for this visibility decision (required; private to HR administrators)</label>
<textarea id="visibility-reason" name="reason" required maxlength="4000" rows="3" class="rounded-lg border border-slate-300 bg-white p-3">{{ old('reason') }}</textarea>
</div>
