<?php

declare(strict_types=1);

namespace App;

enum Capability: string
{
    case AdministerAccounts = 'accounts.administer';
    case ManageDirectory = 'directory.manage';
    case ViewOrganisation = 'people.view-all';
    case ManageFrameworks = 'frameworks.manage';
    case AdministerReviews = 'reviews.administer';
    case DecideReviews = 'reviews.decide';
    case ActualPay = 'pay.actual';
    case Benchmarks = 'pay.benchmarks';
    case RecommendPay = 'pay.recommend';
    case ApprovePay = 'pay.approve';
    case SensitiveContext = 'context.sensitive';
}
