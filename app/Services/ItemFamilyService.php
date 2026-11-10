<?php

namespace Crater\Services;

use Crater\Models\ItemFamily;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Centralised, transaction-safe operations on the item-family tree.
 *
 * Responsibilities:
 *   1. Validate uniqueness of normalised names "among siblings"
 *      (parent_id aware), including the case where the conflicting
 *      row is the same one being edited (no false positive on PUT).
 *   2. Reject `parent_id` mutations that would create a cycle —
 *      either setting a node as its own parent or moving a branch
 *      under one of its descendants.
 *   3. Reject delete on a family with children or items attached.
 *   4. Persist the change in a single database transaction with a
 *      `lockForUpdate()` on the parent row (or company row when the
 *      mutation is at root level) to serialise concurrent writers.
 *
 * The service is deliberately stateless — a single instance is safe
 * to use per request.
 */
class ItemFamilyService
{
    /**
     * Create a root or child family. Returns the persisted model.
     */
    public function create(array $data, int $companyId): ItemFamily
    {
        return DB::transaction(function () use ($data, $companyId) {
            $parentId = $this->normaliseParentId($data['parent_id'] ?? null);

            if ($parentId !== null) {
                $this->lockParentOrCompany($parentId, $companyId);
                $this->assertParentBelongsToCompany($parentId, $companyId);
            } else {
                // Roots can collide with other roots; serialise per
                // company by locking the company row.
                $this->lockCompany($companyId);
            }

            $name = ItemFamily::cleanName($data['name']);
            $normalized = ItemFamily::normalizeName($name);

            $this->assertNoSiblingWithName($companyId, $parentId, $normalized, null);

            $family = new ItemFamily();
            $family->company_id = $companyId;
            $family->parent_id = $parentId;
            $family->applyName($name);
            $family->save();

            return $family;
        });
    }

    /**
     * Rename and optionally re-parent an existing family. Returns the
     * persisted model.
     */
    public function update(ItemFamily $family, array $data): ItemFamily
    {
        return DB::transaction(function () use ($family, $data) {
            // Always lock against the family itself first: even
            // unrelated renames must not race another rename on the
            // same row.
            $this->lockFamily($family->id);
            $this->lockCompany($family->company_id);

            $parentId = array_key_exists('parent_id', $data)
                ? $this->normaliseParentId($data['parent_id'])
                : $family->parent_id;

            if ($parentId !== null && $parentId === $family->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A family cannot be its own parent.',
                ]);
            }

            if ($parentId !== $family->parent_id) {
                if ($parentId !== null) {
                    $this->assertParentBelongsToCompany($parentId, $family->company_id);
                    $this->assertNotDescendantOf($family->id, $parentId, $family->company_id);
                }
                $family->parent_id = $parentId;
            }

            if (array_key_exists('name', $data)) {
                $name = ItemFamily::cleanName($data['name']);
                $normalized = ItemFamily::normalizeName($name);

                if (
                    $normalized !== ($family->name_normalized ?? null)
                    || $parentId !== $family->getOriginal('parent_id')
                ) {
                    $this->assertNoSiblingWithName(
                        $family->company_id,
                        $parentId,
                        $normalized,
                        $family->id
                    );
                }

                $family->applyName($name);
            }

            $family->save();

            return $family;
        });
    }

    /**
     * Delete a family. Throws a ValidationException when children or
     * items are still attached (the operator-visible delete button
     * already filters this out, but defensive guards prevent API
     * misuse).
     */
    public function delete(ItemFamily $family): void
    {
        DB::transaction(function () use ($family) {
            $this->lockFamily($family->id);

            if ($family->children()->exists()) {
                throw ValidationException::withMessages([
                    'family' => 'This family has subfamilies and cannot be deleted.',
                ]);
            }

            if ($family->items()->exists()) {
                throw ValidationException::withMessages([
                    'family' => 'This family has services attached and cannot be deleted.',
                ]);
            }

            $family->delete();
        });
    }

    /**
     * Returns the descendant ids of the given family within the
     * company, BFS-traversed with a visited set to defend against
     * any corrupt cycle that may have slipped in before the service
     * existed. Includes the starting id itself so callers can adopt
     * the "branch" semantics without a separate query.
     */
    public static function descendantIds(int $familyId, int $companyId): array
    {
        $collected = [$familyId];
        $visited = [$familyId => true];

        $current = [$familyId];

        // Hard cap defends against pathological tree sizes; the
        // controller still returns progress to the request loop.
        $depth = 0;
        $maxDepth = 128;

        while (! empty($current) && $depth < $maxDepth) {
            $children = DB::table('item_families')
                ->where('company_id', $companyId)
                ->whereIn('parent_id', $current)
                ->pluck('id')
                ->all();

            $next = [];
            foreach ($children as $childId) {
                if (isset($visited[$childId])) {
                    continue;
                }
                $visited[$childId] = true;
                $collected[] = $childId;
                $next[] = $childId;
            }

            if (empty($next)) {
                break;
            }

            $current = $next;
            $depth++;
        }

        return $collected;
    }

    /**
     * Lock the family row by `id`. Falls back to lockForUpdate which
     * returns an affected row only under MySQL/InnoDB; on engines
     * without row-level locking the surrounding transaction still
     * provides serialisable semantics through the company lock.
     */
    protected function lockFamily(int $familyId): void
    {
        DB::table('item_families')->where('id', $familyId)->lockForUpdate()->first();
    }

    protected function lockCompany(int $companyId): void
    {
        DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
    }

    /**
     * Lock the parent row (when the operation creates or moves a
     * child) or, when there is no parent (root creation), fall back
     * to the company lock.
     */
    protected function lockParentOrCompany(?int $parentId, int $companyId): void
    {
        if ($parentId !== null) {
            $this->lockFamily($parentId);

            return;
        }

        $this->lockCompany($companyId);
    }

    protected function assertParentBelongsToCompany(int $parentId, int $companyId): void
    {
        $exists = DB::table('item_families')
            ->where('id', $parentId)
            ->where('company_id', $companyId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent family not found in this company.',
            ]);
        }
    }

    /**
     * Cycle guard: refuse moves that would push a node under one of
     * its own descendants.
     */
    protected function assertNotDescendantOf(int $candidateId, int $candidateParentId, int $companyId): void
    {
        if ($candidateId === $candidateParentId) {
            throw ValidationException::withMessages([
                'parent_id' => 'A family cannot be its own parent.',
            ]);
        }

        // Walk the proposed parent's ancestors; if `candidateId`
        // appears, the move would create a cycle.
        $cursor = $candidateParentId;
        $visited = [$cursor => true];

        // Same depth bound as `descendantIds` to guard against
        // pathological tree shapes coming in.
        $depth = 0;
        $maxDepth = 128;

        while ($cursor !== null && $depth < $maxDepth) {
            $cursor = DB::table('item_families')
                ->where('company_id', $companyId)
                ->where('id', $cursor)
                ->value('parent_id');

            if ($cursor === null) {
                break;
            }

            if ((int) $cursor === $candidateId) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A family cannot be moved under one of its descendants.',
                ]);
            }

            if (isset($visited[$cursor])) {
                // Anti-loop guard: a row marked its own ancestor
                // already, treat the move as a cycle.
                throw ValidationException::withMessages([
                    'parent_id' => 'Corrupt cycle detected in the existing tree.',
                ]);
            }
            $visited[$cursor] = true;

            $depth++;
        }
    }

    /**
     * Sibling-uniqueness guard. Uses `name_normalized` (a trimmed,
     * lowercased copy) so case / whitespace variants collide.
     */
    protected function assertNoSiblingWithName(
        int $companyId,
        ?int $parentId,
        string $normalizedName,
        ?int $ignoreId
    ): void {
        $query = DB::table('item_families')
            ->where('company_id', $companyId)
            ->where('name_normalized', $normalizedName);

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A family with this name already exists at the same level.',
            ]);
        }
    }

    protected function normaliseParentId($value): ?int
    {
        if ($value === null || $value === '' || $value === '0') {
            return null;
        }

        return (int) $value;
    }
}
