# Implementación del contrato y servicios comunes para documentos

## Objetivo

Ejecutar las fases F0–F12 diseñadas en `.agents/plans/06-document-base-feasibility.md` (paso 8), operativizándolas en PRs verificables con criterios de aceptación tomados de la PoC del paso 7, hasta que las 4 gates G1–G4 estén verdes y se pueda archivar el plan 06.

## Contexto

- **Plan 06** (`.agents/plans/06-document-base-feasibility.md`) emitió la decisión **alternativa A** (composición + contratos) como VIABLE Y RECOMENDABLE con 6 condiciones explícitas (C1-C6) y 7 decisiones (D1-D7). Las fases operativas y los criterios de aceptación viven en ese plan; este plan 09 las **convierte en tareas y PRs concretos**.
- **Plan 05** (`.agents/plans/05-delivery-notes.md`) implementa `DeliveryNote` desde cero. Esa implementación se apoya, en su fase de conversión y FK única, en la salida de las fases F2 (atomicidad) y F11 (registro en su controller).
- **No** se modifica tabla alguna hasta F5/F11. Hasta ese momento, este plan es **aditivo** sobre el código actual.
- **Este plan NO autoriza la refactorización.** La autorización la da la PoC del paso 7 (AC1-AC7 verdes) + el gate G1.

---

## Plan

> Por cada fase F0-F12 de plan 06 §paso-8:
> - **PR-N** propuesto (se numera cronológicamente en `main`).
> - **Archivos a tocar** (referencias a archivos reales hoy y a los que nacerán en el plan).
> - **Tests mínimos a añadir** (del grupo T* en la PoC si aplica).
> - **Salida esperada** (cambio verificable en CI).
> - **Gate al que contribuye** (G1-G4).

> **Convención**: cada fase es **un PR** salvo cuando se indique explícitamente. Las fases F5, F7 y F11 son las únicas que introducen migraciones y, por tanto, requieren atención adicional al orden de despliegue.

### F0 — Namespaces + ServiceProvider (PR-01)

- **Archivos nuevos**:
  - `app/Providers/DocumentServiceProvider.php` (vacío o solo con `register`/`boot` no-op; placeholder para binding futuro de servicios).
  - `app/Contracts/Documents/.gitkeep` (placeholder).
  - `app/Services/Documents/.gitkeep`.
  - `app/DataObjects/Documents/.gitkeep`.
- **Archivos modificados**:
  - `config/app.php` → registrar `DocumentServiceProvider`.
- **Tests**: ninguno requerido.
- **Salida CI**: `php artisan list` no rompe; `composer dump-autoload` produce la misma lista de autoload; suite de tests existente sigue verde.
- **Gate al que contribuye**: preparación; previo a todos los demás PRs.

### F1 — `DocumentContract` + 11 interfaces de capacidad opt-in (PR-02)

- **Archivos nuevos** (en `app/Contracts/Documents/`):
  - `DocumentContract.php`
  - `HasAmounts.php` · `HasItems.php` · `HasTaxes.php` · `Printable.php` · `Mailable.php` · `Payable.php` · `Deliverable.php` · `Billable.php` · `SerialNumbered.php` · `HasHistoricalSnapshots.php` · `HasCustomFields.php` · `HasMedia.php`
- **Archivos modificados**:
  - `app/Models/Invoice.php` → añadir `implements DocumentContract, HasAmounts, …` (sin cambios de método).
  - `app/Models/Estimate.php` → idem.
  - `app/Models/RecurringInvoice.php` → idem (con el alcance acotado de plan 06 §paso-5).
- **Archivos nuevos tests**:
  - `tests/Unit/Documents/ContractComplianceTest.php` (basado en T1 de la PoC).
- **Salida CI**:
  - `(new Invoice) instanceof DocumentContract` retorna `true`.
  - Pintar `class_implements(Invoice::class)` y verificar el set exacto de interfaces esperado (matriz plan 06 §paso-5 §10).
  - Suites `tests/Feature/Admin/InvoiceTest.php`, `tests/Feature/Admin/EstimateTest.php`, `tests/Unit/{Invoice,Estimate,RecurringInvoice}Test.php` verdes **sin tocar**.
- **Gate al que contribuye**: capa de habilitación para F2-F10.

### F2 — `DocumentWriter` introduce atomicidad (HR7) sobre `Invoice` (PR-03)

- **Archivos nuevos**:
  - `app/Services/Documents/DocumentTotalsCalculator.php` (puro).
  - `app/Services/Documents/DocumentWriter.php` (con `DB::transaction`).
- **Archivos modificados**:
  - `app/Models/Invoice.php` → refactor de `createInvoice($request)`: items y taxes sincronizan vía `DocumentWriter::sync($invoice, $items, $taxes)`.
  - `app/Models/Invoice.php` → refactor de `updateInvoice($request)`: misma sustitución.
- **Archivos nuevos tests**:
  - `tests/Unit/Documents/Writer/InvoiceWriterTest.php` (T2 de la PoC). Caso normal + caso de fallo (inyectar excepción al insertar el tercer `tax` → la invoice queda con totales originales; assert `assertDatabaseMissing` y rollback verificado).
- **Salida CI**:
  - `tests/Feature/Admin/InvoiceTest.php` y `tests/Unit/InvoiceTest.php` verdes **sin tocar**.
  - El primer `generateInvoicePdfJob` se ejecuta sin cambios (no toco `getPDFData`).
- **Gate al que contribuye**: **G2**.

### F3 — `DocumentConverterRegistry` + `EstimateToInvoiceConverter` (PR-04)

- **Archivos nuevos**:
  - `app/Services/Documents/Converters/AbstractDocumentConverter.php` (o interface `DocumentConverterInterface`).
  - `app/Services/Documents/Converters/EstimateToInvoiceConverter.php` (porta literal de `ConvertEstimateController::__invoke` líneas 25-133, incluyendo los 28 campos del `Invoice::create()` y el ciclo de items/taxes).
  - `app/Services/Documents/DocumentConverterRegistry.php`.
- **Archivos modificados**:
  - `app/Http/Controllers/V1/Admin/Estimate/ConvertEstimateController.php` → reducido a cargar Estimate, resolver converter en el registry y devolver `InvoiceResource`. Línea objetivo: ≤ 30 LOC.
  - `App\Providers\DocumentServiceProvider.php` (registrar el converter).
- **Archivos nuevos tests**:
  - `tests/Unit/Documents/Converters/EstimateToInvoiceConverterTest.php` (T3 de la PoC). Aserciones: 28 campos correctos, taxes con `estimate_id` desvinculado, `setNextNumbers` aplicado, `company_id` propagado.
  - `tests/Feature/Admin/EstimateTest.php::test('create invoice from estimate')` verde.
- **Salida CI**: ningún cambio observable por API; el endpoint responde igual. Diff en `ConvertEstimateController` reducido en al menos 100 líneas.
- **Gate al que contribuye**: **G3**.

### F4 — Externalización de `RecurringInvoice::createInvoice` (PR-05)

- **Archivos nuevos**:
  - `app/Services/Documents/Converters/RecurringToInvoiceConverter.php` (porta el cuerpo de `RecurringInvoice::createInvoice` líneas 320-381 con sus `base_*` y `send_automatically`).
  - Registro en `DocumentConverterRegistry`.
- **Archivos modificados**:
  - `app/Models/RecurringInvoice.php` → `createInvoice` queda ≤30 LOC; delega al converter. `RecurringInvoice::generateInvoice` invoca `createInvoice` igual que antes.
  - `app/Services/Documents/Converters/RecurringToInvoiceConverter.php` reusa `Invoice::createItems($invoice, $this->items->toArray())` ya estable.
- **Archivos nuevos tests**:
  - `tests/Unit/Documents/Converters/RecurringToInvoiceConverterTest.php` (T4 de la PoC). Casos: `starts_at > now` → no crea; `limit_by = COUNT` agotado → no crea; ciclo normal → crea Invoice + actualiza `next_invoice_at`.
  - `tests/Feature/Admin/RecurringInvoiceTest.php` verde.
- **Salida CI**: cron sigue disparando con el mismo efecto.
- **Gate al que contribuye**: preparación para F5+F8.

### F5 — `invoices.from_estimate_id` (PR-06A o PR-06B; decisión tras PoC)

- **Variante A** (la PoC recomienda FK explícita):
  - **Archivos nuevos migraciones**:
    - `database/migrations/YYYY_MM_DD_HHMMSS_add_from_estimate_id_to_invoices_table.php`:
      - `$table->unsignedBigInteger('from_estimate_id')->nullable();`
      - `$table->foreign('from_estimate_id')->references('id')->on('estimates')->onDelete('set null');`
      - `$table->index(['company_id', 'from_estimate_id']);`
    - **Sin UNIQUE** (una Estimate puede dar lugar a varias Invoices en escenarios override).
  - **Modificaciones**:
    - `app/Models/Invoice.php` → atributo `from_estimate_id`, accessor `fromEstimateId` opcional.
    - `app/Models/Estimate.php` → relación `invoicesFromEstimate(): HasMany`.
    - `app/Services/Documents/Converters/EstimateToInvoiceConverter.php` → escribe `from_estimate_id`.
    - `app/Http/Resources/InvoiceResource.php` → expone `from_estimate_id`.
- **Variante B** (la PoC no recomienda FK):
  - Solo `$appends['fromEstimateId']` con `null` por defecto. Sin migración.
  - Log explícito en `EstimateToInvoiceConverter` para trazabilidad por log/telemetría.
- **Salida CI**: `tests/Feature/Admin/EstimateTest.php::test('create invoice from estimate')` verde. Reading snapshots compatibles con valores NULL en filas históricas.
- **Gate al que contribuye**: **G3** (cierra con F3).

### F6 — Ability `convert-estimate-invoice` (D7) (PR-07)

- **Archivos modificados**:
  - `app/Policies/EstimatePolicy.php` → método `convertToInvoice(User $user, Estimate $estimate): bool`.
  - `app/Providers/AuthServiceProvider.php` → `Gate::define('convert-estimate-invoice', [EstimatePolicy::class, 'convertToInvoice']);`
  - `app/Http/Controllers/V1/Admin/Estimate/ConvertEstimateController.php` → `$this->authorize('convert-estimate-invoice', $estimate)`取代 `$this->authorize('create', Invoice::class)`.
  - `database/seeders/Crater/...` (o equivalente) → sembrar el ability `convert-estimate-invoice` en roles existentes que tenían `create-invoice`. Verificar equivalencia exacta de `view-invoice`+`create-invoice` no es requerida: `convert-estimate-invoice` se otorga a `owner` y a roles con `create-invoice` Y al menos... [decisión del seed].
- **Archivos nuevos tests**:
  - `tests/Feature/Admin/EstimateTest.php::test('unauthorized user cannot convert estimate')` (esperado 403).
- **Salida CI**: tests `tests/Feature/Admin/EstimateTest.php` (24 tests listados en plan 06 §G) verdes + nuevos casos negativos verdes.
- **Gate al que contribuye**: **G3**.

### F7 — `DocumentSnapshotter` y DTOs (`CustomerSnapshot`, `AddressSnapshot`) (PR-08; opcional sobre PoC)

- **Archivos nuevos**:
  - `app/DataObjects/Documents/CustomerSnapshot.php` (inmutable, `readonly class`).
  - `app/DataObjects/Documents/AddressSnapshot.php`.
  - `app/DataObjects/Documents/LineSnapshot.php`.
  - `app/DataObjects/Documents/TaxSnapshot.php`.
  - `app/DataObjects/Documents/DocumentTotals.php`.
  - `app/DataObjects/Documents/DocumentContext.php`.
  - `app/Services/Documents/DocumentSnapshotter.php` con `snapshotCustomer(Customer $c)`, `snapshotAddress(Address $a)`.
  - `$appends['customerSnapshot']` + `$appends['billingAddress']` + `$appends['shippingAddress']` en `Invoice`, `Estimate`, `RecurringInvoice`.
- **Persistencia**: decisión del PR (JSON en `notes` o columna nueva). Si columna nueva, migración **aditiva reversible** con `down()` que borra la columna.
- **Archivos nuevos tests**:
  - `tests/Unit/Documents/SnapshotterTest.php` (T5 de la PoC). Aserciones: snapshot inmutable; modificar `Customer` después no afecta el snapshot.
- **Salida CI**: tests existentes verdes. La `customer_snapshot` queda en `null` para filas históricas; el código lee con fall-back al `customer` vivo.
- **Gate al que contribuye**: habilita F11 en igualdad de condiciones.

### F8 — Idempotencia bajo doble click (D5) (PR-09)

- **Archivos modificados**:
  - `app/Services/Documents/Converters/EstimateToInvoiceConverter.php` → en `convert(...)` envolver en `DB::transaction`, `lockForUpdate()` sobre el Estimate y chequeo: ¿ya existe `Invoice` con `from_estimate_id = $estimate->id`? Si sí, devolver la existente; si no, crear y devolver.
  - `app/Services/Documents/Converters/RecurringToInvoiceConverter.php` → chequeo equivalente: ¿se creó Invoice con `recurring_invoice_id = $id` en este ciclo? Si `limit_by = COUNT` ya usado, no crear.
- **Archivos nuevos tests**:
  - `tests/Feature/Documents/EstimateConversionConcurrencyTest.php` (T9 de la PoC). Usar `pcntl_fork` o dos `php artisan tinker --execute` paralelos.
- **Salida CI**: dos ejecuciones en paralelo de `convert` generan exactamente **una** Invoice.
- **Gate al que contribuye**: **G3** (refuerza idempotencia).

### F9 — Frontend Composable `useDocumentForm` + folder `document-common/` (PR-10)

- **Archivos nuevos**:
  - `resources/scripts/admin/components/document-common/DocumentTotalsPanel.vue` (re-exporta `CreateTotal.vue` + `CreateTotalTaxes.vue`).
  - `resources/scripts/admin/components/document-common/DocumentItemsTable.vue` (re-exporta `CreateItems.vue` + `CreateItemRow.vue` + `CreateItemRowTax.vue`).
  - `resources/scripts/admin/components/document-common/NotesField.vue` (re-exporta `CreateNotesField.vue` + `CreatePaymentInformationField.vue`).
  - `resources/scripts/admin/components/document-common/TemplatePicker.vue` (re-exporta `SelectTemplateButton.vue`).
  - `resources/scripts/admin/components/document-common/TaxPicker.vue` (re-exporta `SelectTaxPopup.vue` + `SalesTax.vue`).
  - `resources/scripts/admin/components/document-common/ExchangeRateConverter.vue` (re-exporta `ExchangeRateConverter.vue`).
  - `resources/scripts/admin/composables/useDocumentForm.ts` (API definida en plan 06 §paso-5 §7.2).
- **Archivos modificados**:
  - `resources/scripts/admin/views/invoices/create/InvoiceCreate.vue` → consumir `<DocumentTotalsPanel />` en lugar del bloque inline.
  - `resources/scripts/admin/views/estimates/create/EstimateCreate.vue` → idem.
- **Salida**: build de frontend OK; los formularios siguen produciendo los mismos payloads.
- **Gate al que contribuye**: opcional; sin gate estricto, pero acelera F11 con UI lista.

### F10 — Refactor de consumers (PR-11)

- **Archivos modificados**:
  - `app/Http/Controllers/V1/Admin/General/BulkExchangeRateController.php` → itera `DocumentIndex::allWith(self::HasAmounts)` y aplica el recálculo de `base_*` por tipo.
  - `app/Services/Documents/Index/DocumentIndex.php` (nuevo) expone `allOf(self::HasAmounts)`.
  - `app/Http/Controllers/V1/Admin/Dashboard/DashboardController.php`: NO se modifica (D6).
  - `app/Http/Controllers/V1/Admin/General/SidebarCountsController.php`: añadir `total_delivery_note_count` cuando plan 05 entre.
- **Archivos nuevos tests**:
  - `tests/Feature/Documents/DocumentIndexIterationTest.php` (T6 de la PoC).
- **Salida CI**:
  - `BulkExchangeRateController.php` ≤ 15 LOC donde tenía 60+ (H9 cumplido).
  - Tests existentes verdes por equivalencia de salida.
- **Gate al que contribuye**: limpia consumidores para F11.

### F11 — Plan 05 `DeliveryNote` (PR-12)

- **Archivos nuevos**:
  - `app/Models/DeliveryNote.php` → `implements DocumentContract, HasAmounts, HasItems, HasTaxes, Printable, Billable, SerialNumbered, HasHistoricalSnapshots`.
  - `app/Models/DeliveryNoteItem.php`.
  - `app/Services/Documents/Converters/DeliveryNoteToInvoiceConverter.php`.
  - Migraciones:
    - `database/migrations/YYYY_MM_DD_HHMMSS_create_delivery_notes_table.php`.
    - `database/migrations/YYYY_MM_DD_HHMMSS_create_delivery_note_items_table.php`.
    - `database/migrations/YYYY_MM_DD_HHMMSS_add_delivery_note_id_to_invoices_table.php` (nullable UNIQUE — restringido por plan 05 §3).
  - Controllers: `app/Http/Controllers/V1/Admin/DeliveryNote/DeliveryNotesController.php` (delController) + `CloneDeliveryNoteController` + `SendDeliveryNoteController` (si Plan 05 los exige).
  - Policies, Requests, Resources, Jobs (`GenerateDeliveryNotePdfJob`), Mail.
  - Registro en `DocumentConverterRegistry`.
  - Routes en `routes/api.php` y `routes/web.php`.
- **Archivos modificados**:
  - `resources/scripts/admin/admin-router.js` → rutas `/admin/delivery-notes/*`.
- **Salida CI**: plan 05 cerrado y archivado. Tests verde.
- **Gate al que contribuye**: **G4**.

### F12 — Documentación y handover (PR-13, fuera del flujo backend)

- **Archivos nuevos**:
  - `docs/architecture/documents.md` (capítulo del nuevo contrato, capacidades, conversores, snapshots).
  - `docs/dev/adding-a-document-type.md` (paso a paso: implementar contrato, registrar converter, registrar menu, actualizar Store Pinia, tests E2E).
  - `CONTRIBUTING.md` actualizado con enlaces.
- **Salida**: PR ejemplo siguiendo el flujo; documentación navegable.
- **Gate al que contribuye**: ninguno técnico, pero habilita trabajo futuro sin perder conocimiento.

---

## Orden recomendado (no estricto)

```
F0 → F1 → F2 → F3 → F4 → F5 → F6 → F7 → F8 → F9 → F10 → F11 → F12
       v
     G1 (PoC del paso 7 del plan 06) ★ gate obligatorio antes de F2
                                          v
                                       G2 = F2 verde
                                          v
                                       G3 = F3 + F5 + F6 + F8 verdes
                                          v
                                       G4 = plan 05 / F11 cerrado
                                          v
                                  plan 06 → archive/
```

> `★` La PoC del paso 7 (plan 06) **debe ejecutarse y estar verde** antes de F2. F0 y F1 pueden ejecutarse sin PoC, pero no se acelera ninguna refactorización significativa hasta que G1 esté verde.

---

## Resultado esperado

- [ ] **G1**. PoC del paso 7 del plan 06 ejecutada con los 7 criterios de aceptación (AC1-AC7) verdes. Reporte `docs/poc/poc-results.md` rellenado.
- [ ] **G2**. PR-03 (`F2`) mergeado y en producción: atomicidad transaccional operativa en `Invoice::createInvoice` y `Invoice::updateInvoice`. `tests/Unit/Documents/Writer/InvoiceWriterTest.php` verde.
- [ ] **G3**. PR-04 + PR-05 + PR-06 + PR-07 + PR-09 mergeados: registry operativo para Estimate→Invoice y Recurring→Invoice, autorización separada por flujo, idempotencia con doble click validada.
- [ ] **G4**. PR-12 (`F11`) mergeado: `DeliveryNote` operativo en producción. Plan 05 cerrado y archivado.
- [ ] Plan 06 movido a `/.agents/plans/archive/`.

## Fuera de alcance

- Implementar la `BaseDocument` abstracta (A+B). Decidido en plan 06 como evolución posterior a la PoC.
- Migrar a tabla única (alternativa D). Decidido en plan 06 como descartado.
- Refactor de N+1 heredados en `DashboardController` y `CustomerStatsController` (D6 los declara fuera).
- Nuevo tipo más allá de `DeliveryNote` (el siguiente será probablemente nota de crédito, fuera de plan 09).
- Renombrar `Invoice` u otras clases (rompería el polimórfico `mailable_type` pre-existente).

## Notas

Estado: **plan operativo, listo para ejecutar**. Pendiente de trigger: PoC del paso 7 con AC1-AC7 verdes (G1). Una vez verificado, ejecutar PR-01 a PR-13 en el orden recomendado.

Riesgos abiertos a vigilar:
- `invoices.from_estimate_id` introduce dudas sobre FK; F5 elige variante A o B según resultado de PoC.
- F7 introduce persistencia (texto JSON o columna nueva) que cualquier `Backup`/`Reportes` debe respetar.
- F11 cierra plan 05: la carga de trabajo final es alta, pero las 10 fases previas la hacen viable.

No se autoriza la ejecución hasta G1 (PoC verde). Los PRs F0-F1 pueden ejecutarse sin PoC para preparar el terreno. Los PRs F2+ requieren evidencia de la PoC.
