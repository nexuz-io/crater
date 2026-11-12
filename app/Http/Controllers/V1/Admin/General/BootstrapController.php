<?php

namespace Crater\Http\Controllers\V1\Admin\General;

use Crater\Http\Controllers\Controller;
use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\UserResource;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\Module;
use Crater\Models\Setting;
use Crater\Traits\GeneratesMenuTrait;
use Database\Seeders\CraterVersion610;
use Illuminate\Http\Request;
use Silber\Bouncer\BouncerFacade;

class BootstrapController extends Controller
{
    use GeneratesMenuTrait;

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $current_user = $request->user();
        $current_user_settings = $current_user->getAllSettings();

        $companies = $current_user->companies;

        $current_company = Company::find($request->header('company'));

        if ((! $current_company) || ($current_company && ! $current_user->hasCompany($current_company->id))) {
            $current_company = $current_user->companies()->first();
        }

        $disabled_modules = $current_company->disabledModules();

        $main_menu = collect($this->generateMenu('main_menu', $current_user))
            ->filter(function ($item) use ($disabled_modules) {
                return ! $disabled_modules->contains($item['name']);
            })
            ->values()
            ->toArray();

        $setting_menu = $this->generateMenu('setting_menu', $current_user);

        $current_company_settings = CompanySetting::getAllSettings($current_company->id);

        $current_company_currency = $current_company_settings->has('currency')
            ? Currency::find($current_company_settings->get('currency'))
            : Currency::first();

        BouncerFacade::refreshFor($current_user);

        // Plan 05 §7 + plan 09 §F11 — instalaciones existentes
        // (pre-merge) no tienen las abilities de DeliveryNote y el
        // gateway `route.meta.ability` las bloquea redirigiendo a
        // `account.settings`. El seeder CraterVersion610 ya existe y
        // es idempotente. Lo aplicamos una vez por instalación, y
        // marcamos un sentinel en `Setting` para no repetir.
        //
        // - En installs nuevos:       DatabaseSeeder lo invoca al
        //   poblar, sentinel nace `true`, este bloque es un no-op.
        // - En installs pre-existentes: la primera llamada al
        //   bootstrap aplica las abilities faltantes sin tocar UI.
        try {
            if (class_exists(CraterVersion610::class)
                && ! Setting::getSetting('crater_v610_abilities_seeded')) {
                $seeder = new CraterVersion610();
                $seeder->setContainer(app());
                $seeder->run();
                Setting::setSetting(
                    'crater_v610_abilities_seeded',
                    (string) now()->timestamp
                );
            }
        } catch (\Throwable $e) {
            // No abortamos el bootstrap si el seed falla; la
            // ausencia de abilities sólo bloquea rutas protegidas,
            // no la carga básica de la app.
            report($e);
        }

        $global_settings = Setting::getSettings([
            'api_token',
            'admin_portal_theme',
            'admin_portal_logo',
            'login_page_logo',
            'login_page_heading',
            'login_page_description',
            'admin_page_title',
            'copyright_text'
        ]);

        return response()->json([
            'current_user' => new UserResource($current_user),
            'current_user_settings' => $current_user_settings,
            'current_user_abilities' => $current_user->getAbilities(),
            'companies' => CompanyResource::collection($companies),
            'current_company' => new CompanyResource($current_company),
            'current_company_settings' => $current_company_settings,
            'current_company_currency' => $current_company_currency,
            'config' => config('crater'),
            'global_settings' => $global_settings,
            'main_menu' => $main_menu,
            'setting_menu' => $setting_menu,
            'modules' => Module::where('enabled', true)->pluck('name'),
        ]);
    }
}
