<?php

namespace App\Http\Controllers;

use App\Models\ObjectiveHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ObjectiveHistoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:objective-history-list');
    }

    public function index(Request $request)
    {
        $query = ObjectiveHistory::query()
            ->with('user:id,name')
            ->orderByDesc('changed_at')
            ->orderByDesc('id');

        if ($field = $request->query('field')) {
            $query->where('field_name', $field);
        }
        if ($direction = $request->query('direction')) {
            $query->where('direction', $direction);
        }
        if ($subjectType = $request->query('subject_type')) {
            $query->where('subject_type', $subjectType);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('changed_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('changed_at', '<=', $to);
        }

        $entries = $query->limit(500)->get()->map(fn (ObjectiveHistory $e) => [
            'id' => $e->id,
            'subject_type' => $e->subject_type,
            'subject_label' => $e->subject_label,
            'field_name' => $e->field_name,
            'field_label' => $e->field_label,
            'old_value' => $e->old_value,
            'new_value' => $e->new_value,
            'magnitude' => $e->magnitude !== null ? (float) $e->magnitude : null,
            'direction' => $e->direction,
            'note' => $e->note,
            'context' => $e->context,
            'user' => $e->user?->only(['id', 'name']),
            'changed_at' => $e->changed_at?->format('Y-m-d H:i'),
        ]);

        return Inertia::render('logistics/objective-history/Index', [
            'entries' => $entries,
            'filters' => [
                'field' => $request->query('field'),
                'direction' => $request->query('direction'),
                'subject_type' => $request->query('subject_type'),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
        ]);
    }
}
