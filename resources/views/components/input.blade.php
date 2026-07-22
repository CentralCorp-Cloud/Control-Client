@props(['label'=>null,'name','type'=>'text','help'=>null])
@php($id = $attributes->get('id', $name))
<label for="{{ $id }}">
    @if($label)<span class="label">{{ $label }}</span>@endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type==='password'?'':old($name,$attributes->get('value')) }}" @if($help || $errors->has($name)) aria-describedby="{{ collect([$help ? $id.'-help' : null, $errors->has($name) ? $id.'-error' : null])->filter()->implode(' ') }}" @endif @error($name) aria-invalid="true" @enderror {{ $attributes->except(['value','id'])->merge(['class'=>'field']) }}>
    @if($help)<span id="{{ $id }}-help" class="field-help">{{ $help }}</span>@endif
    @error($name)<span id="{{ $id }}-error" class="field-error" role="alert">{{ $message }}</span>@enderror
</label>
