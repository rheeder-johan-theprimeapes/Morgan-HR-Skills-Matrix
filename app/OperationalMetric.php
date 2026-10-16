<?php

declare(strict_types=1);

namespace App;

enum OperationalMetric: string
{
    case AbsenceHours = 'absence_hours';
    case PpeObservations = 'ppe_observations';
    case RightFirstTime = 'right_first_time';
    case ReworkHours = 'rework_hours';
    case RetentionContext = 'retention_context';
    case RoleCriticality = 'role_criticality';
    case HumanContext = 'human_context';

    public function unit(): string
    {
        return match ($this) {
            self::AbsenceHours, self::ReworkHours => 'hours', self::PpeObservations => 'observations', self::RightFirstTime => 'percent', default => 'narrative'
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AbsenceHours => 'Attendance absence hours', self::PpeObservations => 'PPE observation count', self::RightFirstTime => 'Right-first-time quality', self::ReworkHours => 'Rework hours', self::RetentionContext => 'Retention / replacement context', self::RoleCriticality => 'Role criticality context', self::HumanContext => 'Other human context'
        };
    }

    public function isInterpretation(): bool
    {
        return $this->unit() === 'narrative';
    }
}
