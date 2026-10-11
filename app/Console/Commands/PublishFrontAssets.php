<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Copies the Livewire and Flux scripts into public/vendor so the web server sends them as plain files.
 * Some hosts (OVHcloud web hosting) break those scripts when PHP streams them
 * (ERR_HTTP2_PROTOCOL_ERROR): buttons and filters then stop reacting.
 */
class PublishFrontAssets extends Command
{
    protected $signature = 'veille:assets';

    protected $description = 'Publier les scripts Livewire et Flux en fichiers statiques (public/vendor)';

    public function handle(): int
    {
        $this->callSilently('vendor:publish', ['--tag' => 'livewire:assets', '--force' => true]);

        $source = base_path('vendor/livewire/flux/dist');
        $target = public_path('vendor/flux');
        File::ensureDirectoryExists($target);
        foreach (['flux-lite.min.js', 'manifest.json'] as $file) {
            File::copy($source.'/'.$file, $target.'/'.$file);
        }

        $this->info('Scripts Livewire et Flux publiés dans public/vendor.');

        return self::SUCCESS;
    }
}
