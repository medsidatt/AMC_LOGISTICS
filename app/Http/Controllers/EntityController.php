<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use Illuminate\Http\Request;
use Yajra\DataTables\Exceptions\Exception;

class EntityController extends Controller
{
    //index
    /**
     * @throws Exception
     * @throws \Exception
     */

    public function __construct()
    {
        $this->middleware('permission:entity-list|entity-create|entity-edit|entity-delete', ['only' => ['index']]);
        $this->middleware('permission:entity-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:entity-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:entity-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {

        if ($request->ajax()) {
            return datatables()
                ->of(Entity::query())
                ->addColumn('actions', function ($data) {
                    $actions = [
                        [
                            'label' => __('global.show'),
                            'onclick' => 'openInModal({link: \'' . route('entities.show', $data->slug) . '\'})',
                            'permission' => 'entity-show',
                        ],
                        [
                            'label' => __('global.edit'),
                            'onclick' => 'openInModal({link: \'' . route('entities.edit', $data->slug) . '\'})',
                            'permission' => 'entity-edit',
                        ],
                        [
                            'label' => __('global.delete'),
                            'onclick' => 'confirmDelete(\'' . route('entities.destroy', $data->slug) . '\')',
                            'permission' => 'entity-delete',
                        ]
                    ];

                    return view('components.buttons.action', ['actions' => $actions]);
                })->editColumn('logo', function ($data) {
                    return $data->logo ? '<img src="' . asset('storage/' . $data->logo) . '" alt="' . $data->name . '" class="img-thumbnail" style="width: 100px;">' : '';
                })
                ->rawColumns(['actions', 'logo'])
                ->make(true);
        }

        $actions = [
            [
                'label' => __('global.create'),
                'onclick' => 'openInModal({link: \'' . route('entities.create') . '\'})',
                'permission' => 'entity-create',
            ]
        ];

        return view('pages.entities.index', ['actions' => $actions]);
    }
    //create
    public function create()
    {
        return view('pages.entities.create');
    }

    //store
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'description' => 'string|nullable',
            'matricule_cnss' => 'string|nullable',
            'matricule_cnam' => 'string|nullable',
            'nif' => 'string|nullable',
            'rc' => 'string|nullable',
            'address' => 'string|nullable',
            'phone' => 'string|nullable',
            'email' => 'string|email|nullable',
            'website' => 'string|url|nullable',
            'is_active' => 'boolean|nullable',
            'logo' => 'image|nullable|max:2048', // 2MB max
            'ilot' => 'string|nullable',
            'lot' => 'string|nullable',
            'city' => 'string|nullable',
            'activity_principle' => 'string|nullable',
            'bp' => 'string|nullable',
        ]);

        Entity::firstOrCreate([
            'name' => $request->name,
            'description' => $request->description,
            'matricule_cnss' => $request->matricule_cnss,
            'matricule_cnam' => $request->matricule_cnam,
            'nif' => $request->nif,
            'rc' => $request->rc,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'website' => $request->website,
            'is_active' => $request->is_active ?? true,
            'logo' => $request->file('logo') ? $request->file('logo')->store('logos', 'public') : null,
            'ilot' => $request->ilot,
            'lot' => $request->lot,
            'city' => $request->city,
            'activity_principle' => $request->activity_principle,
            'bp' => $request->bp,
        ]);

        return response()->json([
            'message' => __('global.created_success'),
            'success' => true,
        ]);
    }
    //show
    public function show(Entity $entity)
    {
        return view('pages.entities.show', ['entity' => $entity]);
    }

    //edit
    public function edit(Entity $entity)
    {
        return view('pages.entities.edit', ['entity' => $entity]);
    }
    //update
    public function update(Request $request, Entity $entity): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required',
            'description' => 'string|nullable',
            'matricule_cnss' => 'string|nullable',
            'matricule_cnam' => 'string|nullable',
            'nif' => 'string|nullable',
            'rc' => 'string|nullable',
            'address' => 'string|nullable',
            'phone' => 'string|nullable',
            'email' => 'string|email|nullable',
            'website' => 'string|url|nullable',
            'is_active' => 'boolean|nullable',
            'logo' => 'image|nullable|max:2048', // 2MB max
            'ilot' => 'string|nullable',
            'lot' => 'string|nullable',
            'city' => 'string|nullable',
            'activity_principle' => 'string|nullable',
            'bp' => 'string|nullable',
        ]);

        // check if the entity has logo or not already if has and the request has file replace it and if not keep the old one
        if ($request->file('logo') && $entity->logo) {
            // Delete old logo if exists
            \Storage::disk('public')->delete($entity->logo);
        }

        $entity->update([
            'name' => $request->name,
            'description' => $request->description,
            'matricule_cnss' => $request->matricule_cnss,
            'matricule_cnam' => $request->matricule_cnam,
            'nif' => $request->nif,
            'rc' => $request->rc,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'website' => $request->website,
            'is_active' => $request->is_active ?? true,
            'logo' => $request->file('logo') ? $request->file('logo')->store('logos', 'public') : $entity->logo,
            'ilot' => $request->ilot,
            'lot' => $request->lot,
            'city' => $request->city,
            'activity_principle' => $request->principal_activity,
            'bp' => $request->bp,
        ]);

        return response()->json([
            'message' => __('global.updated_success'),
            'success' => true,
        ]);
    }
    //destroy
    public function destroy(Entity $entity)
    {
        $entity->delete();

        return response()->json([
            'message' => __('global.deleted_success'),
            'success' => true,
        ]);
    }
}
