<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SkillsMatrixRequest;
use App\Models\User;
use App\Services\SkillsMatrix;
use Illuminate\Contracts\View\View;

class SkillsMatrixController extends Controller
{
    public function index(SkillsMatrixRequest $request, SkillsMatrix $matrix): View
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        return view('skills.matrix', $matrix->page($actor, $request->validated()));
    }
}
