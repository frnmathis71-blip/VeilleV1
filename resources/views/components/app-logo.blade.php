@props([
    'sidebar' => false,
])

<a {{ $attributes->class(['flex items-center', 'px-2 py-1.5' => $sidebar]) }} aria-label="CODENYR – accueil">
    <x-brand-logo :height="$sidebar ? '1.9rem' : '1.6rem'" />
</a>
