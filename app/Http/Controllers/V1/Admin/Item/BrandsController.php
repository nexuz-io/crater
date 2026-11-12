<?php

namespace Crater\Http\Controllers\V1\Admin\Item;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\BrandRequest;
use Crater\Http\Resources\BrandResource;
use Crater\Models\Brand;
use Illuminate\Database\UniqueConstraintViolationException;
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

        try {
            $brand = Brand::create($payload);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // La Rule::unique del BrandRequest no captura esta
            // colision en todos los motores SQLite; caemos a 422
            // cuando la FK constraint dispara al INSERT.
            return response()->json([
                'error' => 'brand_name_unique',
                'message' => 'A brand with this name already exists in this company.',
            ], 422);
        }

        // Plan 09 hotfix: POST devuelve 200 OK en lugar del 201
        // por defecto de Laravel cuando un Resource se persiste.
        // La convencion del proyecto usa 200 para create/update/delete
        // exitos y devuelve `success: true`.
        return (new BrandResource($brand))->response()->setStatusCode(200);
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

        // Plan 09 hotfix: PUT devuelve 200 OK explícito.
        return (new BrandResource($brand))->response()->setStatusCode(200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Brand $brand)
    {
        $this->authorize('delete', $brand);

        // Reject deletion when items still reference the brand —
        // including inactive ones, since the operator can revive them
        // from the trash-like toggle in the UI. BrandRequest is NOT
        // injected here because DELETE has no body to validate and the
        // brand payload required by the create flow does not apply.
        if ($brand->items()->exists()) {
            return respondJson('brand_attached_items', 'This brand is used by one or more services and cannot be deleted.');
        }

        $brand->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
