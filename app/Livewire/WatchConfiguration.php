<?php

namespace App\Livewire;

use App\Models\Category;
use App\Services\WatchSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

class WatchConfiguration extends Component
{
    /** @var array<string, mixed> */
    public array $settings = [];

    /** @var list<int> */
    public array $monitored = [];

    public string $message = '';

    public function mount(WatchSettings $settings): void
    {
        Gate::authorize('manage-watch');
        $this->settings = $settings->all();
        foreach (Category::where('is_monitored', true)->get() as $category) {
            $this->monitored[] = $category->id;
        }
    }

    public function save(WatchSettings $settings): void
    {
        Gate::authorize('manage-watch');
        $rules = [
            'settings.threshold' => 'required|integer|min:0|max:100',
            'settings.batch_size' => 'required|integer|min:1|max:100',
            'settings.collect_hour' => 'required|integer|between:0,23',
            'settings.max_run_minutes' => 'required|integer|min:5|max:600',
            'settings.max_age_days' => 'required|integer|min:0|max:365',
            'settings.retention_days' => 'required|integer|min:30|max:3650',
            'settings.irrelevant_retention_days' => 'required|integer|min:1|max:3650',
            'settings.ai_profile' => 'nullable|string|max:3000',
            'settings.enabled' => 'boolean',
            'settings.priority_keywords' => 'nullable|string|max:2000',
            'settings.excluded_keywords' => 'nullable|string|max:2000',
            'monitored' => 'array',
            'monitored.*' => 'integer|exists:categories,id',
        ];
        foreach (array_keys(config('watch.defaults.weights')) as $key) {
            $rules['settings.weights.'.$key] = 'required|integer|min:0|max:100';
        }
        $validated = $this->validate($rules);
        if (array_sum($validated['settings']['weights']) !== 100) {
            $this->addError('settings.weights', 'La somme des pondérations doit être égale à 100.');

            return;
        }
        DB::transaction(function () use ($settings, $validated): void {
            $settings->save($validated['settings']);
            Category::query()->update(['is_monitored' => false]);
            Category::whereIn('id', $this->monitored)->update(['is_monitored' => true]);
        });
        $this->settings['collect_hour'] = (int) $this->settings['collect_hour'];
        $this->message = 'Paramètres enregistrés. Prochaine collecte automatique : '.$settings->nextPlannedLabel().'. Ils s’appliquent aux prochaines analyses ; « php artisan veille:reanalyze --all » fait rejuger les articles existants.';
    }

    public function render(): View
    {
        Gate::authorize('manage-watch');

        return view('livewire.watch-configuration', [
            'categories' => Category::orderBy('name')->get(),
            'nextCollection' => app(WatchSettings::class)->nextPlannedLabel(),
            'analyzer' => config('watch.analyzer') === 'local' ? 'local' : 'gemini',
            'model' => config('services.gemini.model'),
            'hasKey' => filled(config('services.gemini.key')),
        ])->layout('layouts.app', ['title' => 'Paramètres de veille']);
    }
}
