<?php

namespace Crater\Http\Controllers\V1\Admin\Item;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\BrandRequest;
use Crater\Http\Resources\BrandResource;
use Crater\Models\Brand;
use Illuminate\Http\Request;

class BrandsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Brand::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $brands = Brand::applyFilters($request->all())
            ->whereCompany()
            ->latest()
            ->paginateData($limit);

        return BrandResource::collection($brands);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(BrandRequest $request)
    {
        $this->authorize('create', Brand::class);

        // Refresh the unique check in case the request bypassed the
        // validation layer. Leaving it on validated() is fine because
        // every write path uses BrandRequest; this is a defensive
        // guard against programmatic creation.
        $payload = Brand::payloadForCreate(
            $request->validated(),
            (int) $request->header('company')
        );

        $brand = Brand::create($payload);

        return new BrandResource($brand);
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Brand $brand)
    {
        $this->authorize('view', $brand);

        return new BrandResource($brand);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(BrandRequest $request, Brand $brand)
    {
        $this->authorize('update', $brand);

        $payload = $request->getBrandPayload();

        $brand->name = $payload['name'];
        $brand->name_normalized = Brand::normalizeName($payload['name']);
        $brand->save();

        return new BrandResource($brand);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(BrandRequest $request, Brand $brand)
    {
        $this->authorize('delete', $brand);

        // Reject deletion when items still reference the brand —
        // including inactive ones, since the operator can revive them
        // from the trash-like toggle in the UI.
        if ($brand->items()->exists()) {
            return respondJson('brand_attached_items', 'This brand is used by one or more services and cannot be deleted.');
        }

        $brand->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
