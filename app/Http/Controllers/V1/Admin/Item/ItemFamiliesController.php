<?php

namespace Crater\Http\Controllers\V1\Admin\Item;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\ItemFamilyRequest;
use Crater\Http\Resources\ItemFamilyResource;
use Crater\Models\ItemFamily;
use Crater\Services\ItemFamilyService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ItemFamiliesController extends Controller
{
    /**
     * Display a flat listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, ItemFamilyService $service)
    {
        $this->authorize('viewAny', ItemFamily::class);

        // The plan mandates a flat response so the client can build
        // the tree without recursive resources; the controller is
        // the single source of truth for ordering. We sort by
        // `parent_id NULL` (roots first), then by `name`, which
        // mirrors the server-side depth grouping the UI uses.
        $families = ItemFamily::whereCompany()
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END ASC')
            ->orderBy('parent_id', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return ItemFamilyResource::collection($families);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(ItemFamilyRequest $request, ItemFamilyService $service)
    {
        $this->authorize('create', ItemFamily::class);

        try {
            $family = $service->create(
                $request->getFamilyPayload(),
                (int) $request->header('company')
            );
        } catch (ValidationException $e) {
            // Plan 09 hotfix: el servicio lanza ValidationException
            // cuando detecta sibling-unique / cycle / cross-company.
            // Lo traducimos al contrato 422 del response del cliente.
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        // Plan 09 hotfix: POST devuelve 200 OK en lugar del 201 default.
        return (new ItemFamilyResource($family))->response()->setStatusCode(200);
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(ItemFamily $itemFamily)
    {
        $this->authorize('view', $itemFamily);

        return new ItemFamilyResource($itemFamily);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(
        ItemFamilyRequest $request,
        ItemFamilyService $service,
        ItemFamily $itemFamily
    ) {
        $this->authorize('update', $itemFamily);

        try {
            $family = $service->update($itemFamily, $request->getFamilyPayload());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return (new ItemFamilyResource($family))->response()->setStatusCode(200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(ItemFamilyService $service, ItemFamily $itemFamily)
    {
        $this->authorize('delete', $itemFamily);

        try {
            $service->delete($itemFamily);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
