@props(['label'=>null,'name','help'=>null])
@php($id = $attributes->get('id', $name))
<label for="{{ $id }}">
    @if($label)<span class="label">{{ $label }}</span>@endif
    <select id="{{ $id }}" name="{{ $name }}" @if($help || $errors->has($name)) aria-describedby="{{ collect([$help ? $id.'-help' : null, $errors->has($name) ? $id.'-error' : null])->filter()->implode(' ') }}" @endif @error($name) aria-invalid="true" @enderror {{ $attributes->except('id')->merge(['class'=>'field']) }}>{{ $slot }}</select>
    @if($help)<span id="{{ $id }}-help" class="field-help">{{ $help }}</span>@endif
    @error($name)<span id="{{ $id }}-error" class="field-error" role="alert">{{ $message }}</span>@enderror
</label>
