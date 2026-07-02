<?php

namespace App\Http\Controllers;

use App\Enums\Fuel\ReviewOutcome;
use App\Models\FuelCardTransaction;
use App\Models\Truck;
use App\Services\Fuel\FuelReviewService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use InvalidArgumentException;

/**
 * R10 — thin boundary over FuelReviewService. Validates HTTP, delegates, returns responses.
 * No business logic; reuses the `fuel-import` authorization.
 */
class FuelReviewController extends Controller
{
    public function __construct(private readonly FuelReviewService $reviewService)
    {
        $this->middleware('auth');
        $this->middleware('permission:fuel-import');
    }

    /** Pending-review queue. */
    public function queue(Request $request)
    {
        $filters = array_filter($request->only(['truck_id']));

        $records = $this->reviewService->pending($filters)
            ->through(fn (FuelCardTransaction $t) => [
                'id' => $t->id,
                'date' => $t->occurred_at?->format('d/m/Y H:i'),
                'truck' => $t->truck?->matricule,
                'detected_plate' => $t->detected_plate,
                'amount' => (float) $t->amount_fcfa,
                'type' => $t->transaction_type?->value,
                'findings' => $t->proposed_business_findings ?? [],
                'imported_by' => $t->importedBy?->name,
            ])->appends($request->query());

        return Inertia::render('fuel/Review', [
            'records' => $records,
            'filters' => $filters,
            'trucks' => Truck::where('is_active', true)->orderBy('matricule')->get(['id', 'matricule'])
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->matricule])->all(),
            'outcomes' => collect(ReviewOutcome::cases())
                ->map(fn (ReviewOutcome $o) => ['value' => $o->value, 'label' => $o->label(), 'requires_truck' => $o->requiresTruck()])->all(),
        ]);
    }

    /** Review details: proposal snapshot (immutable), effective values, and full history. */
    public function show(FuelCardTransaction $transaction)
    {
        $transaction->load([
            'truck:id,matricule', 'driver:id,name', 'importedBy:id,name', 'reviewedBy:id,name',
            'reviewEvents' => fn ($q) => $q->latest('created_at'),
            'reviewEvents.reviewer:id,name',
        ]);

        return response()->json([
            'record' => [
                'id' => $transaction->id,
                'transaction_ref' => $transaction->transaction_ref,
                'date' => $transaction->occurred_at?->format('d/m/Y H:i'),
                'source' => $transaction->source?->value,
                'type' => $transaction->transaction_type?->value,
                'amount' => (float) $transaction->amount_fcfa,
                'estimated_litres' => $transaction->estimated_litres !== null ? (float) $transaction->estimated_litres : null,
                'card_number' => $transaction->card_number,
                'holder_raw' => $transaction->holder_raw,
                'detected_plate' => $transaction->detected_plate,
                'imported_by' => $transaction->importedBy?->name,
            ],
            'effective' => [
                'truck' => $transaction->truck?->matricule,
                'driver' => $transaction->driver?->name,
                'kpi_eligible' => (bool) $transaction->kpi_eligible,
                'review_status' => $transaction->review_status,
                'review_outcome' => $transaction->review_outcome,
                'reviewed_by' => $transaction->reviewedBy?->name,
                'reviewed_at' => $transaction->reviewed_at?->format('d/m/Y H:i'),
            ],
            // Immutable proposal snapshot.
            'proposal' => [
                'technical_findings' => $transaction->proposed_technical_findings ?? [],
                'business_findings' => $transaction->proposed_business_findings ?? [],
                'kpi_eligible' => (bool) $transaction->proposed_kpi_eligible,
                'policy_version' => $transaction->policy_version,
            ],
            'history' => $transaction->reviewEvents->map(fn ($e) => [
                'id' => $e->id,
                'outcome' => $e->outcome,
                'note' => $e->note,
                'reviewer' => $e->reviewer?->name,
                'at' => $e->created_at?->format('d/m/Y H:i'),
                'before' => $e->before,
                'after' => $e->after,
            ])->all(),
        ]);
    }

    /** Apply a reviewer decision. */
    public function resolve(FuelCardTransaction $transaction, Request $request)
    {
        $validated = $request->validate([
            'outcome' => ['required', Rule::enum(ReviewOutcome::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'truck_id' => ['nullable', 'integer', 'exists:trucks,id'],
        ]);

        try {
            $this->reviewService->resolve(
                $transaction,
                ReviewOutcome::from($validated['outcome']),
                auth()->id(),
                $validated['note'] ?? null,
                $validated['truck_id'] ?? null,
            );
        } catch (DomainException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaction revue.');
    }
}
