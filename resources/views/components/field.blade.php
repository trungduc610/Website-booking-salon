@props(['name', 'label', 'type' => 'text', 'autocomplete' => null, 'required' => false, 'hint' => null])
<div class="field-group">
    <label for="{{ $name }}" class="form-label">{{ $label }} @unless($required)<span class="optional">(không bắt buộc)</span>@endunless</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        class="form-control @error($name) is-invalid @enderror"
        @if($type !== 'password') value="{{ old($name) }}" @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @required($required)
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @else @if($hint) aria-describedby="{{ $name }}-hint" @endif @enderror
        {{ $attributes }}>
    @error($name)<div id="{{ $name }}-error" class="field-error" role="alert">{{ $message }}</div>@enderror
    @if($hint)<small id="{{ $name }}-hint" class="field-hint">{{ $hint }}</small>@endif
</div>
