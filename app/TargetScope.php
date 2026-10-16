<?php

declare(strict_types=1);

namespace App;

enum TargetScope: string
{
    case Department = 'department';
    case JobRole = 'job_role';
    case Employee = 'employee';
}
