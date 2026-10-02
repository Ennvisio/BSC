<?php

namespace App;

use Illuminate\Support\Collection;

/**
 * The dashboard's "Validity of Certificates" table: one summary row per
 * vessel (how many of its certificates are expired, due soon, valid), each
 * expanding to that vessel's certificates grouped by category.
 *
 * Replaces the old vessels x certificate-types grid, which only worked while
 * every vessel was measured against the same fixed list of certificate
 * names (the legacy `certificates` table). Titles are now typed per vessel
 * under a category, so there is no shared column set to build a grid from.
 */
class CertificateValidityReport
{
    const UNCATEGORISED = 'Uncategorised';

    /**
     * @param  Collection<int,Vessel>  $vessels
     * @return array<int,array<string,mixed>> one entry per vessel, most urgent first
     */
    public static function forVessels(Collection $vessels): array
    {
        $certificates = VesselCertificate::with('category')
            ->where('status', true)
            ->whereIn('vessel_id', $vessels->pluck('id'))
            ->get()
            ->groupBy('vessel_id');

        $categoryOrder = CertificateCategory::orderBy('id')->pluck('name')->flip();

        $rows = $vessels->map(function ($vessel) use ($certificates, $categoryOrder) {
            $current = self::latestRenewals($certificates->get($vessel->id, collect()));

            $counts = ['expired' => 0, 'due' => 0, 'valid' => 0, 'unknown' => 0];
            foreach ($current as $certificate) {
                $status = $certificate->validityStatus();
                // Permanent never runs out, so for "does this need attention"
                // it is simply valid.
                $counts[$status === 'permanent' ? 'valid' : $status]++;
            }

            return [
                'vessel' => $vessel,
                'total' => $current->count(),
                'expired' => $counts['expired'],
                'due' => $counts['due'],
                'valid' => $counts['valid'],
                'unknown' => $counts['unknown'],
                'next_expiry' => self::nextExpiry($current),
                'all_permanent' => $current->isNotEmpty() && $current->every(fn ($c) => $c->is_permanent),
                'groups' => self::groupByCategory($current, $categoryOrder),
            ];
        });

        // Needs-attention first: most expired, then most due, then by name -
        // the point of this table is "which ship do I chase today".
        return $rows->sort(function ($a, $b) {
            return [$b['expired'], $b['due'], $a['vessel']->name]
                <=> [$a['expired'], $a['due'], $b['vessel']->name];
        })->values()->all();
    }

    /**
     * One row per certificate, not per renewal. Older data carries each
     * renewal as its own row (SAMRIDDHI has the same Safety Equipment
     * certificate from 2019, 2023 and 2026) - counting all of them would
     * report two expired certificates the vessel doesn't actually have. Only
     * the latest expiry of each counts; a permanent one outranks any date.
     */
    private static function latestRenewals(Collection $certificates): Collection
    {
        return $certificates
            ->groupBy(fn ($c) => ($c->category_id ?? 'none').'|'.mb_strtolower(trim((string) $c->title)))
            ->map(fn ($renewals) => $renewals->sortByDesc(
                fn ($c) => $c->is_permanent ? '9999-12-31' : (string) $c->exp_date
            )->first())
            ->values();
    }

    /** The soonest expiry still ahead - overdue ones are the Expired column's job. */
    private static function nextExpiry(Collection $certificates): ?string
    {
        return $certificates
            ->filter(fn ($c) => ($days = $c->daysUntilExpiry()) !== null && $days >= 0)
            ->min('exp_date');
    }

    /**
     * Categories in super-admin's own list order, Uncategorised (legacy rows
     * recorded before categories existed) last; soonest expiry first within
     * each, permanent at the bottom.
     */
    private static function groupByCategory(Collection $certificates, Collection $categoryOrder): array
    {
        return $certificates
            ->groupBy(fn ($c) => $c->category->name ?? self::UNCATEGORISED)
            ->sortBy(fn ($group, $name) => $categoryOrder[$name] ?? PHP_INT_MAX)
            ->map(fn ($group) => $group->sortBy(
                fn ($c) => $c->is_permanent ? '9999-12-31' : ((string) $c->exp_date ?: '9999-12-30')
            )->values())
            ->all();
    }
}
