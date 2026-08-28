<?php

return [
    /*
     * A (equipment) is flagged "high consumption" for a given day if that
     * day's total ISSUE quantity exceeds the trailing lookback-period daily
     * average by this multiplier — same pattern as MaterialAlertService.
     */
    'high_consumption_multiplier' => env('FUEL_HIGH_CONSUMPTION_MULTIPLIER', 2.0),

    'consumption_lookback_days' => 30,

    /*
     * Absolute, organization-configurable ceiling on a single day's fuel
     * quantity issued to one piece of equipment, regardless of trailing
     * history (brief §17: "Consumption above configured threshold"). Read
     * from organization.settings.fuel_max_daily_quantity when present;
     * this is the fallback when the organization hasn't configured one.
     */
    'default_max_daily_quantity' => env('FUEL_DEFAULT_MAX_DAILY_QUANTITY', null),
];
