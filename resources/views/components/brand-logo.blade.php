@props(['height' => '2rem', 'variant' => 'auto'])
{{-- CODENYR wordmark. "auto" follows the light/dark theme; "dark" forces the version made for dark backgrounds. --}}
<span {{ $attributes->class('brand-logo inline-flex items-center') }}>
    @if ($variant !== 'dark')
        <img src="{{ asset('images/codenyr-logo-light.png') }}" alt="CODENYR" class="brand-logo-light" style="height: {{ $height }}; width: auto" />
    @endif
    <img src="{{ asset('images/codenyr-logo-dark.png') }}" alt="CODENYR" @class(['brand-logo-dark' => $variant !== 'dark']) style="height: {{ $height }}; width: auto" />
</span>
