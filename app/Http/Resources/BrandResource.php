<?php

namespace Crater\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    /**
     * Mirrors UnitResource: a brand is a flat per-company entity whose
     * public shape only needs `id` + `name`. `name_normalized` is kept
     * intentionally out of the API since it's an implementation detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_id' => $this->company_id,
            'company' => $this->when($this->company()->exists(), function () {
                return new CompanyResource($this->company);
            }),
        ];
    }
}
