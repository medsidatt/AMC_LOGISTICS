<?php

namespace App\Http\Controllers;

use App\Models\Auth\User;
use App\Models\Entity;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:project-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:project-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:project-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:project-delete', ['only' => ['destroy']]);
        $this->middleware('permission:project-assign-user', ['only' => ['assignUser', 'storeAssignUser']]);
    }

    public function index()
    {
        $projects = Project::with('entity')
            ->forCurrentUser()
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'description' => $project->description,
                'logo' => $project->logo,
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'entity' => $project->entity ? [
                    'id' => $project->entity->id,
                    'name' => $project->entity->name,
                    'logo' => $project->entity->logo,
                ] : null,
            ]);

        $entities = Entity::all()->map(fn ($e) => [
            'id' => $e->id,
            'name' => $e->name,
        ])->toArray();

        return Inertia::render('projects/Index', [
            'projects' => $projects,
            'entities' => $entities,
        ]);
    }

    public function show($id)
    {
        $project = Project::with(['entity', 'users'])->findOrFail($id);

        return Inertia::render('projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'description' => $project->description,
                'logo' => $project->logo,
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'address' => $project->address,
                'phone' => $project->phone,
                'email' => $project->email,
                'entity' => $project->entity ? [
                    'id' => $project->entity->id,
                    'name' => $project->entity->name,
                ] : null,
                'users' => $project->users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->pivot->role ?? null,
                ])->toArray(),
            ],
        ]);
    }

    public function create()
    {
        $entities = Entity::all();
        return view('pages.projects.create', compact('entities'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'code' => 'string|required',
            'description' => 'string|nullable',
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable',
        ]);

        Project::firstOrCreate([
            'name' => $request->name,
            'code' => $request->code,
            'entity_id' => $request->entity_id,
        ], [
            'description' => $request->description,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'matricule_cnss' => $request->matricule_cnss,
            'matricule_cnam' => $request->matricule_cnam,
            'bp' => $request->bp,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'logo' => $request->file('logo') ? $request->file('logo')->store('logos', 'public') : null,
        ]);

        return redirect()->back()->with('success', __('global.created_success'));
    }

    public function edit($id)
    {
        $project = Project::findOrFail($id);
        $entities = Entity::all();
        return view('pages.projects.edit', compact('project', 'entities'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'code' => 'string|required',
            'description' => 'string|nullable',
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable',
            'matricule_cnss' => 'string|nullable',
            'matricule_cnam' => 'string|nullable',
            'bp' => 'string|nullable',
            'address' => 'string|nullable',
            'phone' => 'string|nullable',
            'email' => 'string|nullable|email',
            'entity_id' => 'required|exists:entities,id',
        ]);

        $proje = Project::findOrFail($id);

        if ($request->file('logo') && $proje->logo) {
            \Storage::disk('public')->delete($proje->logo);
        }

        $file = null;

        if ($request->file('logo')) {
            $file = $request->file('logo')->store('logos', 'public');
        } else {
            $file = $proje->logo;
        }

        $proje->update([
            'name' => $request->name,
            'code' => $request->code,
            'entity_id' => $request->entity_id,
            'description' => $request->description,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'matricule_cnss' => $request->matricule_cnss,
            'matricule_cnam' => $request->matricule_cnam,
            'bp' => $request->bp,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'logo' => $file,
        ]);

        return redirect()->back()->with('success', __('global.updated_success'));
    }

    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $project->delete();

        return redirect()->back()->with('success', __('global.deleted_success'));
    }

    public function assignUser($id)
    {
        $project = Project::findOrFail($id);
        $users = User::all();

        $roles = collect([
            'admin' => 'Admin',
            'manager' => 'Manager',
            'viewer' => 'Viewer',
        ])->mapWithKeys(
            function ($role, $key) {
                return [$key => [
                    'name' => $role,
                    'id' => $key,
                ]];
            }
        )->values()->all();

        $projectUsers = $project->users->pluck('id')->toArray();
        $users = $users->filter(function ($user) use ($projectUsers) {
            return !in_array($user->id, $projectUsers);
        })->values();

        return view('pages.projects.assign-user', [
            'project' => $project,
            'users' => $users,
            'roles' => $roles,
            'projectUsers' => $projectUsers,
        ]);
    }

    public function storeAssignUser(Request $request, $id)
    {
        $this->validate($request, [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:admin,manager,viewer',
        ]);

        $project = Project::findOrFail($id);
        $project->users()->attach($request->user_id, ['role' => $request->role]);

        return response()->json([
            'message' => __('User assigned successfully.'),
            'success' => true,
        ]);
    }

    public function destroyAssignUser($id, $userId)
    {
        $project = Project::findOrFail($id);
        $project->users()->detach($userId);

        return response()->json([
            'message' => __('User unassigned successfully.'),
            'success' => true
        ]);
    }
}
