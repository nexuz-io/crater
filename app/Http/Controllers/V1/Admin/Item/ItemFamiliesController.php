<?php

namespace Crater\Http\Controllers\V1\Admin\Item;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\ItemFamilyRequest;
use Crater\Http\Resources\ItemFamilyResource;
use Crater\Models\ItemFamily;
use Crater\Services\ItemFamilyService;
use Illuminate\Http\Request;

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

        $family = $service->create(
            $request->getFamilyPayload(),
            (int) $request->header('company')
        );

        return new ItemFamilyResource($family);
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

        $family = $service->update($itemFamily, $request->getFamilyPayload());

        return new ItemFamilyResource($family);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(ItemFamilyService $service, ItemFamily $itemFamily)
    {
        $this->authorize('delete', $itemFamily);

        $service->delete($itemFamily);

        return response()->json([
            'success' => true,
        ]);
    }
}
