<?php

namespace Database\Seeders;

use Crater\Models\Company;
use Crater\Models\Setting;
use Crater\Models\User;
use Illuminate\Database\Seeder;
use Silber\Bouncer\BouncerFacade;
use Vinkla\Hashids\Facades\Hashids;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Habilidades (super admin / admin / owner):
     *   Se aplican vía la cadena:
     *     $company->setupDefaultData()
     *       -> $company->setupRoles()
     *         -> BouncerFacade::scope()->to($company->id)
     *         -> foreach (config('abilities.abilities')) { ... }
     *   Cubierto en steps 3..7 del grupo. Plan 05 / plan 09 §F11
     *   introduce `view-delivery-note` y compañía; están en
     *   config/abilities.php y se aplican automáticamente sin
     *   tocar este seeder. Si añades nuevas abilities, basta con
     *   sumarlas a ese config y re-correr este seeder / la
     *   migration `Grant{Doc}AbilitiesToExistingCompanies` para
     *   installations existentes.
     *
     * @return void
     */
    public function run()
    {
        $user = User::create([
            'email' => 'admin@craterapp.com',
            'name' => 'Jane Doe',
            'role' => 'super admin',
            'password' => 'crater@123',
        ]);

        $company = Company::create([
            'name' => 'xyz',
            'owner_id' => $user->id,
            'slug' => 'xyz'
        ]);

        $company->unique_hash = Hashids::connection(Company::class)->encode($company->id);
        $company->save();
        $company->setupDefaultData();
        $user->companies()->attach($company->id);
        BouncerFacade::scope()->to($company->id);

        $user->assign('super admin');

        Setting::setSetting('profile_complete', 0);
    }
}

