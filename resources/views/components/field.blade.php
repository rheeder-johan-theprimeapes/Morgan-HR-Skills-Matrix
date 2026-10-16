@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false])
<div class="grid gap-2">
    <label for="{{ $name }}" class="text-sm font-medium">{{ $label }}@if($required)<span class="sr-only"> (required)</span>@endif</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @required($required)
        @if($type !== 'password') value="{{ old($name, $value) }}" @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @if($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class(['min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 caret-emerald-800 outline-2 outline-offset-1 outline-transparent transition-colors duration-150 placeholder:text-slate-500 hover:border-slate-400 focus:border-emerald-700 focus:outline-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 disabled:opacity-60 aria-invalid:border-red-700 aria-invalid:focus:outline-red-700 motion-reduce:transition-none']) }}>
    @error($name)<p id="{{ $name }}-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
</div>
