<?php

namespace App\Services;

use App\Models\Setting;

class WatchSettings
{
    /** @return array<string, mixed> */
    public function all(): array
    {
        $stored = Setting::where('key', 'watch')->value('value');
        $values = is_string($stored) ? json_decode($stored, true) : $stored;

        return array_replace(config('watch.defaults'), is_array($values) ? $values : []);
    }

    public function threshold(): int
    {
        return (int) $this->all()['threshold'];
    }

    /** @param array<string, mixed> $values */
    public function save(array $values): void
    {
        Setting::updateOrCreate(['key' => 'watch'], ['value' => $values]);
    }
}
