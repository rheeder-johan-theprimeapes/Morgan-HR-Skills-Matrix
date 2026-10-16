<?php

declare(strict_types=1);

namespace App;

enum AssessmentKind: string
{
    case Self = 'self';
    case Manager = 'manager';
}
