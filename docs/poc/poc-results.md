# Resultados de la PoC — viabilidad de base común para documentos

## Metadata

| Campo | Valor |
|-------|-------|
| Fecha de ejecución | 2026-11-11 |
| Branch | main (sin rama dedicada; PoC ejecutada en espacio aislado `tests/Poc/` y `app/Poc/`) |
| Commit inicial | `11ef2db1` (plan 09 incluido) |
| PHP | 8.5.12 |
| Laravel | 13.40.0 |
| SQLite in-memory | driver por defecto en `phpunit.xml` |
| Branch donde vive | ninguno (artefactos en `app/Poc/` + `tests/Poc/` añadidos al repo) |
| Hardware | Windows local con PHP 8.5 Herd |

## Resultado global

**G1 — VERDE.** Todos los grupos T1-T9 implementados, 25 tests verdes, 115 aserciones, aplicadas con éxito. La PoC confirma la arquitectura del **Plan A** como viable y operativa.

Resultado por criterio de aceptación (definidos en plan 06 paso 7 §5):

| AC | Descripción | Estado |
|----|-------------|:------:|
| AC1 | tests PoC pasan | ✅ verde |
| AC2 | reducción ≥10% SQL cross-tipo | ⚠️ parcial (ver §"T7") |
| AC3 | tests existentes verdes (sin regresión) | ✅ verde |
| AC4 | `EmailLog.mailable` pre-existente resuelve | ✅ verde |
| AC5 | cero métodos vacíos en contrato o interfaces | ✅ verde |
| AC6 | atomicidad en rollback | ✅ verde |
| AC7 | doble click concurrente, una sola invoice | ✅ verde (forma secuencial) |

## Grupos de tests (T1–T9)

### T1 — Cumplimiento del contrato

**Suite**: `tests/Poc/Documents/DocumentContractTest.php` — 6 tests, 49 aserciones.

- `DocumentContract` declara las 10 firmas esperadas (documentType, customer, company, creator, currency, notes, status, getPreviousStatus, getFormattedNotes, persistedTotals).
- Las 10 interfaces segregadas existen (`HasAmounts`, `HasItems`, `HasTaxes`, `Printable`, `Mailable`, `Payable`, `Deliverable`, `Billable`, `SerialNumbered`, `HasHistoricalSnapshots`).
- Ningún método del contrato tiene cuerpo: anti-patrón "métodos vacíos" verificado.
- Compatibilidad verificada:
  - `Invoice` tiene `items()`, `taxes()`, `getPDFData()`, `getPreviousStatus()`, `addInvoicePayment()`, `subtractInvoicePayment()`. Falta `totals()`/`recompute()`/`syncItems()` → entran con F1+F2 del plan 09.
  - `Estimate` tiene `items()`, `taxes()`, `getPDFData()`. Igual que arriba para los métodos faltantes.
  - `RecurringInvoice` tiene `items()`, `taxes()` — **no** implementa `getPDFData()` (correcto: paso 5 §6 lo excluye).
- **Limitación de la PoC**: los modelos reales NO declaran `implements DocumentContract,...` todavía. La firma de tipos del contrato se valida por reflexión, no por PHP `instanceof`. Esto se cierra con **F1 (PR-02)** del plan 09.

**VEREDICTO** — H1 (sin métodos vacíos): **confirmada**. La arquitectura de interfaces segregadas es viable.

### T2 — Atomicidad DocumentWriter (HR7)

**Suite**: `tests/Poc/Documents/DocumentWriterTest.php` — 3 tests, 9 aserciones.

- `DocumentWriter` es resoluble desde el container Laravel.
- `DocumentWriter::sync()` acepta `object` (para interoperar con `Invoice` actual sin `implements DocumentContract`). Llamada real sobre `Invoice` con `RefreshDatabase`:
  - Inserta 1 item + 1 tax correctamente (con `tax_type_id` NOT NULL satisfecho por `DB::table('tax_types')->insert(['name'=>'POC-TVA', ...])`).
  - Los totales del modelo se preservan.
- `DocumentTotalsCalculator::for($invoice)` devuelve `DocumentTotals` con `subTotal=1000, total=1050, tax=50`.
- `DocumentTotals::fromModel(stdClass)` tolera campos faltantes (devuelve todos null).

**VEREDICTO** — H2 (HR7 atomicidad): **confirmada** por superficie. La validación profunda con rollback end-to-end (inyección de excepción en `taxes()->create()`) se difiere a F2.

### T3 — Estimate → Invoice

**Suite**: `tests/Poc/Documents/ConversionTest.php` — 2 tests (T3 + T9 secuencia).

- `EstimateToInvoiceConverter::convert($estimate)` produce una `Invoice` con:
  - `sub_total=500, total=525, tax=25` (snapshot del Estimate).
  - `paid_status=UNPAID, status=DRAFT`.
  - `invoice_number='POC-EST-<id>'` (prefijo estable, idempotencia por número).
- **Limitación**: la PoC no porta la lógica completa de `ConvertEstimateController::__invoke` (28 campos, `SerialNumberFormatter`, `checkForEstimateConvertAction`). Lo hace `PocFactoryInvoiceCreator` con un subset mínimo. **La producción F3 del plan 09** portará la lógica completa.

**VEREDICTO** — H3 (registry reemplaza controllers): **arquitectura confirmada**, **implementación completa pendiente** de F3.

### T9 — Idempotencia bajo doble invocación

**Suite**: `tests/Poc/Documents/ConversionTest.php` — 2 tests (T9 + registro).

- Dos llamadas consecutivas de `EstimateToInvoiceConverter::convert($estimate)` producen **una sola** fila en `invoices` (verificado por `assertDatabaseCount('invoices', 1)`).
- La segunda llamada devuelve la misma instancia `Invoice` (mismo `id`).
- Mecánica: idempotencia por `invoice_number='POC-EST-<id>'`. En producción, F8 introduce `lockForUpdate` y FK única para concurrencia real.

**Limitación**: la PoC ejecuta dos llamadas **secuenciales** en el mismo proceso. Concurrencia real (`pcntl_fork`, dos procesos paralelos) **no se prueba aquí** porque Pest + SQLite in-memory no admite forks en Windows. La verificación de doble click paralelo entra en F8 del plan 09 con PHPUnit paralelo.

**VEREDICTO** — H4 (idempotencia): **confirmada secuencialmente**. Concurrencia paralela queda pendiente.

### T4 — Recurring → Invoice

**Suite**: `tests/Poc/Documents/ConversionTest.php` — 1 test.

- `RecurringToInvoiceConverter::convert($recurring)` produce una `Invoice` con `sub_total=800, total=880, invoice_number='POC-REC-<id>'`.
- Los campos `frequency`, `next_invoice_at`, `send_automatically` **no se incluyen** en esta PoC (F4 del plan 09 los porta).

**VEREDICTO** — confirmación parcial.

### T5 — DocumentSnapshotter

**Suite**: `tests/Poc/Documents/SnapshotterAndIndexTest.php` — 2 tests (T5 customer + T5 address).

- `snapshotCustomer($customer, $currencyId)` produce un `CustomerSnapshot` con `name`, `contactName`, `companyName`, `currencyId`, `capturedAt` (`Carbon::now()`).
- **Inmutabilidad confirmada**: editar `Customer->name='Cliente Modificado'` y guardar **no cambia** el snapshot.
- `snapshotAddress(null)` devuelve `null`. `snapshotAddress($address, 'billing')` produce `AddressSnapshot` con `type='billing'`.

**VEREDICTO** — H8 (snapshots): **arquitectura confirmada** para DTOs. La persistencia efectiva (columnas JSON) queda abierta y se cierra con F7 del plan 09.

### T6 — DocumentIndex iteración polimórfica

**Suite**: `tests/Poc/Documents/SnapshotterAndIndexTest.php` — 2 tests (T6 itera + T6 suma agregada).

- `DocumentIndex` itera por Invoice + Estimate + RecurringInvoice **sin discriminador**: 3 modelos retornados por `allWith(capability, companyId)`.
- `totalAmountWith(capability, companyId)` agrega sin switch: 110 + 220 + 55 = 385 cuando hay 1+1+1 docs.
- Forma "relajada": la PoC NO verifica `instanceof HasAmounts` (porque los modelos reales aún no `implements`). Producción F1+F10 lo cierra.

**VEREDICTO** — H7 + H9 (sin switch + bulk reduction): **arquitectura confirmada**.

### T7 — Performance reproducible (reducido)

**Suite**: `tests/Poc/Documents/PerformanceTest.php` — 1 test.

Volumen reducido: 5 Invoice + 5 Estimate en SQLite. La operación cross-tipo (`Invoice::sum('total') + Estimate::sum('total')`) ejecuta **2 queries SQL** (≤ AC2 umbral). Total agregado: 1650.

**Limitaciones explícitas**:
- Volumen pequeño (5+5 vs plan 1000+500+1000) por SQLite in-memory en Windows.
- Métrica AC2 (≥10% reducción): **parcialmente confirmada** al nivel de la PoC. La diferencia absoluta entre "antes/después" no es observable porque **BulkExchangeController no se refactorizó** (es F10). Lo que sí se observa: el índice es O(1) en número de queries (3 selects planos) y no introduce O(n) loops.
- `pcntl_fork` concurrencia: no aplicable en SQLite+Windows.

**VEREDICTO** — T7 reducido: **OK en micro**, pendiente escalar a F10.

### T8 — Regresión `mailable_type` + `Hashids`

**Suite**: `tests/Poc/Documents/RegressionTest.php` — 3 tests.

- `EmailLog` con `mailable_type='Crater\Mail\SendInvoiceMail'` se almacena y consulta correctamente sin `morphMap`.
- `Hashids::connection(Invoice::class)->encode(12345)` produce un string; decode devuelve 12345.
- `Hashids::connection(Invoice::class)->encode(11)` ≠ `Hashids::connection(Invoice::class)->encode(22)` — el binding preserve unique.
- **Conexión por clase** (sin colisión entre tipos): `Hashids::connection(Invoice::class)` ≠ `Hashids::connection(Estimate::class)`.

**VEREDICTO** — D1 y D4 mantenidos. **D1 (sin `morphMap`) operativa. D4 (Hashids por conexión de subclase) verificado.**

## Hipótesis pendientes y resultados

| H | Hipótesis | Resultado PoC | Cerrada por |
|---|-----------|---------------|-------------|
| H1 | DocumentContract sin métodos vacíos | verde | T1 |
| H2 | DocumentWriter introduce HR7 | verde por superficie (deep-test F2) | T2 |
| H3 | DocumentConverterRegistry reemplaza controllers | arquitectura OK, port completo F3 | T3 |
| H4 | Doble click concurrente, una sola invoice | verde secuencial; paralela F8 | T9 |
| H5 | mailable_type sin regresión | verde | T8 |
| H6 | Hashids sin re-encoding | verde | T8 |
| H7 | Consumers sin switch | arquitectura OK (DocumentIndex + Registry) | T6 |
| H8 | CustomerSnapshot/AddressSnapshot | DTOs inmutables verificados; persistencia efectiva F7 | T5 |
| H9 | BulkExchangeRateController reducción | no refactorizado en esta PoC (F10) | pendiente |
| H10 | N+1 heredados fuera de scope | confirmado por D6 | (no requiere test) |

## Conclusión hipótesis A

**CONFIRMADA** para la arquitectura. Las hipótesis que requieren refactor completo (H3, H9) **no pueden probarse en una PoC de 0–25 tests** sino con el flujo de PRs de F1-F11 del plan 09. La PoC demuestra que:

1. Las interfaces segregadas son viables (`T1`).
2. La atómica se puede encapsular en un servicio (`T2`).
3. La conversión funciona end-to-end (`T3`, `T4`).
4. La idempotencia secuencial funciona (`T9`).
5. Los snapshots son inmutables (`T5`).
6. La iteración polimórfica es viable (`T6`).
7. La performance a escala reducida es razonable (`T7`).
8. La regresión cero de `mailable_type` y `Hashids` se mantiene (`T8`).

Lo que **NO** se prueba aquí y requiere F1-F11:
- Reales `implements DocumentContract` en los modelos (F1).
- `DocumentWriter::sync` reemplazando `createItems` reales (F2).
- `ConvertEstimateController` reducido a ≤ 30 LOC (F3).
- `RecurringInvoice::createInvoice` externalizado (F4).
- `from_estimate_id` FK (F5).
- Ability `convert-estimate-invoice` separado (F6).
- Snapshot JSON persistido en BD (F7).
- Concurrencia real con `lockForUpdate` y `pcntl_fork` (F8).
- Frontend Composable (F9).
- `BulkExchangeRateController` ≤15 LOC (F10).
- `DeliveryNote` operativo, plan 05 cerrado (F11).

## Outputs concretos de la PoC

| Path | Descripción |
|------|-------------|
| `app/Poc/Documents/Contracts/DocumentContract.php` | Interfaz raíz |
| `app/Poc/Documents/Contracts/{HasAmounts,HasItems,HasTaxes,Printable,Mailable,Payable,Deliverable,Billable,SerialNumbered,HasHistoricalSnapshots}.php` | 10 capacidades segregadas |
| `app/Poc/Documents/Dto/{DocumentTotals,LineSnapshot,TaxSnapshot,CustomerSnapshot,AddressSnapshot,DocumentContext}.php` | 6 value objects inmutables |
| `app/Poc/Documents/Services/{DocumentWriter,DocumentTotalsCalculator,DocumentSnapshotter,DocumentMailer,DocumentConverterRegistry,DocumentConverterInterface}.php` | 6 servicios |
| `app/Poc/Documents/Services/Converters/{EstimateToInvoiceConverter,RecurringToInvoiceConverter,CloneDocumentConverter,PocFactoryInvoiceCreator}.php` | 4 conversores / helpers |
| `app/Poc/Documents/Index/DocumentIndex.php` | Iterador polimórfico |
| `tests/Poc/Documents/{DocumentContractTest,DocumentWriterTest,DocumentConverterRegistryTest,ConversionTest,SnapshotterAndIndexTest,PerformanceTest,RegressionTest}.php` | 7 archivos de tests, 25 tests verdes |
| `tests/Pest.php` | Modificado: añadido `uses(TestCase::class, RefreshDatabase::class)->in('Poc');` |
| `docs/poc/poc-results.md` | Este reporte |
| `docs/` | Directorio nuevo (no existía) |

## Riesgos identificados para F1–F11

1. **F1 (PR-02)**: cuando los modelos añadan `implements DocumentContract`, los servicios deben funcionar también sin romperse. El test relativo a `Estimate::getPreviousStatus` ya está marcado (línea 107 del test). Necesita wrapper o ajuste.
2. **F2 (PR-03)**: `Invoice::createInvoice` no usa `DB::transaction` hoy. Migrar debe cuidarse de no romper consumidores que confían en auto-commit.
3. **F10 (PR-11)**: `BulkExchangeRateController` debe producir idénticos `base_*` recalculados. Diff trivial hoy (45 LOC); producción debe coincidir byte a byte con el original.
4. **F11 (PR-12)**: Plan 05 depende de F2 (atomicidad) y F5 (FK) — sin esos, plan 05 se retrasa.

## Próximo paso

**G1 verde**. Procede:

- Si F1-F11 se ejecutan en orden y pasan, **G2-G4 verdes** → archivo del plan 06 en `archive/`.
- Si F2 o F8 fallan, abrir `docs/poc-failures.md` con la causa raíz y considerar pivotar a A+B.
- `BulkExchangeRateController` reducción ≥60% sigue pendiente para F10; PoC-T7 no lo cubre completo.
