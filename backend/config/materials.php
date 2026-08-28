<?php

return [
    /*
     * A material is flagged "high consumption" for a given day if that
     * day's total ISSUE quantity at a site exceeds the trailing 30-day daily
     * average issue quantity by this multiplier. Organization-configurable
     * in the future via organization.settings; a sane global default for
     * now.
     */
    'high_consumption_multiplier' => env('MATERIAL_HIGH_CONSUMPTION_MULTIPLIER', 2.0),

    'consumption_lookback_days' => 30,
];
