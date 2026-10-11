{{-- Replaces @fluxScripts: serves the Flux script as a static file when "php artisan veille:assets" has published it. --}}
@php
    $published = public_path('vendor/flux/flux-lite.min.js');
    $manifest = public_path('vendor/flux/manifest.json');
@endphp
@if (file_exists($published) && file_exists($manifest))
    @php(app('livewire')->forceAssetInjection())
    <script src="{{ asset('vendor/flux/flux-lite.min.js') }}?id={{ json_decode(file_get_contents($manifest), true)['/flux.js'] ?? '' }}" data-navigate-once></script>
@else
    @fluxScripts
@endif
