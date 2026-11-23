<?php

namespace App\Sync\Engine;

/**
 * What differs between the cloud and a vessel when applying received
 * changes: which tables they accept, what counts as a local change the
 * sender has not seen yet, and which rows the sender may touch.
 */
interface ReceivingSide
{
    /** cloud or vessel (ReplicationRules::CLOUD / ::VESSEL) */
    public function name(): string;

    public function accepts(string $table): bool;

    /**
     * The newest local change to this row that the sender has not received
     * yet, or null. Both sides changing a row before seeing each other's
     * change is what a conflict is.
     */
    public function pendingLocalChange(string $table, string $rowUuid): ?object;

    /**
     * Throws InvalidChange if the sender may not touch this row: checked on
     * the row as it was ($idBefore) and as it would become ($idAfter).
     */
    public function checkScope(string $table, array $event, int|string|null $idBefore, int|string|null $idAfter): void;
}
