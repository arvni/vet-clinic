<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(RoleSeeder::class);
        // Symfony's test client defaults to en-us; the product default is Persian.
        $this->withHeader('Accept-Language', 'fa');
    })
    ->in('Feature');
