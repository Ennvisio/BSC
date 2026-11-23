<?php

namespace App\Sync\Engine\Hooks;

use App\Services\CatalogService;
use App\Sync\Engine\ApplyHook;
use App\Sync\Engine\ReceivingSide;

/**
 * An item's identity in the catalog is items.item_key, a hash of its article
 * number, item group and name (CatalogService::itemKey). The group is a
 * local id, which differs between servers, so the key is never sent: each
 * server works it out from its own id for the same group. A received item
 * with the same key as one already here is the same item, and is merged
 * into it ('on_duplicate' => 'merge'), exactly as a catalog import would.
 */
class ItemKeyHook implements ApplyHook
{
    public function prepare(string $table, array $values, ?object $local, ReceivingSide $side): array
    {
        $article = array_key_exists('article_number', $values) ? $values['article_number'] : ($local->article_number ?? null);
        $group = array_key_exists('item_group_id', $values) ? $values['item_group_id'] : ($local->item_group_id ?? null);
        $name = array_key_exists('name', $values) ? $values['name'] : ($local->name ?? null);

        if ($article === null || $name === null) {
            // A legacy item without an article number: no catalog identity
            // to compute. A copy already here keeps whatever key it has.
            if ($local === null) {
                $values['item_key'] = null;
            }

            return $values;
        }

        $values['item_key'] = CatalogService::itemKey((string) $article, $group === null ? null : (int) $group, (string) $name);

        return $values;
    }

    public function applied(string $table, array $event, string $source, ?object $before, ?object $after, ReceivingSide $side): void
    {
    }
}
