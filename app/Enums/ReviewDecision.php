<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case Relevant = 'relevant';
    case Rejected = 'rejected';
}
