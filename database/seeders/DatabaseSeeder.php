<?php

namespace Database\Seeders;

use App\Models\Theaters;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        Theaters::insert([
            'name' => 'ДОНЕЦКИЙ ГОСУДАРСТВЕННЫЙ АКАДЕМИЧЕСКИЙ МУЗЫКАЛЬНО-ДРАМАТИЧЕСКИЙ ТЕАТР ИМЕНИ М.М.БРОВУНА',
            'domain_name' => 'muzdrama.ru',
        ]);
        Theaters::insert([
            'name' => 'Кинотеатр Фунтура Синема',
            'domain_name' => 'funtura-cinema.ru',
        ]);
    }
}
