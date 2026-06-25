<?php

namespace App\Http\Controllers;

use App\Models\Auth\User;
use App\Models\ExpectedTransportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketGapController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:daily-dispatch-edit', ['only' => ['dismiss']]);
    }

    public function dismiss(Request $request, ExpectedTransportTicket $expected): RedirectResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        /** @var User|null $user */
        $user = auth()->user();

        $expected->update([
            'status' => ExpectedTransportTicket::STATUS_DISMISSED,
            'dismissed_reason' => $data['reason'],
            'dismissed_by' => $user?->id,
        ]);

        return back()->with('success', 'Expected ticket dismissed.');
    }
}
