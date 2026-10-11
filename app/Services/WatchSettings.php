<?php

namespace App\Services;

use App\Models\CollectionRun;
use App\Models\Setting;
use Carbon\CarbonImmutable;

class WatchSettings
{
    /** Daily collection times are expressed in the user's time zone. */
    public const TIMEZONE = 'Europe/Paris';

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

    /** Most recent planned collection time (today at the chosen hour, or yesterday if that hour has not come yet). */
    public function lastPlannedAt(?CarbonImmutable $now = null): CarbonImmutable
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone(self::TIMEZONE);
        $hour = max(0, min(23, (int) $this->all()['collect_hour']));
        $slot = $now->setTime($hour, 0);

        return $slot->greaterThan($now) ? $slot->subDay() : $slot;
    }

    /** True when the planned collection of the current day has not been started yet. */
    public function collectionDue(): bool
    {
        if (! $this->all()['enabled']) {
            return false;
        }

        return ! CollectionRun::where('trigger', 'scheduled')
            ->where('created_at', '>=', $this->lastPlannedAt()->utc())
            ->exists();
    }

    /** Human readable description of the next automatic collection, for the interface. */
    public function nextPlannedLabel(): string
    {
        if (! $this->all()['enabled']) {
            return 'collectes automatiques désactivées';
        }
        if ($this->collectionDue()) {
            return 'au prochain passage du cron (celle de '.$this->lastPlannedAt()->format('H:i').' n’a pas encore été lancée)';
        }

        return $this->nextPlannedAt()->translatedFormat('l j F \à H:i');
    }

    /** Next planned collection time, in the user's time zone. */
    public function nextPlannedAt(): CarbonImmutable
    {
        return $this->collectionDue() ? $this->lastPlannedAt() : $this->lastPlannedAt()->addDay();
    }
}
