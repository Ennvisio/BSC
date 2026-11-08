<?php

namespace Tests\Feature\ManualEntry;

use App\Item;
use App\ItemGroup;
use App\StockMovement;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/**
 * The "Add item manually" tab on /catalog/import
 * (ItemImportController::storeManual()) - same CatalogService the bulk
 * Excel import uses (App\Imports\ItemCatalogImport), one item at a time.
 */
class ManualCatalogItemTest extends TestCase
{
    use BuildsRequisitionSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
    }

    public function test_creates_a_new_item_with_a_new_group_path(): void
    {
        $category = $this->makeCategory();
        $officer = $this->makeUser('chief-officer', $this->vesselId);

        $response = $this->actingAs($officer)->postJson('/catalog/import/manual', [
            'category_id' => $category->id,
            'item_group_path' => 'Provisions -> Beans and Peas, Dry -> BEANS BROAD DRY',
            'article_number' => 'ART-NEW-001',
            'name' => 'Beans Broad Dry',
            'unit' => 'KG',
        ])->assertOk();

        $item = Item::where('article_number', 'ART-NEW-001')->first();
        $this->assertNotNull($item);
        $this->assertSame('Beans Broad Dry', $item->name);
        $this->assertSame('KG', $item->unit);
        $this->assertSame('-', $item->impa_code);
        $this->assertSame($category->id, $item->category_id);

        $leafGroup = ItemGroup::findOrFail($item->item_group_id);
        $this->assertSame('BEANS BROAD DRY', $leafGroup->name);
        $this->assertSame('Provisions -> Beans and Peas, Dry -> BEANS BROAD DRY', $leafGroup->path);

        $parent = ItemGroup::findOrFail($leafGroup->parent_id);
        $this->assertSame('Beans and Peas, Dry', $parent->name);
        $grandparent = ItemGroup::findOrFail($parent->parent_id);
        $this->assertSame('Provisions', $grandparent->name);
        $this->assertNull($grandparent->parent_id);

        $this->assertTrue(
            DB::table('vessel_items')->where('vessel_id', $this->vesselId)->where('item_id', $item->id)->exists()
        );
    }

    public function test_an_existing_article_number_links_instead_of_duplicating(): void
    {
        $category = $this->makeCategory();
        $existing = $this->makeItem($category->id, ['article_number' => 'ART-DUP-001', 'name' => 'Existing Widget']);
        $officer = $this->makeUser('chief-officer', $this->vesselId);

        // First attempt, no existing_item_id yet - refused with the item to link.
        $refused = $this->actingAs($officer)->postJson('/catalog/import/manual', [
            'category_id' => $category->id,
            'item_group_path' => 'Some -> New -> Path',
            'article_number' => 'ART-DUP-001',
            'name' => 'Whatever the officer typed',
        ])->assertStatus(409);

        $this->assertSame($existing->id, $refused->json('existing_item.id'));
        $this->assertSame(1, Item::where('article_number', 'ART-DUP-001')->count());

        // Second attempt, with existing_item_id set - links instead of creating.
        $this->actingAs($officer)->postJson('/catalog/import/manual', [
            'category_id' => $category->id,
            'article_number' => 'ART-DUP-001',
            'existing_item_id' => $existing->id,
        ])->assertOk();

        $this->assertSame(1, Item::where('article_number', 'ART-DUP-001')->count(), 'no duplicate item should have been created');
        $this->assertTrue(
            DB::table('vessel_items')->where('vessel_id', $this->vesselId)->where('item_id', $existing->id)->exists()
        );
    }

    public function test_ship_user_cannot_add_to_another_vessel(): void
    {
        $otherVesselId = $this->makeVessel('MT OTHER VESSEL')->id;
        $category = $this->makeCategory();
        $officer = $this->makeUser('chief-officer', $this->vesselId);

        // A ship user's own vessel always wins, same rule the Excel import
        // already enforces - whatever vessel_id they send is ignored.
        $this->actingAs($officer)->postJson('/catalog/import/manual', [
            'vessel_id' => $otherVesselId,
            'category_id' => $category->id,
            'item_group_path' => 'A -> B',
            'article_number' => 'ART-OWN-VESSEL',
            'name' => 'Own Vessel Only',
        ])->assertOk();

        $item = Item::where('article_number', 'ART-OWN-VESSEL')->firstOrFail();

        $this->assertTrue(
            DB::table('vessel_items')->where('vessel_id', $this->vesselId)->where('item_id', $item->id)->exists()
        );
        $this->assertFalse(
            DB::table('vessel_items')->where('vessel_id', $otherVesselId)->where('item_id', $item->id)->exists()
        );
    }

    public function test_masters_opening_stock_writes_an_opening_movement(): void
    {
        $category = $this->makeCategory();
        $master = $this->makeUser('master', $this->vesselId);

        $this->actingAs($master)->postJson('/catalog/import/manual', [
            'category_id' => $category->id,
            'item_group_path' => 'A -> B',
            'article_number' => 'ART-OPENING-001',
            'name' => 'Opening Stock Item',
            'opening_stock' => 25,
            'min_qty' => 5,
        ])->assertOk();

        $item = Item::where('article_number', 'ART-OPENING-001')->firstOrFail();

        $movement = StockMovement::where('item_id', $item->id)->where('vessel_id', $this->vesselId)->sole();
        $this->assertSame(StockMovement::TYPE_OPENING, $movement->type);
        $this->assertSame(25, $movement->qty);

        $vesselItem = DB::table('vessel_items')->where('vessel_id', $this->vesselId)->where('item_id', $item->id)->first();
        $this->assertSame(25, (int) $vesselItem->opening_stock);
        $this->assertSame(5, (int) $vesselItem->min_qty);
    }

    public function test_a_non_master_ship_role_does_not_write_opening_stock_even_if_sent(): void
    {
        $category = $this->makeCategory();
        $officer = $this->makeUser('chief-officer', $this->vesselId);

        $this->actingAs($officer)->postJson('/catalog/import/manual', [
            'category_id' => $category->id,
            'item_group_path' => 'A -> B',
            'article_number' => 'ART-NO-OPENING',
            'name' => 'No Opening For This Role',
            'opening_stock' => 99,
        ])->assertOk();

        $item = Item::where('article_number', 'ART-NO-OPENING')->firstOrFail();

        $this->assertSame(0, StockMovement::where('item_id', $item->id)->count());
    }
}
