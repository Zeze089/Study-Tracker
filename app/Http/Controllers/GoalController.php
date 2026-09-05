<?php

namespace App\Http\Controllers;

use App\Services\GoalProgressService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoalController extends Controller
{
    public function index(Request $request, GoalProgressService $goals): View
    {
        return view('goals.index', [
            'activeGoals' => $goals->activeProgress($request->user()),
        ]);
    }
}
