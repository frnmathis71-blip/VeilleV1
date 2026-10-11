<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Dossier de veille technologique</title>
<style>
@page { margin: 42px 42px 52px; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; line-height: 1.6; color: #243247; }
h1 { font-size: 28px; line-height: 1.25; color: #123e74; margin: 10px 0 16px; }
h2 { font-size: 17px; line-height: 1.4; color: #123e74; }
h3 { font-size: 11px; margin: 16px 0 3px; }
p { margin: 6px 0; }
.eyebrow { color: #2563eb; letter-spacing: 2px; font-size: 10px; }
.muted { color: #637083; }
.cover { border-top: 5px solid #2563eb; padding-top: 24px; margin-bottom: 26px; }
.article { page-break-before: always; }
.meta { background: #eff6ff; padding: 12px; margin: 16px 0; }
.note { border-left: 3px solid #2563eb; padding-left: 12px; white-space: pre-wrap; overflow-wrap: anywhere; }
.url { font-size: 8px; word-wrap: break-word; color: #2563eb; }
table { width: 100%; border-collapse: collapse; } td { padding: 5px 0; border-bottom: 1px solid #e2e8f0; }
</style></head><body>
<div class="cover"><p class="eyebrow">BTS SIO · SLAM</p><h1>Dossier de veille<br>technologique</h1><p>{{ $name }}</p><p class="muted">{{ $period }}</p><p><strong>{{ $articles->count() }} articles validés personnellement</strong></p><p class="muted">Édité le {{ now()->timezone('Europe/Paris')->format('d/m/Y à H:i') }}</p></div>
<h2>Répartition des sujets</h2><table>@forelse($categories as $label=>$count)<tr><td>{{ $label }}</td><td style="text-align:right">{{ $count }}</td></tr>@empty<tr><td>Aucune catégorie détectée.</td></tr>@endforelse</table>
<p class="muted">Un article peut appartenir à plusieurs catégories.</p>
<h2>Méthode</h2><p>Cette sélection rassemble uniquement les articles validés par l’utilisateur. Les scores automatiques proviennent de règles locales explicites. Les extraits restent dans la langue du flux ; ils ne constituent pas une traduction ni une reproduction intégrale du contenu de l’éditeur.</p>
@foreach($articles as $article)
<section class="article"><p class="eyebrow">ARTICLE {{ $loop->iteration }} / {{ $articles->count() }}</p><h2>{{ $article->displayTitle() }}</h2>@if($article->isTitleTranslated())<p class="muted">Titre original : {{ $article->title }}</p>@endif
<div class="meta"><strong>{{ $article->source?->name }}</strong><br>Publication : {{ $article->published_at?->timezone('Europe/Paris')->format('d/m/Y') ?? 'Non précisée' }}<br>Score automatique : {{ $article->analysis?->score ?? 'Non analysé' }} / 100<br>Catégories : {{ $article->categories->pluck('name')->join(', ') ?: 'Non classé' }}</div>
<h3>Résumé / extrait du flux</h3><p>{{ $article->analysis?->summary ?? $article->description }}</p>
<h3>Justification du classement</h3><p>{{ $article->analysis?->explanation ?? 'Analyse non disponible.' }}</p>
@if($article->reviews->first()?->personal_note)<h3>Note personnelle</h3><p class="note">{{ $article->reviews->first()->personal_note }}</p>@endif
<h3>Source originale</h3><p class="url"><a href="{{ $article->url }}">{{ $article->url }}</a></p>
<p class="muted">Validation : {{ $article->reviews->first()?->decided_at?->timezone('Europe/Paris')->format('d/m/Y') ?? 'Non précisée' }}</p>
</section>
@endforeach
</body></html>
