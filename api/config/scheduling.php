<?php

return [
    // Every slot starts on this grid, and holds lock cells of this size.
    'step_minutes' => (int) env('SCHEDULING_STEP_MINUTES', 15),

    // FR-3.1: a slot stays held for this long during checkout.
    'hold_minutes' => (int) env('SCHEDULING_HOLD_MINUTES', 10),

    // How far ahead owners may search.
    'horizon_days' => (int) env('SCHEDULING_HORIZON_DAYS', 60),
];
