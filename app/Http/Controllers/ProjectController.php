<?php

namespace App\Http\Controllers;

use App\Models\Auth\User;
use App\Models\Entity;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        if (\request()->ajax()) {
            $projects = Project::with('entity')->forCurrentUser()->get();

            if (request()->filters['entity']) {
                $projects = $projects->where('entity_id', request()->filters['entity']);
            }

            return datatables()->of($projects)
                ->addColumn('actions', function ($project) {
                    $actions = [
                        [
                            'label' => __('global.show'),
                            'href' => route('projects.show', $project->id),
                            'permission' => 'project-show'
                        ],
                        [
                            'label' => __('global.edit'),
                            'onclick' => 'showModal({
                                    route: \'' . route('projects.edit', $project->id) . '\',
                                    title: \'' . __('global.project_edit') . '\',
                                    size: \'lg\',
                                })',
                            'permission' => 'project-edit'
                        ],
                        [
                            'label' => __('Affecter un utilisateur'),
                            'onclick' => 'showModal({
                                    route: \'' . route('projects.assign.user', $project->id) . '\',
                                    title: \'' . __('Affecter un utilisateur pour '. $project->name) . '\',
                                })',
                            'permission' => true
                        ],
                        [
                            'label' => __('global.delete'),
                            'href' => route('projects.destroy', $project->id),
                            'permission' => 'project-delete'
                        ],
                    ];
                    return view('components.buttons.action', [
                        'actions' => $actions
                    ]);
                })
                ->editColumn('logo', function ($project) {
                    if ($project->logo) {
                        return '<img src="' . asset('storage/' . $project->logo) . '" alt="' . $project->name . '" class="img-thumbnail" style="width: 100px;">';
                    } else {
                        return '<img src="' .  asset('storage/' . $project->entity?->logo) . '" alt="' . $project->name . '" class="img-thumbnail" style="width: 100px;">';
                    }
                })
                ->editColumn('name', function ($project){
                    return '<a href="' . route('projects.show', $project->id) . '">' . $project->name . ' ('. $project->code . ')</a>';
                })
                ->editColumn('entity_id', function ($project) {
                    return $project->entity ? $project->entity->name : 'N/A';
                })
                ->rawColumns(['actions', 'logo', 'name'])
                ->make(true);
        }

        $actions = [
            [
                'label' => __('global.create'),
                'permission' => true,
                'onclick' => 'showModal({
                                 route: \'' . route('projects.create') . '\',
                                 title: \'' . __('global.project_create') . '\',
                                 size: \'lg\',
                           })'
            ]
        ];

        return view('pages.projects.index', [
            'actions' => $actions,
            'breadcrumbs' => [
                ['label' => __('global.projects'), 'url' => ''],
            ],
            'entities' => Entity::all(),
        ]);
    }

    public function show($id)
    {
        $project = Project::findOrFail($id);

        $actions = [
            [
                'label' => __('global.edit'),
                'onclick' => 'showModal({
                                    route: \'' . route('projects.edit', $id) . '\',
                                    title: \'' . __('global.project_edit') . '\',
                                    size: \'lg\',
                                })',
                'permission' => 'project-edit'
            ],
            [
                'label' => __('global.delete'),
                'onclick' => 'confirmDelete(\'' . route('projects.destroy', $id) . '\')',
                'permission' => 'project-delete'
            ],
        ];

        $breadcrumbs = [
            [
                'url' => route('projects.index'),
                'label' => __('global.projects')
            ],
            [
                'url' => '#',
                'label' => $project->name . ' ('. $project->entity->name .')'
            ]
        ];

        return view('pages.projects.show', compact('project', 'actions', 'breadcrumbs'));
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

        return response()->json([
            'message' => __('global.created_success'),
            'success' => true
        ]);
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

        return response()->json([
            'message' => __('global.updated_success'),
            'success' => true,
            'file' => $file,
        ]);
    }

    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $project->delete();

        return response()->json([
            'message' => __('global.deleted_success'),
            'success' => true
        ]);
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
