<?php

namespace Crater\Providers;

use Crater\Services\Documents\Converters\EstimateToInvoiceConverter;
use Crater\Services\Documents\Converters\RecurringToInvoiceConverter;
use Crater\Services\Documents\DocumentConverterRegistry;
use Crater\Services\Documents\DocumentSnapshotter;
use Crater\Services\Documents\DocumentTotalsCalculator;
use Crater\Services\Documents\DocumentWriter;
use Illuminate\Support\ServiceProvider;

/**
 * Provider for the document contract layer (plan 09, F0-F11).
 *
 * Services registered here are autowired by the container via
 * constructor injection. F2 (PR-03) registers DocumentWriter and
 * DocumentTotalsCalculator. Subsequent phases add DocumentConverter
 * registry, DocumentSnapshotter, etc., without touching the provider
 * unless a binding needs explicit configuration.
 */
class DocumentServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(DocumentTotalsCalculator::class);
        $this->app->singleton(DocumentSnapshotter::class);

        $this->app->singleton(DocumentWriter::class, function ($app) {
            return new DocumentWriter(
                $app['db.connection'],
                $app->make(DocumentTotalsCalculator::class),
            );
        });

        // F3 (PR-04) y F4 (PR-05): registry de converters.
        // EstimateToInvoiceConverter (F3) + RecurringToInvoiceConverter
        // (F4) pre-registrados; F11 añadirá DeliveryNoteToInvoiceConverter.
        $this->app->singleton(DocumentConverterRegistry::class, function () {
            $registry = new DocumentConverterRegistry();
            $registry->register(new EstimateToInvoiceConverter());
            $registry->register(new RecurringToInvoiceConverter());

            return $registry;
        });
    }

    public function boot()
    {
        //
    }
}
