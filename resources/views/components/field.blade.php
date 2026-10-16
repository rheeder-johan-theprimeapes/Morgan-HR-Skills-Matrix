@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false])
<div class="grid gap-2">
    <label for="{{ $name }}" class="text-sm font-medium">{{ $label }}@if($required)<span class="sr-only"> (required)</span>@endif</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @required($required)
        @if($type !== 'password') value="{{ old($name, $value) }}" @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @if($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class(['w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base shadow-xs outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/20 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-emerald-400']) }}>
    @error($name)<p id="{{ $name }}-error" class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
</div>
