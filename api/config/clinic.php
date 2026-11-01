<?php

return [
    // Shown on the emergency button as a tap-to-call link.
    'phone' => env('CLINIC_PHONE'),

    // FR-3.4: staff see a ticket as overdue when nobody has acknowledged it by then.
    'emergency_sla_minutes' => (int) env('EMERGENCY_SLA_MINUTES', 15),
];
