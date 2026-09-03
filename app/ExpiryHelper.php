<?php

namespace App;

class ExpiryHelper
{
    /**
     * CSS class for an expiry-date cell based on how many whole days remain:
     * more than 30 days -> ok (green), 16-30 days -> warn (yellow),
     * 15 days or fewer, including already expired -> danger (red).
     */
    public static function cssClass($date): string
    {
        if (empty($date)) {
            return '';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }

        $daysRemaining = (int) floor(
            (strtotime(date('Y-m-d', $timestamp)) - strtotime(date('Y-m-d'))) / 86400
        );

        if ($daysRemaining > 30) {
            return 'exp-ok';
        }

        if ($daysRemaining > 15) {
            return 'exp-warn';
        }

        return 'exp-danger';
    }
}
