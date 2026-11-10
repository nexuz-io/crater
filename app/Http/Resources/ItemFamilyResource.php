<?php

namespace Crater\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ItemFamilyResource extends JsonResource
{
    /**
     * Flat per-company tree node. The list endpoint returns this for
     * each row so the client can build the tree client-side without
     * needing recursive resources or per-node fetches.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'company' => $this->when($this->company()->exists(), function () {
                return new CompanyResource($this->company);
            }),
            'parent' => $this->when($this->parent()->exists(), function () {
                return new self($this->parent);
            }),
        ];
    }
}
