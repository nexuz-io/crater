<?php

namespace Crater\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Placeholder service provider for the document contract layer.
 *
 * This provider exists to register forthcoming bindings for the common
 * document services (DocumentWriter, DocumentConverterRegistry,
 * DocumentSnapshotter, …) introduced by the F1-F11 phases of plan 09.
 *
 * It is intentionally empty for the F0 onboarding PR: registering
 * nothing yet, but giving the rest of the application a stable,
 * dedicated place for the bindings once those phases land.
 */
class DocumentServiceProvider extends ServiceProvider
{
    /**
     * Register document-related services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap document-related services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
