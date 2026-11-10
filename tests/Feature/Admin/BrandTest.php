<?php

use Crater\Http\Controllers\V1\Admin\Item\BrandsController;
use Crater\Http\Requests\BrandRequest;
use Crater\Models\Brand;
use Crater\Models\Item;
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
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('get brands', function () {
    $response = getJson('api/v1/brands?page=1');

    $response->assertOk();
});

test('create brand', function () {
    $data = [
        'name' => 'Sony',
    ];

    $response = postJson('api/v1/brands', $data);

    $response->assertOk();

    $this->assertDatabaseHas('brands', [
        'name' => 'Sony',
        'name_normalized' => Brand::normalizeName('Sony'),
    ]);
});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        BrandsController::class,
        'store',
        BrandRequest::class
    );
});

test('get brand', function () {
    $brand = Brand::factory()->create();

    $response = getJson("api/v1/brands/{$brand->id}");

    $response->assertOk();
});

test('update brand', function () {
    $brand = Brand::factory()->create();

    $response = putJson("api/v1/brands/{$brand->id}", [
        'name' => 'New name',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('brands', [
        'id' => $brand->id,
        'name' => 'New name',
    ]);
});

test('update validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        BrandsController::class,
        'update',
        BrandRequest::class
    );
});

test('delete brand without items', function () {
    $brand = Brand::factory()->create();

    $response = deleteJson("api/v1/brands/{$brand->id}");

    $response->assertOk();

    $this->assertModelMissing($brand);
});

test('cannot delete brand attached to items', function () {
    $brand = Brand::factory()->create();
    Item::factory()->create(['brand_id' => $brand->id]);

    // Controller returns 422 via respondJson when the brand is in
    // use; the row must remain in the DB.
    $response = deleteJson("api/v1/brands/{$brand->id}");

    $response->assertStatus(422);
    $response->assertJson(['error' => 'brand_attached_items']);

    $this->assertDatabaseHas('brands', [
        'id' => $brand->id,
    ]);
});

test('brand name unique per company', function () {
    Brand::factory()->create([
        'name' => 'Sony',
        'name_normalized' => 'sony',
    ]);

    postJson('api/v1/brands', ['name' => 'sony'])->assertStatus(422);

    // Whitespace + normalised test: 'Sony ' must collide.
    postJson('api/v1/brands', ['name' => '  Sony  '])->assertStatus(422);
});
