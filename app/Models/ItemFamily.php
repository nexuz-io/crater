<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ItemFamily extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Self-referencing adjacency list. Each row is either a root
     * (parent_id NULL) or a child of another row in the same company.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function items()
    {
        // Migration names the FK `family_id` (not the default
        // `item_family_id`). Declare it explicitly so the relation's
        // generated SQL stays clean, the exists() guard in
        // ItemFamilyService::delete works, and eager loading does
        // not produce spurious column-not-found failures.
        return $this->hasMany(Item::class, 'family_id');
    }

    public function scopeWhereCompany($query)
    {
        $query->where('company_id', request()->header('company'));
    }

    public function scopeWhereSearch($query, $search)
    {
        return $query->where('name', 'LIKE', '%'.$search.'%');
    }

    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWhereParent($query, $parentId)
    {
        $query->where('parent_id', $parentId);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('parent_id') !== null && $filters->get('parent_id') !== '') {
            $query->whereParent($filters->get('parent_id'));
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ?: 'name';
            $orderBy = $filters->get('orderBy') ?: 'asc';
            $query->whereOrder($field, $orderBy);
        }

        return $query;
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    /**
     * Normalisation helpers used by both the service layer and the
     * request validation. Centralised in the model so the
     * BrandFamilyFactory + the seeder (when it exists) cannot
     * diverge.
     */
    public static function cleanName(string $name): string
    {
        return trim($name);
    }

    public static function normalizeName(string $name): string
    {
        return Str::lower(trim($name));
    }

    /**
     * Persists the trimmed name and its normalised comparison key on
     * the model. Called by `ItemFamilyService` and tests.
     */
    public function applyName(string $name): void
    {
        $clean = self::cleanName($name);
        $this->name = $clean;
        $this->name_normalized = self::normalizeName($clean);
    }
}
