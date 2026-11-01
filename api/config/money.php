<?php

return [
    /*
    | How amounts (always stored in current rials) are shown to people.
    | toman: rials / 10 (today's everyday unit)
    | new_rial: rials / 10,000 (post-redenomination unit)
    */
    'display_mode' => env('MONEY_DISPLAY_MODE', 'toman'),
];
