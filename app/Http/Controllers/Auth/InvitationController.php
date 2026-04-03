<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Auth\Invitation;
use App\Models\Auth\User;
use App\Models\Employee\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class InvitationController extends Controller
{

    // index
    public function index()
    {

        if (\request()->ajax()) {
            $invitations = Invitation::all();
            return datatables()->of($invitations)
                ->addColumn('action', function ($invitation) {
                    $actions = [
                        [
                            'label' => 'Edit',
                            'onclick' => 'openInModal({ link: \'' . route('invitation.edit', $invitation->id) . '\', size: \'md\' })',
                            'permission' => 'invitation-edit'
                        ],
                        [
                            'label' => 'Delete',
                            'onclick' => 'confirmDelete(\'' . route('invitation.destroy', $invitation->id) . '\')',
                            'permission' => 'invitation-delete'
                        ]
                    ];

                    return view('components.buttons.action', ['actions' => $actions]);
                })
                ->editColumn('created_at', function ($invitation) {
                    return $invitation->created_at->format('d/m/Y');
                })
                ->editColumn('expires_at', function ($invitation) {
                    return view('components.badges.badge', [
                        'label' => 'Expire ' . Carbon::parse($invitation->expires_at)->diffForHumans(),
                        'color' => 'danger'
                    ]);
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('auth.invitations.index', [
            'actions' => [
                [
                    'label' => 'Create',
                    'onclick' => 'openInModal({ link: \'' . route('invitation.create', 1) . '\', size: \'md\' })',
                    'permission' => 'invitation-create'
                ]
            ]
        ]);
    }

    public function create()
    {
        $roles = $this->assignableRoles();
        return view('auth.invitations.create', compact('roles'));
    }

    public function edit($id)
    {
        $invitation = Invitation::findOrFail($id);
        $roles = $this->assignableRoles();
        return view('auth.invitations.edit', compact('invitation', 'roles'));
    }

    /**
     * Roles the current user can assign:
     * - Super Admin can assign any role
     * - Admin can assign Admin and Driver only
     */
    private function assignableRoles()
    {
        $user = auth()->user();
        $query = Role::whereIn('name', ['Super Admin', 'Admin', 'Driver']);

        if (!$user->hasRole('Super Admin')) {
            $query->where('name', '!=', 'Super Admin');
        }

        return $query->orderBy('name')->get(['name']);
    }

    public function sendInvitation(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email|unique:invitations,email',
            'role_name' => 'required|string|exists:roles,name',
        ]);

        DB::beginTransaction();
        try {
            $token = Str::random(32);

            $invitation = Invitation::create([
                'email' => $request->email,
                'role_name' => $request->role_name,
                'token' => $token,
                'expires_at' => now()->addDays(7),
            ]);

            Mail::to($request->email)->send(new InvitationMail($invitation));
            DB::commit();
            return response()->json([
                'message' => 'Invitation sent successfully.',
                'success' => true
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to send invitation.',
                'success' => false
            ], 500);
        }

    }

    public function accept($token)
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if ($invitation->is_used || $invitation->isExpired()) {
            return view('auth.invitations.expired', [
                'message' => 'Invalid or expired invitation.',
            ]);
        }

        return view('auth.register', ['email' => $invitation->email, 'token' => $token]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'phone' => 'required|numeric|digits:8',
            'password' => 'required|confirmed',
            'password_confirmation' => ['required', 'same:password'],
            'token' => 'required',
        ]);

        $invitation = Invitation::where('token', $request->token)->firstOrFail();

        if ($invitation->is_used || $invitation->isExpired()) {
            return response()->json(['message' => 'Invalid or expired invitation.'], 400);
        }

        $user = User::firstOrCreate([
            'email' => $invitation->email,
        ], [
            'name' => $request->first_name . ' ' . $request->last_name,
            'password' => bcrypt($request->password),
        ]);

        /*$user->employee()->create([
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $invitation->email,
            'phone' => $request->phone,
        ]);*/

        $user->employee()->firstOrCreate(
            [
                'email' => $invitation->email,
            ],
            [
                'name' => $request->first_name . ' ' . $request->last_name,
                'phone' => $request->phone,
                'address' => 'Nouakchott, Mauritania',
            ]);

        if (! empty($invitation->role_name)) {
            $role = Role::query()->where('name', $invitation->role_name)->first();
            if ($role) {
                $user->syncRoles([$role->name]);
            }
        }

        $invitation->update(['is_used' => true]);

        try {
            Auth::login($user);
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Failed to login.');
        }

        return redirect()->route('home');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $id,
            'role_name' => 'required|string|exists:roles,name',
        ]);

        $invitation = Invitation::findOrFail($id);
        $invitation->email = $request->email;
        $invitation->role_name = $request->role_name;
        $invitation->expires_at = now()->addDays(7);
        $invitation->save();

        Mail::to($request->email)->send(new InvitationMail($invitation));

        return response()->json([
            'message' => 'Invitation sent successfully.',
            'success' => true
        ], 200);

    }

    // delete
    public function destroy($id)
    {
        $invitation = Invitation::findOrFail($id);
        $invitation->delete();
        return response()->json([
            'message' => 'Invitation deleted successfully.',
            'success' => true
        ], 200);
    }


}
