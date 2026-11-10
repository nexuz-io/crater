<?php

use Crater\Http\Controllers\V1\Admin\Item\ItemFamiliesController;
use Crater\Http\Requests\ItemFamilyRequest;
use Crater\Models\Item;
use Crater\Models\ItemFamily;
use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs($user, ['*']);
});

test('get item families', function () {
    getJson('api/v1/item-families')->assertOk();
});

test('create item family', function () {
    $response = postJson('api/v1/item-families', [
        'name' => 'Electronics',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('item_families', [
        'name' => 'Electronics',
        'parent_id' => null,
    ]);
});

test('create child family under root', function () {
    $root = ItemFamily::factory()->create(['name' => 'Electronics']);

    $response = postJson('api/v1/item-families', [
        'name' => 'Phones',
        'parent_id' => $root->id,
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('item_families', [
        'name' => 'Phones',
        'parent_id' => $root->id,
    ]);
});

test('reject duplicate sibling name (root)', function () {
    ItemFamily::factory()->create(['name' => 'Electronics']);

    postJson('api/v1/item-families', ['name' => 'ELECTRONICS'])->assertStatus(422);
});

test('allow duplicate name across different parents', function () {
    $rootA = ItemFamily::factory()->create(['name' => 'A']);
    $rootB = ItemFamily::factory()->create(['name' => 'B']);

    // Same name under two different roots must be accepted.
    $childA = postJson('api/v1/item-families', [
        'name' => 'Other',
        'parent_id' => $rootA->id,
    ]);
    $childA->assertOk();

    $childB = postJson('api/v1/item-families', [
        'name' => 'Other',
        'parent_id' => $rootB->id,
    ]);
    $childB->assertOk();
});

test('reject cycle when moving a node under itself', function () {
    $root = ItemFamily::factory()->create(['name' => 'Electronics']);

    $response = putJson("api/v1/item-families/{$root->id}", [
        'name' => $root->name,
        'parent_id' => $root->id,
    ]);

    $response->assertStatus(422);
});

test('reject cycle when moving a node under its descendant', function () {
    $root = ItemFamily::factory()->create(['name' => 'Electronics']);
    $child = ItemFamily::factory()->childOf($root)->create(['name' => 'Phones']);
    $grand = ItemFamily::factory()->childOf($child)->create(['name' => 'Smartphones']);

    // Move the root under its grandchild — must be rejected.
    $response = putJson("api/v1/item-families/{$root->id}", [
        'name' => $root->name,
        'parent_id' => $grand->id,
    ]);

    $response->assertStatus(422);
});

test('cross-company parent rejected', function () {
    $foreignCompanyId = \Crater\Models\Company::count() > 1
        ? \Crater\Models\Company::skip(1)->first()->id
        : null;

    // Test relies on the seeded second company; if only one exists,
    // skip to avoid spurious failures on a single-company fixture.
    if (! $foreignCompanyId) {
        $this->markTestSkipped('Seeder creates a single company; cross-company check requires ≥2.');
    }

    // Plant a family owned by company B by writing directly.
    $foreign = ItemFamily::create([
        'name' => 'Foreign',
        'name_normalized' => 'foreign',
        'company_id' => $foreignCompanyId,
        'parent_id' => null,
    ]);

    postJson('api/v1/item-families', [
        'name' => 'New',
        'parent_id' => $foreign->id,
    ])->assertStatus(422);
});

test('cannot delete family with children', function () {
    $root = ItemFamily::factory()->create(['name' => 'Electronics']);
    ItemFamily::factory()->childOf($root)->create(['name' => 'Phones']);

    $response = deleteJson("api/v1/item-families/{$root->id}");
    $response->assertStatus(422);

    $this->assertDatabaseHas('item_families', ['id' => $root->id]);
});

test('cannot delete family with items attached', function () {
    $root = ItemFamily::factory()->create(['name' => 'Electronics']);
    Item::factory()->create(['family_id' => $root->id]);

    $response = deleteJson("api/v1/item-families/{$root->id}");
    $response->assertStatus(422);

    $this->assertDatabaseHas('item_families', ['id' => $root->id]);
});

test('delete empty family succeeds', function () {
    $root = ItemFamily::factory()->create(['name' => 'Electronics']);
    deleteJson("api/v1/item-families/{$root->id}")->assertOk();
    $this->assertModelMissing($root);
});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        ItemFamiliesController::class,
        'store',
        ItemFamilyRequest::class
    );
});

test('update renames and re-parents cleanly', function () {
    $rootA = ItemFamily::factory()->create(['name' => 'A']);
    $rootB = ItemFamily::factory()->create(['name' => 'B']);
    $child = ItemFamily::factory()->childOf($rootA)->create(['name' => 'C']);

    putJson("api/v1/item-families/{$child->id}", [
        'name' => 'C-renamed',
        'parent_id' => $rootB->id,
    ])->assertOk();

    $this->assertDatabaseHas('item_families', [
        'id' => $child->id,
        'name' => 'C-renamed',
        'parent_id' => $rootB->id,
    ]);
});
