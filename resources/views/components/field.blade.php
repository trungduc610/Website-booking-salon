@props(['name', 'label', 'type' => 'text', 'autocomplete' => null, 'required' => false, 'hint' => null])
<div class="field-group">
    <label for="{{ $name }}" class="form-label">{{ $label }} @unless ($required)
            <span class="optional">(không bắt buộc)</span>
        @endunless
    </label>
    <div class="{{ $type === 'password' ? 'password-input' : 'field-input' }}">
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
            class="form-control @error($name) is-invalid @enderror"
            @if ($type !== 'password') value="{{ old($name) }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif @required($required)
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @else @if ($hint) aria-describedby="{{ $name }}-hint" @endif @enderror
            {{ $attributes }}>
        @if ($type === 'password')
            <button type="button" class="password-toggle" data-password-toggle aria-controls="{{ $name }}"
                aria-pressed="false" aria-label="Hiện {{ mb_strtolower($label) }}" hidden>Hiện</button>
        @endif
    </div>
    @error($name)
        <div id="{{ $name }}-error" class="field-error" role="alert">{{ $message }}</div>
    @enderror
    @if ($hint)
        <small id="{{ $name }}-hint" class="field-hint">{{ $hint }}</small>
    @endif
</div>
