<?php

declare(strict_types=1);
use App\Actions\AcknowledgePdrReview;
use App\Actions\DecidePdrSubmission;
use App\Actions\RecordPdrFeedback;
use App\Actions\ReleasePdrReview;
use App\Actions\ReviewPdrSubmission;
use App\Actions\SubmitPdrReview;
use App\Capability;
use App\Models\EvidenceAttachment;
use App\Models\PdrAcknowledgement;
use App\Models\PdrDecision;
use App\Models\PdrRelease;
use App\Models\PdrReview;
use App\Models\PdrSubmission;
use App\Models\PdrTransition;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Support/PdrFixtures.php';
uses(DatabaseTruncation::class);

it('preserves referenced evidence if an after commit callback reports failure', function (): void {
    Storage::fake('evidence');
    [$hr,$manager,$employee,$cycle,$review,$assessment,$revision] = submissionFixture();
    Storage::disk('evidence')->put('original.txt', 'Fictional evidence');
    EvidenceAttachment::factory()->create(['assessment_revision_id' => $revision->id, 'storage_path' => 'original.txt', 'byte_size' => strlen('Fictional evidence'), 'sha256' => hash('sha256', 'Fictional evidence')]);
    PdrSubmission::created(function (): void {
        DB::afterCommit(function (): never {
            throw new RuntimeException('Simulated callback failure after durable commit');
        });
    });
    try {
        expect(fn () => app(SubmitPdrReview::class)->handle($manager, $review, submissionTokens($review)))->toThrow(RuntimeException::class, 'Simulated callback failure');
    } finally {
        PdrSubmission::flushEventListeners();
        PdrSubmission::clearBootedModels();
    }
    $submission = PdrSubmission::query()->sole();
    $path = $submission->snapshot['assessments'][0]['attachments'][0]['storage_path'];
    expect(Storage::disk('evidence')->exists($path))->toBeTrue();
});

it('rejects a competing committed submission after its caller established an older snapshot', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = submissionFixture();
    $settings = config('database.connections.mysql');
    expect($settings['database'])->toBe('morgan_hr_skills_test');
    config(['database.connections.pdr_competitor' => $settings]);
    expect(DB::connection('pdr_competitor')->selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');
    $tokens = submissionTokens($review);
    DB::transaction(function () use ($manager, $review, $tokens): void {
        expect(PdrReview::query()->findOrFail($review->id)->state->value)->toBe('draft');
        DB::setDefaultConnection('pdr_competitor');
        try {
            app(SubmitPdrReview::class)->handle($manager, $review, $tokens);
        } finally {
            DB::setDefaultConnection('mysql');
        }
        expect(fn () => app(SubmitPdrReview::class)->handle($manager, $review, $tokens))->toThrow(HttpException::class);
    });
    expect(PdrSubmission::query()->count())->toBe(1)->and(PdrTransition::query()->count())->toBe(1);
    DB::purge('pdr_competitor');
});

it('uses current HR authority despite an older repeatable read snapshot', function (): void {
    [$hr,$manager,$employee,$cycle,$review] = submissionFixture();
    $submission = app(SubmitPdrReview::class)->handle($manager, $review, submissionTokens($review));
    $settings = config('database.connections.mysql');
    expect($settings['database'])->toBe('morgan_hr_skills_test');
    $other = DB::build($settings);
    expect($other->selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');
    DB::transaction(function () use ($hr, $review, $submission, $other): void {
        expect(User::query()->findOrFail($hr->id)->hasCapability(Capability::AdministerReviews))->toBeTrue();
        $other->table('users')->where('id', $hr->id)->update(['capabilities' => '[]']);
        expect(fn () => app(ReviewPdrSubmission::class)->handle($hr, $review, $submission, ['expected_version' => $review->refresh()->version, 'action' => 'review', 'reason' => 'Denied revoked grant']))->toThrow(HttpException::class);
    });
    expect($review->refresh()->state->value)->toBe('submitted')->and(PdrTransition::query()->count())->toBe(1);
});

it('permits only one competing SLT decision after the other connection commits', function (string $winningAction): void {
    [$hr,$manager,$employee,$cycle,$review] = submissionFixture();
    $submission = app(SubmitPdrReview::class)->handle($manager, $review, submissionTokens($review));
    app(ReviewPdrSubmission::class)->handle($hr, $review, $submission, ['expected_version' => $review->refresh()->version, 'action' => 'review', 'reason' => 'Fictional evidence check']);
    $slt = User::factory()->withEmployee()->create(['capabilities' => [Capability::DecideReviews->value, Capability::ApprovePay->value, Capability::ActualPay->value, Capability::ViewOrganisation->value]]);
    $settings = config('database.connections.mysql');
    expect($settings['database'])->toBe('morgan_hr_skills_test');
    config(['database.connections.pdr_competitor' => $settings]);
    expect(DB::connection('pdr_competitor')->selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');
    $token = $review->refresh()->version;
    DB::transaction(function () use ($slt, $review, $submission, $token, $winningAction): void {
        expect(PdrReview::query()->findOrFail($review->id)->state->value)->toBe('hr_reviewed');
        DB::setDefaultConnection('pdr_competitor');
        try {
            $input = ['expected_version' => $token, 'action' => $winningAction, 'reason' => 'First human outcome'];
            if ($winningAction === 'approve') {
                $input['pay_outcome'] = 'no_change';
            }
            app(DecidePdrSubmission::class)->handle($slt, $review, $submission, $input);
        } finally {
            DB::setDefaultConnection('mysql');
        }
        expect(fn () => app(DecidePdrSubmission::class)->handle($slt, $review, $submission, ['expected_version' => $token, 'action' => 'approve', 'reason' => 'Losing stale decision', 'pay_outcome' => 'no_change']))->toThrow(HttpException::class);
    });
    expect(PdrDecision::query()->count())->toBe(1)->and(PdrDecision::query()->sole()->outcome)->toBe($winningAction)->and(PdrTransition::query()->count())->toBe(3);
    DB::purge('pdr_competitor');
})->with(['approve', 'return']);

it('serialises competing explicit release and employee receipt transitions', function (string $operation): void {
    [$hr,$manager,$employee,$cycle,$review] = submissionFixture();
    $submission = app(SubmitPdrReview::class)->handle($manager, $review, submissionTokens($review));
    app(ReviewPdrSubmission::class)->handle($hr, $review, $submission, ['expected_version' => $review->refresh()->version, 'action' => 'review', 'reason' => 'Fictional checked evidence']);
    $slt = User::factory()->withEmployee()->create(['capabilities' => [Capability::DecideReviews->value, Capability::ApprovePay->value, Capability::ActualPay->value, Capability::ViewOrganisation->value]]);
    app(DecidePdrSubmission::class)->handle($slt, $review, $submission, ['expected_version' => $review->refresh()->version, 'action' => 'approve', 'reason' => 'Fictional human decision', 'pay_outcome' => 'no_change']);
    app(RecordPdrFeedback::class)->handle($manager, $review, ['expected_version' => $review->refresh()->version, 'held_on' => today()->toDateString(), 'summary' => 'Fictional intended feedback']);
    if ($operation === 'receipt') {
        app(ReleasePdrReview::class)->handle($manager, $review, ['expected_version' => $review->refresh()->version]);
    }
    $settings = config('database.connections.mysql');
    expect($settings['database'])->toBe('morgan_hr_skills_test');
    config(['database.connections.pdr_competitor' => $settings]);
    expect(DB::connection('pdr_competitor')->selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');
    $token = $review->refresh()->version;
    DB::transaction(function () use ($manager, $employee, $review, $token, $operation): void {
        expect(PdrReview::query()->findOrFail($review->id)->version)->toBe($token);
        $write = fn () => $operation === 'release' ? app(ReleasePdrReview::class)->handle($manager, $review, ['expected_version' => $token]) : app(AcknowledgePdrReview::class)->handle($employee, $review, ['expected_version' => $token, 'choice' => 'disagree', 'comment' => 'Fictional receipt comment']);
        DB::setDefaultConnection('pdr_competitor');
        try {
            $write();
        } finally {
            DB::setDefaultConnection('mysql');
        }
        expect($write)->toThrow(HttpException::class);
    });
    expect(PdrRelease::query()->count())->toBe(1);
    if ($operation === 'receipt') {
        expect(PdrAcknowledgement::query()->count())->toBe(1)->and(PdrAcknowledgement::query()->sole()->choice)->toBe('disagree');
    }
    DB::purge('pdr_competitor');
})->with(['release', 'receipt']);
