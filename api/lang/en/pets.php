<?php

return [
    'species' => [
        'dog' => 'Dog',
        'cat' => 'Cat',
        'bird' => 'Bird',
        'rabbit' => 'Rabbit',
        'rodent' => 'Rodent',
        'reptile' => 'Reptile',
        'other' => 'Other',
    ],
    'sex' => ['male' => 'Male', 'female' => 'Female', 'unknown' => 'Unknown'],
    'allergy_type' => ['drug' => 'Drug', 'food' => 'Food', 'other' => 'Other'],
    'document_kind' => [
        'national' => 'National pet ID',
        'clinic_id' => 'Clinic pet ID',
        'certificate' => 'Health certificate',
        'other' => 'Other',
    ],
    'breed_species_mismatch' => 'The selected breed does not match the species.',
    'national_number_taken' => 'This national document number is already registered to another pet.',
    'transfer' => [
        'self' => 'You cannot transfer a pet to yourself.',
        'not_open' => 'This transfer request is no longer valid.',
        'pending_exists' => 'This pet already has a pending transfer.',
    ],
];
