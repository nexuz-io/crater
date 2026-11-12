<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(CurrenciesTableSeeder::class);
        $this->call(CountriesTableSeeder::class);
        $this->call(UsersTableSeeder::class);

        // Plan 05 / plan 09 §F11 — entrega las abilities de los
        // albaranes (`view/create/edit/delete/send delivery note` y
        // `convert delivery note to invoice`) a los roles por
        // defecto durante el install. El seeder es idempotente: si
        // las abilities o los enlaces role↔ability ya existen, no
        // duplica filas.
        $this->call(CraterVersion610::class);
    }
}
