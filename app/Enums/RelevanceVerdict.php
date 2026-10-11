<?php

namespace App\Enums;

/** Automatic verdict given by the analyzer; independent of each user's personal decision. */
enum RelevanceVerdict: string
{
    case Relevant = 'relevant';
    case Irrelevant = 'irrelevant';

    public function label(): string
    {
        return match ($this) {
            self::Relevant => 'Pertinent',
            self::Irrelevant => 'Impertinent',
        };
    }
}
