<?php

declare(strict_types=1);

namespace App;

enum PdrState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case HrReviewed = 'hr_reviewed';
    case Approved = 'approved';
    case FeedbackRecorded = 'feedback_recorded';
    case Released = 'released';
    case Acknowledged = 'acknowledged';
}
