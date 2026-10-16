<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ScoringFramework;
use App\Models\User;
use App\Services\AssessmentAccess;
use App\Services\EmployeeProfile;
use App\Services\EmployeeScore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ScoreController extends Controller
{
    public function index(Request $request, Employee $employee, AssessmentAccess $access): View
    {
        abort_unless($access->canSupervise($this->actor($request), $employee), 403);

        return view('scores.index', ['profile' => EmployeeProfile::fromEmployee($employee), 'frameworks' => ScoringFramework::query()->whereNotNull('published_at')->latest('id')->paginate(20)]);
    }

    public function show(Request $request, Employee $employee, ScoringFramework $framework, EmployeeScore $scores): View
    {
        $result = $scores->preview($this->actor($request), $employee, $framework);

        return view('scores.show', ['profile' => EmployeeProfile::fromEmployee($employee), 'framework' => $framework, 'result' => $result]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        return $actor;
    }
}
