<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccessSeeder::class,
            DevelopmentSeeder::class, // local environment only
            DemoDataSeeder::class,    // local environment only: three months of realistic trading data
        ]);
    }
}
