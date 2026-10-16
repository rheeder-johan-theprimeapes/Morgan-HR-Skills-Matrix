<?php

declare(strict_types=1);

namespace App;

enum AssessmentState: string
{
    case Rated = 'rated';
    case NotApplicable = 'not_applicable';
    case Unassessed = 'unassessed';
}
