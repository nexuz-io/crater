<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Each brand belongs to exactly one company; brand names are unique
     * within that company only.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
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

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
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
     * Returns the values that should be persisted alongside the raw
     * name: validated, trimmed, with the normalized comparison key.
     *
     * Centralising this in the model keeps the request/controller
     * layers thin and prevents validation/normalization from drifting
     * between the API and any seeds or migrations.
     */
    public static function payloadForCreate(array $data, int $companyId): array
    {
        $name = self::cleanName($data['name'] ?? '');

        return [
            'name' => $name,
            'name_normalized' => self::normalizeName($name),
            'company_id' => $companyId,
        ];
    }

    public static function normalizeName(string $name): string
    {
        return Str::lower(trim($name));
    }

    public static function cleanName(string $name): string
    {
        return trim($name);
    }
}
