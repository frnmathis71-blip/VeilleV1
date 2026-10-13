<?php

namespace App\Livewire;

use App\Console\Commands\HourlyWatch;
use App\Models\CollectionRun;
use App\Models\Setting;
use App\Services\Collection\Collector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class History extends Component
{
    use WithPagination;

    public ?int $selected = null;

    public string $message = '';

    public function launch(Collector $collector): void
    {
        Gate::authorize('manage-watch');
        $run = $collector->request(auth()->user()?->id);
        $this->selected = $run->id;
        $this->message = 'Collecte #'.$run->id.' en attente. Le cron la traitera ; vous pouvez aussi traiter un lot ci-dessous.';
    }

    public function process(Collector $collector): void
    {
        Gate::authorize('manage-watch');
        $run = $collector->process();
        $this->message = $run ? 'Lot traité : '.$run->created_count.' nouveaux articles, '.$run->analyzed_count.' analysés.' : 'Aucun lot disponible, ou un autre traitement est en cours.';
    }

    private function lastCronRun(): ?Carbon
    {
        $at = Setting::where('key', HourlyWatch::HEARTBEAT_KEY)->first()?->value['at'] ?? null;

        return is_string($at) ? Carbon::parse($at) : null;
    }

    public function render(): View
    {
        return view('livewire.history', [
            'runs' => CollectionRun::latest('id')->paginate(15),
            'pausedUntil' => app(Collector::class)->analyzerPausedUntil(),
            'cronAt' => $this->lastCronRun(),
            'detail' => $this->selected ? CollectionRun::with(['sources.source', 'items' => fn ($q) => $q->whereNotNull('error')->with('article')])->find($this->selected) : null,
        ])->layout('layouts.app', ['title' => 'Historique']);
    }
}
