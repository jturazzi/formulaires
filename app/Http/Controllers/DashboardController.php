<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Response;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request): InertiaResponse
    {
        $user = $request->user();

        // Admins see stats and recent activity across every form, not just their own.
        $baseQuery = fn () => $user->isAdmin() ? Form::query() : $user->forms();

        $formIds = $baseQuery()->pluck('id');

        return Inertia::render('Dashboard', [
            'stats' => [
                'forms' => $formIds->count(),
                'published' => $baseQuery()->where('status', Form::STATUS_PUBLISHED)->count(),
                'responses' => Response::query()->whereIn('form_id', $formIds)->count(),
                'responses_this_week' => Response::query()
                    ->whereIn('form_id', $formIds)
                    ->where('submitted_at', '>=', now()->subDays(7))
                    ->count(),
            ],
            'recentForms' => $baseQuery()
                ->withCount('responses')
                ->latest('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (Form $form) => [
                    'id' => $form->id,
                    'title' => $form->title,
                    'status' => $form->status,
                    'responses_count' => $form->responses_count,
                    'updated_at' => $form->updated_at,
                ]),
        ]);
    }
}
