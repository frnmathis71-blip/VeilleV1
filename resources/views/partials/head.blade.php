<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico?v=codenyr" sizes="any">
<link rel="icon" href="/images/codenyr-icon.png?v=codenyr" type="image/png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png?v=codenyr">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
<style>
    /* Kept inline so the article grid stays spaced and responsive even before "npm run build". */
    .tile-grid { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 17rem), 1fr)); }
    [x-cloak] { display: none !important; }
    /* CODENYR logo: dark wordmark on light theme, white wordmark on dark theme. */
    .brand-logo-dark { display: none; }
    .dark .brand-logo-light { display: none; }
    .dark .brand-logo-dark { display: inline-block; }
    @media (max-width: 767px) { .tile-grid { gap: 1rem; } }
</style>
