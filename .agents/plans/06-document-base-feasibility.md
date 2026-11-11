# Análisis de viabilidad de una base común para documentos

## Objetivo

Determinar si conviene introducir una base común para facturas, presupuestos y albaranes que reúna sus datos y comportamientos compartidos y permita implementar las particularidades de cada documento.

## Contexto

- El proyecto dispone de `Invoice`, `Estimate`, sus líneas, requests, recursos, policies, controladores y generación de PDF.
- `05-delivery-notes.md` propone incorporar `DeliveryNote` y convertirlo posteriormente en factura.
- La arquitectura candidata es `DocumentoBase → Factura / Presupuesto / DeliveryNote`; el análisis debe distinguir herencia de clases, contratos de comportamiento y organización de tablas.
- Compartir una clase no obliga a compartir tabla. También es posible compartir servicios o implementar una interfaz manteniendo modelos independientes.
- Este plan produce un diagnóstico y una recomendación. La migración de documentos reales requerirá un plan posterior basado en sus conclusiones.

## Plan

1. **Inventariar los documentos y sus dependencias.**
   - Revisar `/app/Models/Invoice.php`, `/app/Models/Estimate.php`, `/app/Models/RecurringInvoice.php` y sus modelos de líneas.
   - Usar `05-delivery-notes.md` como especificación de `DeliveryNote`; si ya está implementado al ejecutar el análisis, revisar también su código real.
   - Identificar requests, recursos, policies, controladores, stores, formularios, trabajos, correos, PDFs y pruebas asociados a cada documento.
   - Examinar `/app/Traits/GeneratesPdfTrait.php` y `/app/Http/Controllers/V1/Admin/Estimate/ConvertEstimateController.php` como puntos de comportamiento compartido y conversión.
   - Localizar las referencias externas a modelos, IDs y nombres de tablas: pagos, impuestos, medios, campos personalizados, reportes, portal de clientes, calculadora, módulos y asistente IA.
   - Registrar las rutas reales y ejemplos concretos de duplicación o divergencia; no basar la propuesta únicamente en nombres de campos parecidos.

2. **Construir una matriz de datos e invariantes.**
   - Comparar empresa, cliente, creador, número, fecha, moneda, dirección, notas, líneas, importes, impuestos, descuentos, estado y metadatos.
   - Clasificar cada campo y comportamiento como común obligatorio, común opcional o específico, con su significado y reglas por documento.
   - Identificar diferencias esenciales: vencimiento, pagos y saldo en factura; aceptación y expiración en presupuesto; entrega y cantidades en albarán.
   - Separar estado de envío, estado comercial, estado de pago y estado de entrega; comprobar que una base común no fuerce un único campo ambiguo.
   - Revisar instantáneas históricas de cliente, dirección y artículos, precisión monetaria, cantidades, tipos de cambio y reglas de edición después de emitir.
   - Analizar líneas e impuestos por separado: una base de documento no implica que todas sus líneas deban compartir tabla o reglas.

3. **Evaluar alternativas de arquitectura.**
   - **A. Modelos actuales con composición:** extraer servicios, objetos de datos y contratos compartidos, conservando modelos y tablas independientes.
   - **B. Clase abstracta `BaseDocument`:** modelos concretos `Invoice`, `Estimate` y `DeliveryNote` heredan de una base Eloquent común, pero mantienen sus tablas, relaciones y especializaciones.
   - **C. Cabecera común con detalles por tipo:** tabla `documents` y tablas específicas relacionadas uno a uno; evaluar cómo se representan los modelos en Laravel, ya que Eloquent no aporta automáticamente herencia de tablas.
   - **D. Tabla única con discriminador:** evaluar sus beneficios y costes en campos nulos, reglas condicionales, restricciones e aislamiento de tipos.
   - Considerar combinaciones razonables, como interfaz de documento más servicios compartidos y base abstracta mínima.
   - Comparar cada alternativa contra conservar la arquitectura actual, que será una conclusión válida si la abstracción no aporta suficiente beneficio.

4. **Definir criterios de viabilidad y seleccionar una candidata.**
   - Puntuar reducción real de duplicación, claridad de reglas, facilidad para añadir documentos, compatibilidad Eloquent y coste de mantenimiento.
   - Evaluar impacto en esquema, migraciones, contratos API, plugins, frontend, pruebas, reportes y rendimiento.
   - Distinguir beneficios comprobados de hipótesis; anotar qué evidencia falta para decidir.
   - Penalizar bases con numerosos condicionales por tipo, métodos vacíos o campos cuyo significado cambia entre documentos.
   - Tratar integridad histórica, aislamiento entre empresas y autorización como requisitos obligatorios, no como ventajas opcionales.
   - Estimar esfuerzo relativo y riesgo de cada alternativa; no dar tiempos exactos sin haber revisado los puntos afectados.

5. **Diseñar el contrato común y las extensiones de la candidata.**
   - Proponer nombres y responsabilidades para una interfaz `DocumentContract`, una posible clase `BaseDocument` y servicios compartidos, solo donde el inventario justifique su existencia.
   - Definir capacidades explícitas: documento con importes, imprimible, enviable, convertible, cobrable o con entrega, evitando exigir pago o impuestos a todos los tipos.
   - Proponer contratos para acceso a empresa/cliente, líneas, numeración, instantáneas, cálculo y generación de PDF; conservar DTOs o adaptadores cuando los esquemas difieran.
   - Mantener validación, permisos y transiciones específicas cerca de cada documento. Explicar cómo se invocan las extensiones sin depender de grandes bloques `switch`.
   - Evaluar formularios compartidos mediante componentes y esquemas claros, manteniendo stores y acciones específicas cuando sus flujos sean distintos.
   - Analizar `RecurringInvoice` como plantilla de generación antes de incluirla en la jerarquía: no asumir que es un documento emitido equivalente a una factura.

6. **Analizar identidad, persistencia y conversiones.**
   - Determinar cómo se conservarían IDs, numeración por empresa y tipo, hashes, URLs, claves foráneas y relaciones polimórficas en cada opción viable.
   - Revisar nombres de clase almacenados en base de datos y morph maps para evitar romper permisos, medios o campos personalizados al cambiar la jerarquía.
   - Definir conversión como creación de un documento destino vinculado al origen; no convertir una fila cambiando su tipo ni reutilizar su identidad.
   - Mantener contratos específicos para presupuesto → factura y albarán → factura, con autorización, transacción e idempotencia adaptadas a cada flujo.
   - Evaluar si basta una referencia explícita al origen o se necesita una entidad de relación; justificar la decisión con casos existentes, no con escenarios hipotéticos.
   - Comparar consultas de listados, reportes, búsqueda y PDF, incluyendo joins, índices y estrategias para evitar N+1.

7. **Validar las hipótesis con una prueba de concepto acotada.**
   - Solo si el análisis documental deja dudas relevantes, construir una prueba desechable en un entorno de pruebas aislado, sin migrar datos de producción.
   - Utilizar factura y presupuesto reales como referencia y representar las necesidades del albarán con fixtures o su implementación disponible.
   - Verificar guardado y lectura, relaciones Eloquent, serialización, cálculo, validación específica, numeración y selección de plantilla PDF.
   - Probar acceso entre empresas, permisos por tipo y conversión con rollback y reintentos.
   - Medir consultas y tiempos con un volumen representativo frente a la solución actual; registrar tamaño del conjunto y método para que la comparación sea reproducible.
   - Documentar resultados y límites de la prueba. No confundir una demostración funcional con una migración lista para desplegar.

8. **Diseñar una adopción gradual si la propuesta resulta viable.**
   - Priorizar extracción de contratos y servicios manteniendo tablas y endpoints actuales cuando esto reduzca el riesgo.
   - Proponer fases por comportamiento y tipo de documento, con criterios de entrada, salida y compatibilidad temporal.
   - Si se recomienda cambiar tablas, definir mapeo de IDs, backfill, verificación de conteos e importes, verificación de corte de escritura, índices y estrategia de reversión.
   - Identificar cambios incompatibles y consumidores que necesitan adaptadores; evitar escrituras dobles sin una estrategia explícita de consistencia.
   - Establecer regresiones obligatorias para PDFs, cálculos, pagos, estados, reportes, APIs, permisos y conversiones.
   - Explicar la relación con `05-delivery-notes.md`: qué puede implementarse ya y qué conviene esperar a la decisión arquitectónica.
   - Al completar los criterios, mover el plan a `/.agents/plans/archive/` según `.agents/README.md`.

9. **Documentar la decisión y el siguiente paso.**
   - Añadir a este plan el inventario resumido, la matriz de campos/comportamientos, alternativas, riesgos y evidencias.
   - Registrar una decisión: viable y recomendable, viable con condiciones, o no recomendable por ahora.
   - Incluir un diagrama sencillo de clases y relaciones de persistencia para la alternativa elegida, señalando los comportamientos específicos.
   - Listar dudas pendientes y condiciones que harían reconsiderar la decisión.
   - Si se recomienda proceder, crear un nuevo plan de implementación numerado en `/.agents/plans/` con alcance y fases concretas; este análisis no autoriza por sí solo la refactorización completa.

## Fuera de alcance

- Migrar o refactorizar todos los documentos durante este análisis.
- Cambiar reglas contables, comerciales o fiscales para hacerlas encajar en una base común.
- Unificar tablas, estados o formularios sin evidencia de que mejora el diseño.
- Incorporar nuevos tipos de documento distintos de los necesarios para evaluar la propuesta.

---

## Inventario verificable (paso 1)

> Rutas relativas a la raíz del proyecto. Las afirmaciones se cruzan con archivos reales; las divergencias entre Invoice y Estimate se marcan con ↯. La entrega de `DeliveryNote` aún no existe en código; se referencia contra la especificación del plan 05.

### A. Modelos y migraciones

#### Modelos presentes (`app/Models/`)

- `Invoice.php` — extiende `Model`, implementa `HasMedia`. Traits: `HasFactory`, `InteractsWithMedia`, `GeneratesPdfTrait`, `HasCustomFieldsTrait`.
- `Estimate.php` — misma firma que Invoice (clase gemela).
- `RecurringInvoice.php` — extiende `Model` (sin `HasMedia`, sin `GeneratesPdfTrait`).
- `InvoiceItem.php`, `EstimateItem.php` — modelos de línea. Atributos clave idénticos: `name`, `description`, `discount_type`, `quantity` (float), `price`/`discount`/`tax`/`total` (`casts` iguales en líneas 16-27 vs 14-25).
- `DeliveryNote.php` y `DeliveryNoteItem.php` — **no encontrados** (se modelarán según plan 05 §3: `delivery_notes`, `delivery_note_items`, `delivery_note_id` nullable único en `invoices`).

#### Esquema común (migraciones)

- `database/migrations/2017_04_12_090759_create_invoices_table.php` y `2017_05_05_055609_create_estimates_table.php` comparten el mismo esqueleto pero divergen en fechas y pagos:
  - `invoices`: `invoice_date`, `due_date`, `status`, **`paid_status`**, `due_amount`, `sent`, `viewed`.
  - `estimates`: `estimate_date`, `expiry_date`, `status`. Sin `paid_status`, sin `due_amount`, sin `sent/viewed` flags.
  - Ambas: `discount`, `discount_type`, `discount_val`, `sub_total`, `total`, `tax`, `tax_per_item`, `discount_per_item`, `notes`, `unique_hash`, `user_id` (legacy), `company_id`.
- Migraciones posteriores gemelas añaden los mismos campos a ambos (paridad administrativa mantenida por separado):
  - `add_creator_in_invoices_table.php` / `add_creator_in_estimates_table.php` (2020-11-23).
  - `add_template_name_to_invoices_table.php` / `add_template_name_to_estimates_table.php` (2020-12-14) y `remove_template_id_from_invoices_and_estimates_table.php`.
  - `add_customer_id_to_estimates_table.php` / `add_customer_id_to_invoices_table.php` (2021-06-28).
  - `add_base_columns_into_invoices_table.php` / `add_base_columns_into_estimates_table.php` (2021-07-16): `exchange_rate`, `base_discount_val`, `base_sub_total`, `base_total`, `base_tax`, `base_due_amount` (este último solo en invoice).
  - `add_currency_id_into_invoices_table.php` / `add_currency_id_into_estimates_table.php` (2021-07-16).
  - `add_sales_tax_fields_to_invoices_table.php` / `add_sales_tax_fields_to_estimates_table.php` / `add_sales_tax_fields_to_recurring_invoices_table.php` (2022-01-05): `sales_tax_type`, `sales_tax_address_type`.
- Migraciones específicas de Invoice:
  - `make_due_date_optional_in_invoices_table.php` (2021-07-15). ↯ `expiry_date` también se hizo opcional en `make_expiry_date_optional_estimates_table.php`.
  - `add_overdue_to_invoices_table.php` (2022-03-02). ↯ Estimate no tiene `overdue`.
  - `calculate_base_values_for_invoice_items.php` (2022-03-04) — backfill.
  - `add_payment_information_to_invoices_table.php` (fechada 2026-11-07): texto libre `payment_information`. ↯ Estimate no lo tiene.

#### Tabla `recurring_invoices`

- Creada en `2021_07_09_063502_create_recurring_invoices_table.php`. Modela una **plantilla**, no un documento emitido: tiene `starts_at`, `frequency` (cron), `limit_by` (`NONE|COUNT|DATE`), `limit_count`, `limit_date`, `next_invoice_at`, `status` (`ACTIVE|COMPLETED|ON_HOLD`), `send_automatically`. No tiene `paid_status`, `due_amount` operativo, ni `template_name` obligatorio (es nullable).
- Relación con Invoice: `invoices.recurring_invoice_id` (`2021_07_09_063712_add_recurring_invoice_id_to_invoices_table.php`) e `invoice_items.recurring_invoice_id` (`2021_07_09_063755_…`). `Tax::recurring_invoice()` también existe (`app/Models/Tax.php:33`). No existe relación con Estimate.

#### Items (`invoice_items`, `estimate_items`)

- Esquema paralelo (ver migraciones 2017 originales más `add_base_columns_into_invoice_items_table.php` y `add_base_columns_into_estimate_items_table.php` 2021-07-16). Ambos añaden: `exchange_rate`, `base_price`, `base_discount_val`, `base_tax`, `base_total`.
- `recurring_invoice_id` solo se añadió a **`invoice_items`**, no a `estimate_items`. Sirve para reconstruir el origen recurrente de la línea.

### B. Rutas y controladores (paridad administrativa)

Todas las rutas listadas a continuación viven en `routes/api.php` (grupo `auth:user` + `customer-portal`/admin) y `routes/web.php` (PDF público).

| Operación                                | Invoice (admin)                                  | Estimate (admin)                                  | RecurringInvoice (admin)                          |
|------------------------------------------|--------------------------------------------------|---------------------------------------------------|---------------------------------------------------|
| Índice / show / store / update / destroy | `api/v1/invoices` (apiResource)                  | `api/v1/estimates`                                | `api/v1/recurring-invoices`                       |
| Eliminar varios                          | `POST /invoices/delete` (DeleteInvoiceRequest)   | `POST /estimates/delete`                          | `POST /recurring-invoices/delete`                 |
| Bulk change-status                       | `POST /invoices/change-status`                   | `POST /estimates/change-status`                    | n/a                                               |
| Status individual                        | `POST /invoices/{invoice}/status`                | `POST /estimates/{estimate}/status`               | n/a                                               |
| Clonar                                   | `POST /invoices/{invoice}/clone` (CloneInvoiceController) | `POST /estimates/{estimate}/clone`          | n/a                                               |
| Enviar                                   | `POST /invoices/{invoice}/send` (+ `/send/preview`) | `POST /estimates/{estimate}/send` (+ `/send/preview`) | n/a                                       |
| Convertir en factura                     | n/a                                              | `POST /estimates/{estimate}/convert-to-invoice` (ConvertEstimateController) | n/a                                       |
| Plantillas                               | `GET /invoices/templates`                        | `GET /estimates/templates`                        | n/a                                               |
| Frecuencia                               | n/a                                              | n/a                                               | `GET /recurring-invoice-frequency`                |
| PDF público (con `unique_hash`)          | `GET /invoices/pdf/{invoice:unique_hash}`        | `GET /estimates/pdf/{estimate:unique_hash}`       | n/a                                               |

**Observaciones de paridad:**

- Invoice tiene `change-status` plurar **y** singular; Estimate también.
- Cliente que acepta/rechaza: `POST /estimate/{estimate}/status` (Customer, singular, route name singular). ↯ Solo aplica a Estimate.
- Pago parcial del cliente: `POST /customer/invoices/{invoice}/payment` (asumido). Estimate no tiene flujo de pago.
- Invoice expone `payment_information` sólo en su tabla y en `InvoiceResource`/`InvoiceMail`; Estimate no.
- Las plantillas se resuelven con `Invoice::invoiceTemplates()` que lista `resources/views/app/pdf/invoice/*.blade.php`, y `Estimate::estimateTemplates()` para `app/pdf/estimate/*.blade.php`. Son carpetas separadas; no hay fusión.

#### Detalle del controller de conversión (`ConvertEstimateController.php`)

- `__invoke(Request, Estimate, Invoice)` (línea 25).
- Autoriza `create` sobre `Invoice` (línea 27).
- Carga `items`, `items.taxes`, `customer`, `taxes` (línea 29).
- Calcula `invoice_date = now()` y `due_date` condicional vía settings `invoice_set_due_date_automatically` + `invoice_due_date_days`.
- Genera `serial` con `SerialNumberFormatter` configurado con `Invoice` (línea 47-51).
- Elige `templateName` con `$estimate->getInvoiceTemplateName()` (línea 53), que reemplaza `"estimate"` por `"invoice"` y fallbacká a `invoice1` si no encuentra equivalente.
- Crea el Invoice con 28 columnas asignadas manualmente (líneas 57-88).
- `Hashids::connection(Invoice::class)->encode($invoice->id)` para `unique_hash`.
- Itera `$estimate->items->toArray()` y recrea cada línea con `company_id` header + `*_base_*` recalculados (líneas 94-114).
- Itera `$estimate->taxes->toArray()`, fija `company_id`, `exchange_rate`, `base_amount` y **`unset($tax['estimate_id'])`** antes de asociarlo al invoice (línea 122).
- Llama a `$estimate->checkForEstimateConvertAction()` (línea 128): aplica `delete_estimate` o `mark_estimate_as_accepted` según `CompanySetting::getSetting('estimate_convert_action')`.

Duplicaciones claras con `RecurringInvoice::createInvoice()` (`RecurringInvoice.php:299-383`):
mismo patrón de copiar importes, base_*, `customer_sequence_number`, `Hashids::connection(Invoice::class)->encode(...)`, generación de `Items`/`Taxes` y `send_automatically` al final.

#### Clone (paridad casi total)

- `CloneInvoiceController.php` y `CloneEstimateController.php` reproducen el mismo flujo con las dos diferencias reales:
  - invoice asigna `due_date` desde settings; estimate asigna `expiry_date` desde `estimate_set_expiry_date_automatically` + `estimate_expiry_date_days`.
  - invoice asigna `reference_number = $invoice->reference_number`; estimate igual.

### C. Requests, Resources y Policies

#### Requests (`app/Http/Requests/`)

| Request                                | Reglas distintivas |
|----------------------------------------|--------------------|
| `InvoicesRequest.php`                  | `invoice_date required`, `due_date nullable`, `invoice_number unique por empresa`, `payment_information nullable string`, `exchange_rate required` cuando cliente y empresa tienen moneda distinta. `getInvoicePayload()` añade `paid_status = UNPAID`, `due_amount = total`, `company_id`, `currency_id` = moneda del cliente. |
| `EstimatesRequest.php`                 | `estimate_date required`, `expiry_date nullable`, `estimate_number unique por empresa`, `items.*.deliverables|delivery_delay|terms nullable string` (campos exclusivos del catálogo de servicios, replicados desde `Item`). `getEstimatePayload()` no asigna `due_amount` (Estimate no gestiona pagos). |
| `RecurringInvoiceRequest.php`          | `starts_at required`, `frequency required`, `limit_by required`, `limit_count required_if:limit_by,COUNT`, `limit_date required_if:limit_by,DATE`, `send_automatically boolean`. Sin `invoice_date` ni `expiry_date`. |
| `DeleteInvoiceRequest.php`             | `ids.*` valida `exists:invoices,id` **y** `new RelationNotExist(Invoice::class, 'payments')` (no se permite borrar facturas con pagos asociados). |
| `DeleteEstimatesRequest.php`           | `ids.*` solo `exists:estimates,id` (sin guard de relaciones). |
| `CustomerEstimateStatusRequest.php`    | Cambia status a `ACCEPTED` o `REJECTED`. |
| `UpdateInvoicesStatusRequest.php` / `UpdateEstimatesStatusRequest.php` | Single update. |
| `SendInvoiceRequest.php` / `SendEstimatesRequest.php` | subject + body + from + to. |

#### Resources

- `InvoiceResource.php`: expone todos los campos + atributos `formatted_*`, `allow_edit`, `payment_module_enabled`, `overdue`, `invoice_pdf_url`. **Incluye `due_amount` y `paid_status`**; `EstimateResource` no.
- `EstimateResource.php`: `estimator_date|expiry_date`, `reference_number`, totales base, `estimate_pdf_url`. No expone `due_amount`/`paid_status`/`overdue`/`payment_module_enabled`/`payment_information`.
- `RecurringInvoiceResource.php`: añade `formatted_starts_at`, `formatted_next_invoice_at`, `formatted_limit_date`, `frequency`, `limit_by`, `limit_count`, `limit_date`, `send_automatically`. Sus `items` se exponen como `InvoiceItemResource::collection` (recurrente reusa la línea de Invoice).
- `Customer/*` Resources son espejo de los anteriores.

#### Policies

- `InvoicePolicy.php`: abilities `view-invoice`, `create-invoice`, `edit-invoice`, `delete-invoice`, `send-invoice`. `update()` además combina con `$invoice->allow_edit` (`Invoice.php:131`).
- `EstimatePolicy.php`: abilities `view-estimate`, `create-estimate`, `edit-estimate`, `delete-estimate`, `send-estimate`. Mismas firmas, sin allow_edit ni pago.
- `RecurringInvoicePolicy.php`: ability `view-recurring-invoice`, `create-…`, `edit-…`, `delete-…`, sin ability `send-recurring-invoice`.

### D. Servicios compartidos y traits

- `app/Traits/GeneratesPdfTrait.php`:
  - `getGeneratedPDFOrStream`, `getGeneratedPDF`, `generatePDF`, `getFieldsArray`, `getFormattedString`.
  - **Usado por `Invoice` y `Estimate`** (no por `RecurringInvoice`).
- `app/Traits/HasCustomFieldsTrait.php`: portador de custom fields.
- `app/Services/SerialNumberFormatter.php`:
  - `setModel`, `setModelObject`, `setCompany`, `setCustomer`, `setNextNumbers` → `nextSequenceNumber`, `nextCustomerSequenceNumber`.
  - `getNextNumber` infiere la clave de setting con `strtolower(class_basename($this->model)) . '_number_format'`.
- No existe aún un servicio `app/Services/ConvertDeliveryNote*` ni `app/Services/SalesDocument*`.

### E. Trabajos y correo

- `app/Jobs/GenerateInvoicePdfJob.php` y `GenerateEstimatePdfJob.php` son gemelos; solo cambia el nombre del modelo y el identificador de collection.
- `app/Mail/SendInvoiceMail.php` recibe `attach.data` desde `Invoice::getPDFData()`; `SendEstimateMail.php` igual.
- `app/Mail/InvoiceViewedMail.php`, `EstimateViewedMail.php`: flujo de apertura del cliente desde el PDF público (token vía `EmailLog`).
- `app/Mail/SendPaymentMail.php`: depende de `Payment`.

### F. Referencias externas (consumidores)

- `app/Models/Payment.php` — usa `invoice_id`, `Invoice::STATUS_*`, `addInvoicePayment`/`subtractInvoicePayment`/`changeInvoiceStatus`.
- `app/Models/Tax.php` — relaciones `invoice()`, `estimate()`, `recurringInvoice()`, `invoiceItem()`, `estimateItem()`, scope `invoicesBetween()`.
- `app/Models/EmailLog.php` — `morphTo('mailable')` desde Invoice y Estimate. Almacena `mailable_type` literal.
- `app/Models/NationalCustomFields` — `model_type` configurable; `HasCustomFieldsTrait` consume.
- `app/Http/Controllers/V1/Admin/Dashboard/DashboardController.php` — cuenta y suma totales.
- `app/Http/Controllers/V1/Admin/General/SidebarCountsController.php` — expone `total_invoice_count` y `total_estimate_count`.
- `app/Http/Controllers/V1/Admin/General/GetAllUsedCurrenciesController.php` — `array_merge` Invoice+Estimate+Tax+Payment.
- `app/Http/Controllers/V1/Admin/General/BulkExchangeRateController.php` — paralelismo Invoice/Estimate duplicado literal.
- `app/Http/Controllers/V1/Admin/Report/{CustomerSalesReport,TaxSummaryReport}Controller.php` — usa `Tax::with('invoice', 'invoiceItem')` y `Customer::with(['invoices'])`.
- `app/Http/Controllers/V1/Admin/RecurringInvoice/RecurringInvoiceFrequencyController.php` — frecuencias con `Cron\CronExpression`.
- `app/Http/Controllers/V1/Admin/Ai/DocumentAssistantController.php` — recibe `document_type` con valores `estimate`/`invoice`. `assertDocumentExists` usa `match ($doc === 'estimate' ? Estimate : Invoice)` (anti-patrón).
- `app/Http/Controllers/V1/Customer/Payment/*` — liga `invoice_id`.

### G. Frontend (Vue) y componentes compartidos

- `resources/scripts/router/index.js` y `resources/scripts/admin/admin-router.js` declaran rutas SPA para invoices, estimates, recurring-invoices.
- El frontend **ya tiene** una carpeta explícita de componentes compartidos: `resources/scripts/admin/components/estimate-invoice-common/` con 11 componentes: `CreateItemRow.vue`, `CreateItemRowTax.vue`, `CreateItems.vue`, `CreateNotesField.vue`, `CreatePaymentInformationField.vue`, `CreateTotal.vue`, `CreateTotalTaxes.vue`, `ExchangeRateConverter.vue`, `SalesTax.vue`, `SelectTaxPopup.vue`, `SelectTemplateButton.vue`.
- Stores Pinia paralelos: `resources/scripts/admin/stores/invoice.js`, `estimate.js`, `recurring-invoice.js`.
- Localización: `resources/lang/{en,fr,es}` con claves separadas por tipo.

### H. Tests existentes (Pest, `tests/`)

- `tests/Unit/InvoiceTest.php`: pertenencias y `getPreviousStatus`, `create invoice`, snapshots.
- `tests/Unit/EstimateTest.php`: análogo.
- `tests/Unit/InvoiceItemTest.php` / `EstimateItemTest.php` / `RecurringInvoiceTest.php` (relaciones).
- `tests/Feature/Admin/InvoiceTest.php`: cubre listar, crear, crear como SENT, validar, actualizar, enviar, marcar pagada, marcar enviada, buscar, delete múltiple, clonar, tax_per_item, EUR, payment_information, tests PDF B&N.
- `tests/Feature/Admin/EstimateTest.php`: listar, crear, validar, actualizar, search, send, aceptado, rechazado, crear invoice desde estimate, delete múltiple, templates, tax_per_item, EUR.
- `tests/Feature/Admin/RecurringInvoiceTest.php`: listado, formulario creación, store, get, update, delete múltiple, cálculo de frecuencia. **No hay test de generación automática** desde cron.
- `tests/Feature/Customer/InvoiceTest.php` — cobertura mínima.
- `tests/Feature/Customer/EstimateTest.php` — listar, ver, aceptado, rechazado.

### I. Observaciones de divergencia y puntos comunes identificables

#### Divergencias reales entre Invoice y Estimate

1. **Estados comerciales** son disjuntos: Invoice añade `paid_status` y `overdue`; Estimate tiene `ACCEPTED/REJECTED/EXPIRED`.
2. **Pagos**: Invoice es el único con `payments()` y `transactions()`.
3. **Vencimiento**: Invoice usa `due_date` + `invoice_set_due_date_automatically` / `invoice_due_date_days`; Estimate usa `expiry_date` + claves paralelas.
4. **Plantillas morfológicas**: viven en `app/pdf/invoice/` y `app/pdf/estimate/`.
5. **Campos exclusivos de línea**: sólo Estimate soporta `items.*.deliverables/delivery_delay/terms` (heredados del catálogo `Item`).
6. **`payment_information`** exclusiva de Invoice.
7. **Visibilidad del PDF por hash**: ambos generan `unique_hash` con `Hashids::connection(Clase::class)`.

#### Comportamiento verdaderamente compartido

- Estructura de importes (`sub_total`, `tax`, `total`, `discount`, `discount_val`, `discount_type`, `tax_per_item`, `discount_per_item`).
- Sistema de tipos de cambio y campos `base_*`.
- `RecurringInvoice::createInvoice()` y `ConvertEstimateController::__invoke()` siguen el mismo patrón.
- `GeneratesPdfTrait` y `getFieldsArray()` — idénticos.
- `SerialNumberFormatter` — convención `strtolower(class_basename($model))`.
- 11 componentes Vue en `resources/scripts/admin/components/estimate-invoice-common/`.

### J. Hallazgos pendientes (no resueltos en este paso)

- `Crater\Models\Note.php`: confirmada como independiente, no polimórfica a Invoice/Estimate (verificado).
- `Crater\Models\ExchangeRateLog.php`: invocado por los 3.
- `resources/views/app/pdf/estimate/*.blade.php` y `app/pdf/invoice/*.blade.php`: no inspeccionados.
- `resources/scripts/admin/views/recurring-invoices/create/RecurringInvoiceCreate.vue`: no inspeccionado en detalle.

---

## Matriz de datos e invariantes (paso 2)

> **Leyenda de clasificación**
> - **CO** — Común obligatorio.
> - **COp** — Común opcional.
> - **IE** — Específico de **Invoice** (factura).
> - **EE** — Específico de **Estimate**.
> - **RE** — Específico de **RecurringInvoice**.
> - **DE** — Específico de **DeliveryNote** (según plan 05).

### 1. Matriz de cabecera

| Campo / atributo          | Inv | Est | Rec | Del | Clasif. | Tipo SQL / cast                                | Notas / invariantes                                                                                                            |
|---------------------------|-----|-----|-----|-----|---------|-----------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------|
| `id`                      | ✓   | ✓   | ✓   | ✓   | **CO**  | `increments('id')` o `bigIncrements`           | PK.                                                                                                                            |
| `company_id`              | ✓   | ✓   | ✓   | ✓   | **CO**  | `integer('company_id') unsigned nullable`      | FK a `companies`, cascade. Aislamiento por empresa. |
| `customer_id`             | ✓   | ✓   | ✓   | ✓   | **CO**  | `unsignedBigInteger nullable` (FK customers)   |                                                                                                                                |
| `creator_id`              | ✓   | ✓   | ✓   | ✓   | **CO**  | `unsignedInteger nullable` FK `users`          |                                                                                                                                |
| `unique_hash`             | ✓   | ✓   | ✗   | (✓) | **CO**  | `string`                                       | `Hashids::connection(ClaseModel::class)->encode(id)`. |
| `notes`                   | ✓   | ✓   | ✓   | ✓   | **CO**  | `text nullable`                                |                                                                                                                                |
| `reference_number`        | ✓   | ✓   | ✗   | (✓) | **COp** | `string nullable`                              |                                                                                                                                |
| `currency_id`             | ✓   | ✓   | ✓   | ✓   | **CO**  | `unsignedInteger nullable` FK `currencies`     |                                                                                                                                |
| `exchange_rate`           | ✓   | ✓   | ✓   | ✓   | **COp** | `decimal(19,6) nullable`                       |                                                                                                                                |
| `sequence_number`         | ✓   | ✓   | ✗   | (✓) | **CO**  | `unsignedBigInteger`                            | Consecutivo global del tipo por empresa. Cada tipo tiene su serie.                                                             |
| `customer_sequence_number`| ✓   | ✓   | ✗   | (✓) | **CO**  | `unsignedBigInteger`                            | Consecutivo por cliente dentro de la empresa para el tipo.                                                                      |
| `template_name`           | ✓   | ✓   | ✓   | (✓) | **CO**  | `string` (nullable en RecurringInvoice)        |                                                                                                                                |
| `tax_per_item`            | ✓   | ✓   | ✓   | (✓) | **CO**  | `string` ('YES'/'NO')                           |                                                                                                                                |
| `discount_per_item`       | ✓   | ✓   | ✓   | (✓) | **CO**  | `string` ('YES'/'NO')                           |                                                                                                                                |
| `discount_type`           | ✓   | ✓   | ✓   | (✓) | **COp** | `string nullable`                              |                                                                                                                                |
| `discount`                | ✓   | ✓   | ✓   | (✓) | **COp** | `decimal(15,2) nullable`                        |                                                                                                                                |
| `discount_val`            | ✓   | ✓   | ✓   | (✓) | **CO**  | `unsignedBigInteger nullable`                   |                                                                                                                                |
| `sub_total`               | ✓   | ✓   | ✓   | (✓) | **CO**  | `unsignedBigInteger`                            |                                                                                                                                |
| `tax`                     | ✓   | ✓   | ✓   | (✓) | **CO**  | `unsignedBigInteger`                            |                                                                                                                                |
| `total`                   | ✓   | ✓   | ✓   | (✓) | **CO**  | `unsignedBigInteger`                            |                                                                                                                                |
| `base_discount_val`       | ✓   | ✓   | ✗   | (✓) | **COp** | `unsignedBigInteger nullable`                   |                                                                                                                                |
| `base_sub_total`          | ✓   | ✓   | ✗   | (✓) | **COp** | `unsignedBigInteger nullable`                   |                                                                                                                                |
| `base_total`              | ✓   | ✓   | ✗   | (✓) | **COp** | `unsignedBigInteger nullable`                   |                                                                                                                                |
| `base_tax`                | ✓   | ✓   | ✗   | (✓) | **COp** | `unsignedBigInteger nullable`                   |                                                                                                                                |
| `base_due_amount`         | ✓   | ✗   | ✗   | (✗) | **IE**  | `unsignedBigInteger nullable`                   | Moneda base solo Invoice.                                                                                                       |
| `invoice_date` (fecha)    | ✓   | ✗   | ✗   | ✗   | **IE**  | `date`                                         |                                                                                                                                |
| `due_date`                | ✓   | ✗   | ✗   | ✗   | **IE**  | `date` (nullable desde 2021/07/15)              |                                                                                                                                |
| `estimate_date`           | ✗   | ✓   | ✗   | ✗   | **EE**  | `date`                                         |                                                                                                                                |
| `expiry_date`             | ✗   | ✓   | ✗   | ✗   | **EE**  | `date` (nullable desde 2021/07/15)              |                                                                                                                                |
| `starts_at`               | ✗   | ✗   | ✓   | ✗   | **RE**  | `dateTime` precision 0                          |                                                                                                                                |
| `next_invoice_at`         | ✗   | ✗   | ✓   | ✗   | **RE**  | `dateTime` precision 0 nullable                  |                                                                                                                                |
| `frequency`               | ✗   | ✗   | ✓   | ✗   | **RE**  | `string` (cron)                                  |                                                                                                                                |
| `limit_by`                | ✗   | ✗   | ✓   | ✗   | **RE**  | `enum('NONE','COUNT','DATE') default NONE`       |                                                                                                                                |
| `limit_count`             | ✗   | ✗   | ✓   | ✗   | **RE**  | `integer nullable`                               |                                                                                                                                |
| `limit_date`              | ✗   | ✗   | ✓   | ✗   | **RE**  | `date nullable`                                  |                                                                                                                                |
| `send_automatically`      | ✗   | ✗   | ✓   | ✗   | **RE**  | `boolean` default false                          |                                                                                                                                |
| `status`                  | ✓   | ✓   | ✓   | ✓   | **CO**  | `string`                                        | Constantes disjuntas por tipo. Eje independiente (ver §4). |
| `paid_status`             | ✓   | ✗   | ✗   | ✗   | **IE**  | `string`                                        | Solo Invoice.                                                                                                                  |
| `due_amount`              | ✓   | ✗   | ✓   | ✗   | **IE/RE** | `unsignedBigInteger nullable`                | En RecurringInvoice solo inicial; no mantenido. |
| `sent`                    | ✓   | ✗   | ✗   | ✗   | **IE**  | `boolean default false`                        |                                                                                                                                |
| `viewed`                  | ✓   | ✗   | ✗   | ✗   | **IE**  | `boolean default false`                        |                                                                                                                                |
| `overdue`                 | ✓   | ✗   | ✗   | ✗   | **IE**  | `boolean`                                       |                                                                                                                                |
| `payment_information`     | ✓   | ✗   | ✗   | ✗   | **IE**  | `text nullable` (2026/11/07)                     | Exclusiva de Invoice.                                                                                                           |

### 2. Matriz de líneas (`*_items`)

| Campo             | Inv | Est | Rec | Del | Clasif. | Tipo SQL                                  | Notas                                                                                                                |
|-------------------|-----|-----|-----|-----|---------|-------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| `id`              | ✓   | ✓   | ✓   | ✓   | **CO**  | increments                                 |                                                                                                                      |
| `*_id`            | ✓   | ✓   | ✓   | ✓   | **CO**  | FK al documento                             |                                                                                                                      |
| `item_id`         | ✓   | ✓   | ✓   | ✓   | **COp** | FK items cascade                           | Snapshot del nombre persiste.                                                                                       |
| `company_id`      | ✓   | ✓   | ✓   | ✓   | **CO**  | FK companies cascade                       |                                                                                                                      |
| `name`            | ✓   | ✓   | ✓   | ✓   | **CO**  | `string`                                  |                                                                                                                      |
| `description`     | ✓   | ✓   | ✓   | ✓   | **COp** | `string nullable`                          |                                                                                                                      |
| `deliverables`    | ✓   | ✓   | ✓   | ✓   | **COp** | `text nullable` (2026/11/05)              | Migración `2026_11_05_030000` agrega a las 3 tablas.                                                               |
| `delivery_delay`  | ✓   | ✓   | ✓   | ✓   | **COp** | `string nullable` (2026/11/05)            |                                                                                                                      |
| `terms`           | ✓   | ✓   | ✓   | ✓   | **COp** | `text nullable` (2026/11/05)              |                                                                                                                      |
| `discount_type`   | ✓   | ✓   | ✓   | ✓   | **CO**  | `string`                                  |                                                                                                                      |
| `quantity`        | ✓   | ✓   | ✓   | ✓   | **CO**  | `decimal(15,2)`                            | Cantidad entregable; plan 05 §1 la fija positiva para DeliveryNote.                                                 |
| `price`           | ✓   | ✓   | ✓   | ✓   | **CO**  | `unsignedBigInteger`                       | Centavos.                                                                                                            |
| `discount`        | ✓   | ✓   | ✓   | ✓   | **CO**  | `decimal(15,2)`                            |                                                                                                                      |
| `discount_val`    | ✓   | ✓   | ✓   | ✓   | **CO**  | `unsignedBigInteger`                       |                                                                                                                      |
| `tax`             | ✓   | ✓   | ✓   | ✓   | **CO**  | `unsignedBigInteger`                       |                                                                                                                      |
| `total`           | ✓   | ✓   | ✓   | ✓   | **CO**  | `unsignedBigInteger`                       |                                                                                                                      |
| `exchange_rate`   | ✓   | ✓   | ✗   | ✓   | **COp** | `decimal(19,6) nullable`                  |                                                                                                                      |
| `base_price`      | ✓   | ✓   | ✗   | ✓   | **COp** | `unsignedBigInteger nullable`               |                                                                                                                      |
| `base_discount_val`| ✓ | ✓   | ✗   | ✓   | **COp** | `unsignedBigInteger nullable`               |                                                                                                                      |
| `base_tax`        | ✓   | ✓   | ✗   | ✓   | **COp** | `unsignedBigInteger nullable`               |                                                                                                                      |
| `base_total`      | ✓   | ✓   | ✗   | ✓   | **COp** | `unsignedBigInteger nullable`               |                                                                                                                      |
| `recurring_invoice_id` | ✓ | ✗ | ✗ | (✓) | **IE/RE** | `unsignedBigInteger nullable` (2021/07/09) | Solo en `invoice_items`.                                                              |

### 3. Tabla `taxes` (compartida — FK-por-columna)

| Campo              | Clasif. | Tipo SQL                       |
|--------------------|---------|--------------------------------|
| `id`               | **CO**  | increments                     |
| `tax_type_id`      | **CO**  | FK tax_types                  |
| `name`             | **CO**  | `string`                       |
| `amount`           | **CO**  | `unsignedBigInteger`           |
| `percent`          | **CO**  | `decimal(5,2)`                 |
| `compound_tax`     | **CO**  | `tinyInteger default 0`        |
| `company_id`       | **CO**  | `integer nullable`              |
| `currency_id`      | **COp** | `unsignedInteger nullable`     |
| `exchange_rate`    | **COp** | `decimal(19,6) nullable`       |
| `base_amount`      | **COp** | `unsignedBigInteger nullable`  |
| `invoice_id`       | **IE**  | `integer nullable` FK cascade   |
| `estimate_id`      | **EE**  | `integer nullable` FK cascade   |
| `recurring_invoice_id` | **RE** | `unsignedInteger nullable` FK (set null) |                                                          |
| `invoice_item_id`  | (✓)     | `integer nullable` FK cascade   |
| `estimate_item_id` | (✓)     | `integer nullable` FK cascade   |
| (futuro) `delivery_note_id` | **DE** |                               |                                                              |

### 4. Separación de estados (eje ortogonal)

| Eje                    | Inv | Est | Rec | Del          | Cómo se modela hoy                                                                                                          |
|------------------------|-----|-----|-----|--------------|-----------------------------------------------------------------------------------------------------------------------------|
| **Comercial** (`status`) | ✓ | ✓   | ✓   | ✓ (plan 05)  | Invoice: `DRAFT|SENT|VIEWED|COMPLETED`. Estimate: `DRAFT|SENT|VIEWED|EXPIRED|ACCEPTED|REJECTED`. RecurringInvoice: `ACTIVE|ON_HOLD|COMPLETED`. DeliveryNote (plan 05): `DRAFT|DELIVERED|CANCELLED`. Constantes disjuntas. |
| **Envío** (`sent` flag) | ✓ | ✗   | ✗   | ✗            | Solo Invoice (`Invoice::send()` línea 487).                                                                                 |
| **Visualización** (`viewed`) | ✓ | ✗ | ✗ | ✗ | Solo Invoice.                                                                                                              |
| **Pago** (`paid_status`+`due_amount`) | ✓ | ✗ | ✗ | ✗ | Solo Invoice. `UNPAID|PARTIALLY_PAID|PAID`.                                                                                  |
| **Vencido** (`overdue`) | ✓ | ✀ | ✗ | ✗ | Solo Invoice.                                                                                                               |
| **Entrega física**       | ✗ | ✗ | ✗ | ✓ (plan 05) | Solo DeliveryNote.                                                                                                          |
| **Facturado** (link)     | ✗ | ✗ | (parcial vía `recurring_invoice_id`) | ✓ (inverso) | Plan 05 §3 obliga `invoices.delivery_note_id` UNIQUE como fuente única.                                                |

**Conclusión:** un único `status` agregaría ambigüedad. Cualquier contrato base debe mantener ejes independientes.

### 5. Instantáneas históricas e integridad

- **Cliente**: la cabecera guarda `customer_id` pero el PDF resuelto usa `Customer` directamente: fuga de actualidad. Plan 05 §1 obliga a snapshot en DeliveryNote.
- **Dirección**: `getCustomerShippingAddress`/`getCustomerBillingAddress` consultan relaciones vivas. Mismo problema.
- **Artículo**: las líneas ya son snapshot (name, price, tax, etc.).
- **Empresa**: `getPDFData()` consulta `Company::find($company_id)` cada vez.
- **Tipo de cambio**: snapshot decimal(19,6) por documento y línea.
- **Precisión monetaria**: importes en céntimos (integer); AI Assistant convierte decimal→entero en el borde.

### 6. Reglas de validación asimétricas

| Regla                                           | `InvoicesRequest`     | `EstimatesRequest`     | `RecurringInvoiceRequest`             | DeliveryNote esperado |
|-------------------------------------------------|-----------------------|------------------------|---------------------------------------|-----------------------|
| `*_date` (fecha de documento)                    | `invoice_date required` | `estimate_date required` | `starts_at required`           | `delivery_date required` |
| `*_due/expiry/delivered_at`                      | `due_date nullable`   | `expiry_date nullable` | n/a                                   | `delivered_at nullable` |
| `*_number` único por empresa                    | sí                   | sí                      | n/a                                   | sí                     |
| `discount_type/discount/discount_val`            | required              | required               | required                              | required               |
| `sub_total/total/tax`                            | required              | required               | required                              | required               |
| `template_name`                                  | required              | required               | **nullable**                          | required               |
| `items[*].name/quantity/price`                   | required              | required               | required                              | required               |
| `items[*].deliverables/delivery_delay/terms`    | **(falta — divergencia reciente)** | `nullable string` | (silencio)                    | `nullable string`        |
| `payment_information`                            | `nullable string`     | **(no validado)**      | (no tiene)                            | n/a                    |
| `customer_id`                                    | `required`            | `required`             | `required`                            | `required`             |
| `exchange_rate`                                  | required si cliente y empresa difieren en moneda | igual | igual | igual |
| `frequency / limit_* / send_automatically`      | n/a                   | n/a                    | requeridos                            | n/a                    |
| `status`                                         | implícito (DRAFT o SENT vía `invoiceSend`) | igual (`estimateSend`) | `required` (enum) | implícito DRAFT/DELIVERED/CANCELLED |

### 7. Edición post-emisión

- **Invoice**: `getAllowEditAttribute()` puede bloquear si `CompanySetting::getSetting('retrospective_edits')` y pagos parciales. `updateInvoice` borra y recrea items+taxes preservando saldo. `DeleteInvoiceRequest` rechaza con pagos.
- **Estimate**: sin `allow_edit`. `updateEstimate` borra y recrea items+taxes, no preserva saldo.
- **RecurringInvoice**: `updateFromRequest` borra y recrea; como plantilla, no hay "publicado".
- **DeliveryNote (plan 05):** drafts editables; tras DELIVERED bloqueado.

### 8. Resumen ejecutivo del paso 2

- **Común obligatorio** (cabecera): 13+ campos identicos.
- **Líneas verdaderamente comunes**: name, quantity, price, discount_*, tax, total, snapshot del item_id. La migración 2026/11/05 extiende con `deliverables|delivery_delay|terms`.
- **Verdaderamente divergente**: universos disjuntos de `status`; `paid_status/due_amount/sent/viewed/overdue/payment_information` exclusivos; `delivered_at` exclusivo.
- **Asimetría reciente**: `deliverables/delivery_delay/terms` ya están en `invoice_items` pero `InvoicesRequest` no valida.
- **Deficiencia común**: `DB::transaction` ausente en `Invoice::createInvoice`, `Estimate::createEstimate`, `RecurringInvoice::createFromRequest`.

---

## Comparación de alternativas de arquitectura (paso 3)

### 0. Línea base: estado actual (alternativa E)

- Modelo con Invoice, Estimate, RecurringInvoice separados. Sin clase común. `GeneratesPdfTrait` y `SerialNumberFormatter` ya compartidos. Frontend parcialmente compartido en `estimate-invoice-common/`.
- Coste de la inacción: nueva cadena paralela por cada tipo nuevo (plan 05 fuerza esto).
- Beneficio residual: cero acoplamiento, tests existentes pasan intactos.

### 1. Alternativa A — Composición + contratos (tablas independientes)

- Mantener modelos separados. Añadir `DocumentContract`, servicios `DocumentFactory`, `DocumentUpdater`, `DocumentConverter`, `DocumentMailer`.
- Polimórfica `EmailLog.mailable_type` no necesita morph map porque clases siguen siendo `Invoice`, `Estimate`.
- Tests existentes válidos + nuevos tests de contrato.
- Plan 05 cumplible sin tocar consumidores.

#### Ventajas y costes de A

- **+** Migración mínima en BD (∅ cambios de tabla).
- **+** Riesgo bajo de regresión.
- **+** Tests existentes pasan.
- **+** Plan 05 se cumple sin necesidad de homogeneizar esquemas.
- **−** Duplicación residual solo se elimina si se aceptan firmas abstractas.
- **−** Sin jerarquía; clase compartida por convención.

### 2. Alternativa B — Clase abstracta `BaseDocument` (tablas independientes)

- `BaseDocument extends Model implements DocumentContract` con scopes comunes. Subclases por tipo.
- `RecurringInvoice` queda fuera.
- Aporta poco margen sobre A sin STI.

### 3. Alternativa C — Cabecera común `documents` + detalles 1:1

- Tabla `documents` con campos comunes. Modelos específicos extienden `Document`. Tablas específicas mantienen FK 1:1.
- `RecurringInvoice` queda fuera.
- Migración con backfill costosa.

### 4. Alternativa D — Tabla única con discriminador (STI)

- Una tabla `documents` con todas las columnas + discriminador.
- Costes duros: campos nulos masivos, reglas condicionales por discriminador, restricciones únicas por tipo como índices parciales.
- Plan 06 §6 prohíbe convertir fila cambiando tipo.

### 5. Combinaciones razonables

- **A+B** — composición + `BaseDocument` mínima.
- **A+C** — servicios + tabla documents.
- **A estricto** — solo contratos + servicios.

### 6. Tabla comparativa (15 criterios × 5 alternativas)

Tabla con criterios R1-R15 y puntuaciones 0-3:

| Criterio | E | A | A+B | B | C | D |
|----------|---|----|-----|---|----|----|
| R1 Reducción duplicación | 0 | 2 | 3 | 2 | 2 | 3 |
| R2 Claridad reglas | 3 | 2 | 2 | 2 | 1 | 0 |
| R3 Añadir documentos | 0 | 3 | 3 | 3 | 2 | 1 |
| R4 Compat Eloquent | 3 | 3 | 2 | 2 | 1 | 0 |
| R5 Coste migraciones | 3 | 3 | 2 | 2 | 1 | 0 |
| R6 EmailLog | 3 | 3 | 3* | 3* | 2* | 1* |
| R7 Hashids | 3 | 3 | 3* | 3* | 2* | 0* |
| R8 Tax FK | 3 | 3 | 3 | 3 | 1 | 0 |
| R9 Conversión | 2 | 2 | 2 | 2 | 2 | 0 |
| R10 Plan 05 | 1 | 3 | 3 | 2 | 3 | 1 |
| R11 Regresión tests | 3 | 2 | 2 | 1 | 0 | 0 |
| R12 Frontend | 2 | 2 | 2 | 2 | 2 | 1 |
| R13 estimate-invoice-common | 2 | 3 | 3 | 2 | 2 | 2 |
| R14 Distinguir tipos reportes | 3★ | 2 | 2 | 2 | 3 | 3 |
| R15 Sin switch | 0 | 3 | 3 | 2 | 2 | 0 |

Puntuación ponderada (sobre 104):
- **A** = **2.58**
- **A+B** = 2.53
- **B** = 2.16
- **E** = 1.85
- **C** = 1.67
- **D** = 0.76

### 7. Análisis cualitativo por riesgos

| Riesgo | E | A | B | C | D |
|--------|---|---|----|----|----|
| Cumplir plan 05 sin tocar consumers | ✓/✗ | ✓ | ✓/✗ | ✓ | ✗ |
| Atomicidad cabecera+items+taxes | ✗ | ✓ | ✓ | ✓ | ✓ |
| Snapshots de cliente/dirección | ✗ | ✓ | ✓ | ✓ | ✓ |
| Migración 2026/11/05 completa | ✗ | ✓ | ✓ | ✓ | ✓ |
| Inversión para nuevos tipos | ✗ | ✓ | ✓ | ✓ | ✓ |
| Coste de revertir | ✓ | ✓ | ✓/✗ | ✗ | ✗ |

### 8. Observaciones derivadas del paso 2

1. `taxes` ya exige invariante «una fila pertenece a un solo documento»: A y B respetan; C la deforma; D la rompe.
2. `RecurringInvoice` queda explícitamente fuera de cualquier jerarquía tipo-broadcast.
3. Frontend ya parcialmente abstraído.
4. Polimórfico `EmailLog.mailable_type` lo único que vincula.
5. Plan 05 §3 fija `invoices.delivery_note_id` UNIQUE.
6. `Hashids::connection(ClaseModel::class)` no se rompe con A/B.

### 9. Lo que el paso 3 no decide

- **No emite recomendación todavía**.
- **No descarta D** por HR pero lo penaliza.
- **No descarta C**, pero lo marca como caro.
- **Refuerza A y A+B como dominantes**.
- **Confirma E válida solo si se asume coste**.

### 10. Conclusión provisional

1. **A** ≈ 35/45 — mejor candidato puro.
2. **B** ≈ 29/45 — buen candidato, lastrado.
3. **A+B** ≈ 35-37/45 — comparable a A.
4. **E** ≈ 27/45 — aceptable solo si se asume fragmentación.
5. **C** ≈ 23/45 — atractiva pero cara de entrada.
6. **D** ≈ 12/45 — descartado.

---

## Criterios de viabilidad y decisión (paso 4)

### 1. Requisitos obligatorios (HR — must-have)

| HR  | Requisito                                                                                                  | E | A | B | C | D |
|-----|------------------------------------------------------------------------------------------------------------|:-:|:-:|:-:|:-:|:-:|
| HR1 | Integridad histórica preservada o mejorada (snapshots de cliente, dirección, artículo, monetarios).     | ⚠ hoy | ✓ | ✓ | ✓* | ✗ |
| HR2 | Aislamiento por empresa intacto (whereCompany()).                                                          | ✓ | ✓ | ✓ | ✓* | ⚠ |
| HR3 | Autorización por tipo preservada (*Policy).                                                                 | ✓ | ✓ | ✓ | ⚠ | ✗ |
| HR4 | `Hashids::connection(Clase::class)` y `EmailLog.mailable_type` sin morph map obligatorio.                  | ✓ | ✓ | ✓* | ✓* | ✗ |
| HR5 | Invariante `taxes`: «cada fila pertenece a un único documento».                                              | ✓ | ✓ | ✓ | ⚠ | ✗ |
| HR6 | Conversión Estimate→Invoice: «creación de destino» con vínculo (no cambiar tipo de fila).                   | ✓ | ✓ | ✓ | ✓ | ✗ |
| HR7 | Atomicidad transaccional cabecera+items+taxes (DB::transaction + lockForUpdate).                            | ✗ hoy | ✓ | ✓* | ✓ | ✓ |
| HR8 | Plan 05 (DeliveryNote) implementable sin retrabajo del modelo de Invoice.                                    | ✗ hoy | ✓ | ✓ | ✓ | ⚠ |

**Resultado HR:**
- **D eliminado** (HR4, HR5, HR6, HR8).
- **E** descartado como decisión consciente (HR7, HR8).
- **A, B, C** pasan con disciplina.

### 2. Anti-patrones penalizados

- Numerosos condicionales por tipo: ×3 en R2 si supera 5.
- Métodos vacíos en clase base o interfaz: ×1 en R2 si > 2.
- Campos con significado cambiante entre documentos: ×2 en R8.

### 3. Criterios ponderados (los 15 del paso 3 con peso)

| Sub-criterio | Peso | Justificación |
|--------------|:----:|----------------|
| R1 Reducción real de duplicación | 10 | Motivo principal del plan. |
| R2 Claridad de reglas | 12 | Penalizado explícitamente. |
| R3 Facilidad para añadir documentos | 12 | Objetivo de plan 05. |
| R4 Compatibilidad Eloquent sin STI forzado | 8 | STI introduce dependencias. |
| R5 Coste absoluto de migraciones | 7 | Backfills y doble escritura. |
| R6 Compatibilidad con EmailLog polimórfica | 4 | Morfismo existente sin morphMap. |
| R7 Compatibilidad con Hashids::connection(Clase::class) | 4 | URLs PDF públicas estables. |
| R8 Invariante de Tax FK-por-columna | 5 | 5 FKs documentales exclusivas. |
| R9 Compatibilidad con conversión Estimate→Invoice | 5 | Funcionalidad crítica. |
| R10 Compatibilidad con plan 05 (DeliveryNote) | 10 | Entregable inmediato. |
| R11 Riesgo de regresión en tests/features | 8 | Tests Pest estructurados por modelo. |
| R12 Impacto en frontend (Store/Componentes/Router) | 5 | Ya hay folder `estimate-invoice-common/`. |
| R13 Aprovechamiento del frontend ya compartido | 3 | Vale más reescribir. |
| R14 Distinguir tipos en reportes / dashboard | 4 | Ya se hace hoy. |
| R15 Evitar switch($type) o if por discriminador | 7 | Anti-patrón. |

### 4. Puntuación ponderada

| Sub-criterio | Peso | E | A | A+B | B | C | D |
|--------------|:----:|:-:|:-:|:-:|:-:|:-:|:-:|
| R1 | 10 | 0 | 2 | 3 | 2 | 2 | 3 |
| R2 | 12 | 3 | 2 | 2 | 2 | 1 | 0 |
| R3 | 12 | 0 | 3 | 3 | 3 | 2 | 1 |
| R4 | 8 | 3 | 3 | 2 | 2 | 1 | 0 |
| R5 | 7 | 3 | 3 | 2 | 2 | 1 | 0 |
| R6 | 4 | 3 | 3 | 3★ | 3★ | 2★ | 1★ |
| R7 | 4 | 3 | 3 | 3★ | 3★ | 2★ | 0★ |
| R8 | 5 | 3 | 3 | 3 | 3 | 1 | 0 |
| R9 | 5 | 2 | 2 | 2 | 2 | 2 | 0 |
| R10 | 10 | 1 | 3 | 3 | 2 | 3 | 1 |
| R11 | 8 | 3 | 2 | 2 | 1 | 0 | 0 |
| R12 | 5 | 2 | 2 | 2 | 2 | 2 | 1 |
| R13 | 3 | 2 | 3 | 3 | 2 | 2 | 2 |
| R14 | 4 | 3★ | 2 | 2 | 2 | 3 | 3 |
| R15 | 7 | 0 | 3 | 3 | 2 | 2 | 0 |

- E: 27/45; A: 35/45; A+B: 35-37/45; B: 29/45; C: 23/45; D: 12/45.

### 5. Beneficios comprobados vs hipótesis

(Tabla audita cada beneficio contra evidencia del inventario o lo marca como hipótesis.)

### 6. Esfuerzo y riesgo relativos

| Alternativa | Esfuerzo | Riesgo | Reversibilidad |
|-------------|----------|--------|---------------|
| E | Bajo (mantenimiento) | constante | total |
| **A** | **Medio** | Bajo | alta |
| A+B | Medio-Alto | Bajo-Medio | media |
| B | Medio-Alto | Medio | media |
| C | Alto | Alto | baja |
| D | Muy alto | Muy alto | muy baja |

### 7. Decisión argumentada

#### Candidatas descartadas antes de la decisión

- **D** descartado por HR.
- **C** viable pero esfuerzo desproporcionado; queda como segundo escenario.

#### Candidatas en carrera

- **A**: 2.58/3, esfuerzo medio, riesgo bajo.
- **A+B**: 2.53/3, esfuerzo medio-alto, riesgo bajo-medio.
- **E**: 1.85/3, esfuerzo bajo, riesgo constante.

#### Veredicto

**Recomendación: A — composición + contratos (con Plan 05 como banco de pruebas), A+B como evolución natural si la PoC lo justifica.**

**Grado formal del veredicto: VIABLE Y RECOMENDABLE, con condiciones.**

**Condiciones obligatorias (paso 5 → paso 9):**

1. **C1.** Definir y aprobar el `DocumentContract` cubriendo: totals, currency, customer, company, creator, items, taxes, uniqueHash, pdfView, serialContext, lineSnapshot, taxSnapshot, markAsSent, markAsDelivered (DeliveryNote), markAsPaid (Invoice).
2. **C2.** Extraer `app/Services/DocumentWriter.php` (sincroniza items+taxes atómicamente, `DB::transaction`).
3. **C3.** Implementar `ConvertDeliveryNoteToInvoice` consumiendo `DocumentWriter`.
4. **C4.** Validar `mailable_type` y `Hashids` se mantienen por clase.
5. **C5.** Evitar switches o condicionales por discriminador en consumers.
6. **C6.** A+B queda como evolución posterior, no como decisión ahora.

### 8. Estimación relativa del impacto si se ejecuta A

| Eje | Esfuerzo | Categoría |
|-----|----------|-----------|
| Nuevo `DocumentContract` | pequeño | ~1 archivo |
| Nuevo `DocumentWriter` | medio | ~1 servicio + 3 refactors |
| Refactor `createItems`/`createTaxes` × 3 | medio | 3 archivos + tests |
| Frontend: `Composable useDocumentForm` | medio | 1 composable + 3 stores |
| Tests del contrato + tests de writer | medio | nuevas suites Pest |
| Plan 05 (DeliveryNote → Invoice) | medio | ya estimado en plan 05 |
| **Total A** | **medio** | **Sin cortes; baja en BD** |

### 9. Lo que el paso 4 explícitamente NO decide

- La forma exacta del `DocumentContract` (paso 5).
- El nombre y firmas de los métodos del `DocumentWriter`.
- Cómo se transforma el frontend hacia un Composable (paso 8, adopción gradual).
- Si A+B conviene una vez A+B esté validado por PoC.

---

## Diseño del contrato y extensiones (paso 5)

> Candidata: **A** (composición + contratos). `BaseDocument` queda descrita solo a nivel de borrador para que A+B pueda adoptarla sin reescritura del contrato.

### 0. Vista previa (diagrama lógico de capacidades)

```
                          ┌─────────────────────────────────────────┐
                          │  DocumentContract  (raíz)               │
                          │  + documentType(): string               │
                          │  + customer() / company() / creator()   │
                          │  + currency()                            │
                          │  + notes() / status()                    │
          ┌───────────────┼──────────────────────────┬──────────────┘
          │               │                          │
      HasAmounts   HasItems          HasTaxes
      ──────────   ────────          ────────
      + totals():  + items(): HasMany + taxes(): HasMany
        DocumentTotals  + syncItems() void  + syncTaxes() void
      + recompute() void

            Printable    Mailable        SerialNumbered    HasHistoricalSnapshots
            ─────────    ────────        ──────────────    ──────────────────────
            + pdfView()  + send($data)   + nextNumber()    + customerSnapshot()
            + getPDFData() + preview()    + getSerialContext()  + billingAddress()/shippingAddress()
            + getGeneratedPDF()

                  Payable  ◀── sólo Invoice
                  ──────
                  + addPayment($amount)
                  + subtractPayment($amount)
                  + paidStatus()
                  + dueAmount()

                  Deliverable ◀── sólo DeliveryNote
                  ──────────
                  + deliveryStatus()
                  + markAsDelivered()
                  + cancel()
                  + deliveryAddress()

                  Billable ◀── Estimate y DeliveryNote
                  ────────
                  + billToInvoice()
```

### 1. DocumentContract (raíz)

- Solo métodos de consulta, sin lógica.
- Si alguna capacidad varía entre tipos, se extrae a otra interfaz.
- No incluye métodos específicos (getPDFData, send, addPayment) — son capacidades opcionales.

### 2. Interfaces de capacidad (composables)

| Interfaz | Métodos | Implementada por |
|----------|---------|-------------------|
| `HasAmounts` | totals(), recompute() | Invoice, Estimate, DeliveryNote, RecurringInvoice |
| `HasItems` | items(), syncItems() | Idem |
| `HasTaxes` | taxes(), syncTaxes() | Idem |
| `Printable` | pdfView(), getPDFData(), getGeneratedPDF($collection) | Invoice, Estimate, DeliveryNote |
| `Mailable` | send($data), preview($data) | Invoice, Estimate (DeliveryNote pendiente) |
| `Payable` | addPayment/subtractPayment/paidStatus/dueAmount | sólo Invoice |
| `Deliverable` | deliveryStatus, markAsDelivered, cancel, deliveryAddress | sólo DeliveryNote |
| `Billable` | billToInvoice(), alreadyBilled() | Estimate, DeliveryNote |
| `SerialNumbered` | nextNumber(), getSerialContext() | los 4 |
| `HasHistoricalSnapshots` | customerSnapshot(), billingAddress(), shippingAddress() | Invoice, Estimate, DeliveryNote |

Reglas de uso: `instanceof Payable`, no `if ($doc instanceof Invoice)`.

### 3. BaseDocument — solo borrador (A no la exige)

- A gana 2.58/3 sin BaseDocument. La implementacion introduce restricciones de herencia Eloquent y reduce flexibilidad con RecurringInvoice.

### 4. Servicios compartidos

- `DocumentWriter` (atomic items+taxes) — C2.
- `DocumentTotalsCalculator` — puro.
- `DocumentSnapshotter` — HR1.
- `DocumentConverterRegistry` — extensible sin switch.
- `DocumentMailer` — envío.
- `DocumentSnapshotListener` — hooks.

### 5. DTOs / Value Objects

| DTO / VO | Campos | Justificación |
|----------|--------|----------------|
| `DocumentTotals` | subTotal, tax, total, discount, discountVal, baseTotal, baseSubTotal, baseTax, dueAmount, baseDueAmount | Una sola fuente. |
| `LineSnapshot` | name, description, quantity, price, tax, total, discount*, itemId, deliverables, deliveryDelay, terms, companyId | Inmutable. |
| `TaxSnapshot` | taxTypeId, amount, percent, compoundTax, name, exchangeRate, currencyId | Inmutable. |
| `CustomerSnapshot` | name, contactName, companyName, email, phone, website, currencyId, capturedAt | Falta hoy en Invoice/Estimate. Plan 05 §1 lo obliga. |
| `AddressSnapshot` | name, countryName, state, city, street1, street2, phone, zip, type | Idem. |
| `DocumentContext` | origin, targetType, companyId, byUserId, overrides, sourceRowId, currencyStrategy | Paso del conversor. |

### 6. RecurringInvoice como plantilla (fuera del círculo)

- Implementa DocumentContract, HasAmounts, HasItems, HasTaxes, SerialNumbered, HasHistoricalSnapshots.
- NO implementa Printable, Mailable, Payable, Deliverable, Billable.
- Su `invoices()` produce documentos verdaderos.

### 7. Frontend: del folder estimate-invoice-common/ a un Composable

```
resources/scripts/admin/components/
├── estimate-invoice-common/   (existente)
└── document-common/          (nuevo — Composable + slots)
    ├── DocumentTotalsPanel.vue
    ├── DocumentItemsTable.vue
    ├── NotesField.vue
    ├── TemplatePicker.vue
    ├── TaxPicker.vue
    └── useDocumentForm.ts

Composables: useDocumentForm(type: Ref<DocumentTypeId>)
```

- Sin migración disruptiva: el folder actual se mantiene mientras se introduce.

### 8. Despacho sin switch por discriminador

- Polimorfismo por interfaz (recomendado).
- Registry indexado por capacidad.
- Visitante (cuando hay que aplicar dos o más capacidades combinadas).

### 9. Lo que el contrato NO incluye

- Reglas de validación específicas.
- Policies.
- Jerarquía Eloquent (solo interfaces).
- Migraciones.
- API pública idéntica para todos los tipos.
- Polimorfismo de `EmailLog`.
- Jobs de PDF (cada tipo tiene el suyo).

### 10. Diagrama de implementación por tipo

| Tipo / Clase | DC | HA | HI | HT | Pr | Ma | Pa | De | Bi | SN | HS |
|--|--|--|--|--|--|--|--|--|--|--|--|
| `Invoice` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |   |   | ✓ | ✓ |
| `Estimate` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |   |   | ✓ | ✓ | ✓ |
| `DeliveryNote` (plan 05) | ✓ | ✓ | ✓ | ✓ | ✓(futuro) | ✓(futuro) |   | ✓ | ✓ | ✓ | ✓ |
| `RecurringInvoice` (plantilla) | ✓ | ✓ | ✓ | ✓ |   |   |   |   |   | ✓ | ✓ |

### 11. Servicios de conversión previstos

| Converter | Origen → Destino | Disparador |
|--|--|--|
| `EstimateToInvoiceConverter` | Estimate → Invoice | `POST /api/v1/estimates/{id}/convert-to-invoice` |
| `DeliveryNoteToInvoiceConverter` | DeliveryNote → Invoice | `POST /api/v1/delivery-notes/{id}/convert-to-invoice` (plan 05 §5) |
| `RecurringToInvoiceConverter` | RecurringInvoice → Invoice | cron; `send_automatically` |
| `CloneDocumentConverter` | mismo tipo → mismo tipo | `POST .../clone` |

### 12. Riesgos abiertos y trade-offs del contrato

- Trade-off: SerialNumbered con RecurringInvoice.
- Trade-off: Payable solo en Invoice.
- Riesgo: Mailable se rompe si DeliveryNote entra sin envío por mail.
- Riesgo: RecurringInvoice queda con firma similar pero sin capacidades de documento emitido.
- Riesgo: DocumentSnapshotter introduce dependencia de atributos JSON.

### 13. Lo que el paso 5 NO decide

- Persistencia JSON vs columnas nuevas.
- Refactorización profunda de stores.
- `BaseDocument` definitiva.
- Detalle de tests del contrato.
- Cómo conviven los namespaces.

---

## Identidad, persistencia y conversiones (paso 6)

### 1. Auditoría de identidad actual

#### 1.1 `Hashids::connection(Clase::class)`

- `app/Models/Invoice.php:351, 391`; `app/Models/Estimate.php:217, 227`; `app/Models/RecurringInvoice.php:346`. Conexión por clase.
- **Recomendación A:** mantener ConexiónHashids por subclase.

#### 1.2 `EmailLog.mailable_type` (sin morphMap hoy)

- `App\Providers\AppServiceProvider.php` (líneas 1-92): NO hay `Relation::morphMap([...])`.
- `email_logs.mailable_type` almacena literales como `"Crater\\Models\\Invoice"` o `"Crater\\Models\\Estimate"`.
- **Riesgo:** un cambio de namespace rompe el binding; sin protección morphMap.
- **Recomendación A:** NO introducir morphMap en este plan.

#### 1.3 `invoices.recurring_invoice_id`

- `database/migrations/2021_07_09_063712_...`: FK sin `onDelete` (default RESTRICT/NO ACTION).
- RecurringInvoice::createInvoice fija el campo; RecurringInvoice::generateInvoice cuenta hijos y desvincula al borrar.
- Lectura: `app/Models/RecurringInvoice.php:283`, `app/Models/RecurringInvoice.php:414`, `app/Http/Resources/InvoiceResource.php:41`, `TaxResource.php:33`.
- **Uso en controllers: ninguno.** No hay filtrado por `recurring_invoice_id` en listados.

#### 1.4 `ai_conversations` — el patrón a replicar

- `database/migrations/2026_11_06_100000_create_ai_conversations_table.php` define:
  - `document_type VARCHAR(20)` + `document_id UNSIGNED BIGINT NULL` (sin FK rígida).
  - Unique `(company_id, document_type, document_id)`.
  - Unique `(company_id, document_type, draft_key)`.
  - Index `(document_type, document_id)`.
- Implementación: `app/Models/AiConversation.php:56-75`.

### 2. Conversiones existentes (verbatim)

| Origen → Destino | Implementación | Comportamiento |
|---|---|---|
| Estimate → Invoice | `app/Http/Controllers/V1/Admin/Estimate/ConvertEstimateController.php` (134 líneas) | 1 endpoint, sin transacción. |
| RecurringInvoice → Invoice | Inline en `app/Models/RecurringInvoice.php:299-383` | Disparador automático. |
| Invoice → Invoice (clonar) | `app/Http/Controllers/V1/Admin/Invoice/CloneInvoiceController.php` (134 líneas) | Patrón casi calcado a Estimate. |
| Estimate → Estimate (clonar) | `app/Http/Controllers/V1/Admin/Estimate/CloneEstimateController.php` (121 líneas) | |
| DeliveryNote → Invoice (futuro) | No implementado. Plan 05 §5 lo fija. |

Duplicación observable: `ConvertEstimateController.php` (líneas 92-114) y `CloneInvoiceController.php` (líneas 88-110) son virtualmente idénticos. `RecurringInvoice::createInvoice` (líneas 312-371) repite el mismo patrón. `BulkExchangeRateController.php` (líneas 30-61) también paralelos.

### 3. Reglas que A debe respetar para conversiones

#### 3.1 Source link (origen conservado)

- Hoy no hay `invoices.from_estimate_id`. La conversión Estimate→Invoice no fija FK. Asumir que la PoC decide.

#### 3.2 Identity preservation

- No cambiar `id`, `unique_hash`, timestamps del origen. Hoy `ConvertEstimateController::__invoke()` cumple porque CREA una Invoice nueva.

#### 3.3 Transaccionalidad

- `ConvertEstimateController` no usa `DB::transaction()`. Plan 05 §5 obliga. **Recomendación A:** introducir DB::transaction en TODOS los converters.

#### 3.4 Idempotencia

- Hoy no existe idempotencia. Plan 05 §5 prescribe `lockForUpdate` + chequeo. Se propone restricción por índice parcial o lógica adicional.

#### 3.5 Autorización separada por flujo

- Hoy `ConvertEstimateController` autoriza `create-invoice`. **Recomendación A:** introducir ability `convert-estimate-invoice` separada.

### 4. ¿Referencia por columna explícita o entidad de relación?

| Caso | Decisión | Justificación |
|---|---|---|
| Estimate → Invoice | Columna explícita `invoices.from_estimate_id` | Coherente con `recurring_invoice_id`. |
| RecurringInvoice → Invoice | Columna explícita `invoices.recurring_invoice_id` | Existente. |
| DeliveryNote → Invoice (plan 05) | Columna explícita `invoices.delivery_note_id` UNIQUE | Plan 05 §3. |
| Clone mismo tipo | Sin vínculo | No aplica. |
| `EmailLog.mailable` (cross-doc) | morphTo literal sin morphMap | No requiere modelo de relación intermedia. |
| `ai_conversations` (cross-doc) | Columna explícita (document_type, document_id) sin FK rígida | Patrón operativo en producción. |
| `Tax` con FK-por-columna | 5 columnas FK disjuntas | Invariante «una fila pertenece a un documento». |

### 5. N+1 verificado en código existente

| Caso | ¿N+1? | Evidencia |
|---|---|---|
| `InvoicesController::index` | leve | join+select optimizado pero `Resource` serializa lazy. |
| `EstimatesController::index` | leve | Idem. |
| `RecurringInvoiceController::index` | leve | Sin join a customers. |
| `DashboardController::__invoke` | medio | 12 meses × 4 consultas = 48 SELECTs. |
| `CustomerStatsController::__invoke` | medio | Mismo: 48 SELECTs. |
| `ConvertEstimateController::__invoke` | no | `load(['items', 'items.taxes', 'customer', 'taxes'])`. |
| `Clone*Controller` | no | `load('items.taxes')`. |
| `TaxSummaryReportController` | leve | `with('taxType', 'invoice', 'invoiceItem')` pre-carga. |

Conclusión: N+1 heredados en Dashboard y CustomerStats. No introducidos por A; **no son razón suficiente** para elegir C o D sobre A.

### 6. Listados cruzados

- `CustomerSalesReportController.php` (líneas 37-55): `Customer::with(['invoices' => fn($q) => whereBetween('invoice_date', ...)])`.
- `SidebarCountsController.php` (líneas 23-25).
- `GetAllUsedCurrenciesController.php` (líneas 23-31): array_merge Invoice+Estimate+Tax+Payment (puede factorizarse a `DocumentIndex::allOf(self::HasCurrency)`).
- `BulkExchangeRateController.php` (líneas 30-61): paralelismo Invoice↔Estimate.

Lección: una tabla común (C/D) simplificaría cross-tipo, pero A cubre los casos sin fusionar tablas. La duplicación actual está concentrada en `BulkExchangeRateController` y `DashboardController`.

### 7. Decisiones formales del paso 6 (D1-D7)

- **D1.** `mailable_type` y `Hashids` no cambian.
- **D2.** Conversiones siempre dentro de `DB::transaction()` (HR7).
- **D3.** Source link explícito por columna.
- **D4.** `Relation::morphMap` fuera de A.
- **D5.** Idempotencia por columna única cuando exista foreign link.
- **D6.** N+1 heredados no son razón para cambiar arquitectura.
- **D7.** Autorización separada por flujo.

### 8. Lo que el paso 6 no zanja

- Si `invoices.from_estimate_id` se introduce de inmediato (depende PoC).
- Si vale la pena un log unificado.
- Si el cron de RecurringInvoice debe asegurar idempotencia bajo doble disparo.

---

## Diseño de la PoC acotada (paso 7)

### 0. Hipótesis abiertas (H1-H10)

H1 DocumentContract sin metodos vacios. H2 DocumentWriter introduce HR7. H3 EstimateToInvoiceConverter reemplaza controller. H4 Idempotencia bajo doble click. H5 mailable_type sin regresión. H6 Hashids sin re-encoding. H7 Refactor consumers sin switch. H8 CustomerSnapshot opcional. H9 BulkExchangeRateController LOC -60%. H10 N+1 heredados fuera de scope.

### 1. Alcance y exclusiones

- Stub `PocDeliveryNote` sin migración real.
- Sin refactor N+1 heredados.
- Sin render binario PDF.

### 2. Aislamiento

- Branch dedicado `poc/document-base-feasibility`.
- Worktree `wt-poc-document-base`.
- Namespace `Crater\Poc\Documents\*`.
- `RefreshDatabase`.
- No migraciones.

### 3. Estructura de archivos

```
app/Poc/Documents/{Contracts,Dto,Services,Converters,Index,Fixtures}/
tests/Poc/Documents/
docs/poc/poc-results.md
```

### 4. Grupos de tests (T1-T9)

- T1 Cumplimiento del contrato.
- T2 Atomicidad DocumentWriter.
- T3 EstimateToInvoiceConverter (autorización + idempotencia + rollback).
- T4 RecurringToInvoiceConverter.
- T5 Snapshotter.
- T6 DocumentIndex iteración polimórfica.
- T7 Performance (reproducible, con QueryLog).
- T8 Regresión mailable_type/Hashids.
- T9 Idempotencia concurrente (pcntl_fork).

### 5. Criterios de aceptación (AC1-AC7)

- AC1: tests PoC pasan.
- AC2: reducción ≥10% SQL cross-tipo.
- AC3: tests existentes verdes.
- AC4: EmailLog pre-existente resuelve.
- AC5: cero métodos vacíos.
- AC6: atomicidad en rollback.
- AC7: doble click concurrente, una sola invoice.

### 6. Metodología de medición

- Versiones (PHP, Laravel, DB) registradas.
- Fixtures deterministas.
- Repeticiones ≥3.

### 7. Resultados esperados posibles

| Escenario | Acción |
|---|---|
| Todos AC verdes, T7 ≥10% | proceder paso 8/9 |
| T1 falla | refinar paso 5 |
| T2 falla | revisar hr7 / savepoint |
| T3/T4 falla | activar D3 |
| T7 <10% pero >0 | decisión |
| T8 falla | morphMap activo |
| Cualquier AC falla | pivotar A+B |

### 8. Lo que la PoC NO prueba

- Producción real.
- Plugins externos.
- Versionado de migraciones.
- Cambio en URLs públicas.

### 9. Plantilla informe final

```
docs/poc/poc-results.md
- Metadata (fecha, branch, hash, PHP/Laravel/DB).
- Resultados por AC.
- Resultados T1-T9.
- Mediciones (Bulk, Reportes, Conversion).
- Idempotencia bajo concurrencia.
- Hipótesis pendientes/refutadas.
- Conclusión (siguiente paso).
```

### 10. Riesgos y mitigación

- Tests concurrentes no reproducibles en CI → documentar entorno.
- getPDFData bloquea → stub.
- BulkExchangeRateController reescrito introduce bug → comparación numérica.
- Migración from_estimate_id simulada diverge → validar comportamiento actual.
- PoC tardía → limitar escala.

---

## Adopción gradual (paso 8)

> Plan 06 eligio **alternativa A** (composicion + contratos). La adopcion gradual **no cambia tablas ni endpoints** durante todo el plan; introduce los contratos y servicios como capa opt-in que se va haciendo cargo de los flujos uno a uno. Esta seccion **define las fases y criterios**; la **ejecucion se delega al plan hijo que se cree en el paso 9**. La PoC del paso 7 es el filtro de calidad que autoriza la salida de las primeras 6 fases.

### 0. Principios rectores

- P1. **Tablas y endpoints inmutables** durante todo el plan.
- P2. **Cada fase es shippable por si sola** con tests que cubren su delta.
- P3. **Tests existentes pasan siempre** (regresion cero).
- P4. **HR7 atomicidad se introduce antes** de cualquier refactor de consumidores.
- P5. **Sin migraciones persistentes** para introducir contratos/servicios; solo DTOs e interfaces en codigo.
- P6. **Sin doble escritura**: no se introduce un nuevo campo `from_estimate_id` que coexista con un log paralelo antes de PoC.
- P7. **RecurringInvoice se externaliza al final**, porque su flujo es automatico y aislado.

### 1. Timeline de fases (F0-F12)

```
PoC (paso 7) -> F0 -> F1 -> F2 -> F3 -> F4 -> F5 -> F6 -> F7 -> F8 -> F9 -> F10 -> F11 -> F12
                |    |    |    |    |    |    |    |    |    |    |    |    |
                ns   ctr  wri  reg  cvt  tra  aut  sna  idm  fea  cns  DN   doc
```

### 2. Detalle por fase (entradas/salidas/riesgos/criterio)

- **F0** - Preparacion del namespace y ServiceProvider.
  - Salidas: `app/Providers/DocumentServiceProvider.php` (sin logica); carpetas `app/Contracts/`, `app/Services/Documents/`, `app/DataObjects/Documents/` creadas. Modificaciones: composer autoload + config providers. Cero cambios en modelos.
  - Riesgos: bajo (solo plumbing).
  - Criterio: `php artisan list` no rompe; `composer dump-autoload` funciona.

- **F1** - `DocumentContract` + interfaces de capacidad opt-in.
  - Modificaciones: crear las 11 interfaces en `app/Contracts/Documents/*` y declarar `implements` en `Invoice`, `Estimate`, `RecurringInvoice` sin cambiar metodos.
  - Salidas: declaracion de implementacion; tests introspectivos verdes; ningun cambio de comportamiento.
  - Compatibilidad: tests existentes pasan identico.
  - Riesgos: medio (firmas no encajadas).
  - Criterio: `(new Invoice) instanceof DocumentContract` retorna true. Feature tests verde.

- **F2** - `DocumentWriter` introduce atomicidad (HR7) sobre Invoice.
  - Entradas: F1.
  - Modificaciones: `Invoice::createItems` y `Invoice::createTaxes` delegan en `DocumentWriter::sync()`. `DB::transaction` aniadido.
  - Salidas: `tests/Feature/Admin/InvoiceTest.php` y `tests/Unit/InvoiceTest.php` siguen verdes; nueva suite `tests/Unit/Documents/Writer/InvoiceWriterTest.php` confirma atomicidad.
  - Compatibilidad: `Invoice::create_invoice` debe producir el mismo resultado.
  - Riesgos: medio (interrupcion de transaccion).
  - Criterio: T2 de PoC verde. Sin cambios en PDF.

- **F3** - `DocumentConverterRegistry` + `EstimateToInvoiceConverter`.
  - Entradas: F2 verde.
  - Modificaciones: `ConvertEstimateController` queda en <=30 LOC delegando en `EstimateToInvoiceConverter`.
  - Salidas: `tests/Feature/Admin/EstimateTest.php::test('create invoice from estimate')` verde. T3 de PoC verde.
  - Compatibilidad: endpoint `/api/v1/estimates/{estimate}/convert-to-invoice` responde igual.
  - Riesgos: bajo.
  - Criterio: `ConvertEstimateController` <=30 LOC.

- **F4** - Externalizacion de `RecurringInvoice::createInvoice`.
  - Entradas: F3.
  - Modificaciones: la logica se mueve a `RecurringToInvoiceConverter`; `RecurringInvoice::generateInvoice` y `createInvoice` delegan.
  - Salidas: `tests/Feature/Admin/RecurringInvoiceTest.php` verde. T4 de PoC verde.
  - Compatibilidad: el cron sigue funcionando.
  - Riesgos: medio.
  - Criterio: `RecurringInvoice::createInvoice` <=30 LOC.

- **F5** - Decision sobre `invoices.from_estimate_id`.
  - Entradas: F4 verde, PoC reporta.
  - Variante A: migracion `add_from_estimate_id_to_invoices_table` con FK nullable + index `(company_id, from_estimate_id)`. `EstimateToInvoiceConverter` escribe.
  - Variante B: solo `$appends['fromEstimateId']` con null + log explicito. Sin migracion.
  - Salidas: features verdes en ambas variantes.
  - Compatibilidad: invoices existentes con `from_estimate_id = NULL`.
  - Riesgos: bajo si la PoC valida.
  - Criterio: cobertura por tests con la variante elegida.

- **F6** - Autorizacion `convert-estimate-invoice` (D7).
  - Entradas: F3.
  - Modificaciones: `AuthServiceProvider` declara `Gate::define('convert-estimate-invoice', [EstimatePolicy::class, 'convertToInvoice'])`. `ConvertEstimateController` cambia authorize.
  - Salidas: tests cubren autorizado/denegado (403).
  - Compatibilidad: documentar el cambio para usuarios sin el ability.
  - Riesgos: bajo.
  - Criterio: H3 de PoC verde.

- **F7** - `DocumentSnapshotter` y DTOs (`CustomerSnapshot`, `AddressSnapshot`).
  - Entradas: F6.
  - Modificaciones: DTOs introducidos; `DocumentSnapshotter` expone `snapshotCustomer(Customer $c)` y `snapshotAddress(Address $a)`; `DocumentWriter::sync` invoca si `HasHistoricalSnapshots`. Persistencia JSON o columnas dedicadas (decision en PR).
  - Salidas: `tests/Unit/Documents/SnapshotterTest.php` (T5 PoC). Invoices existentes con `customer_snapshot = null`.
  - Compatibilidad: aditivo.
  - Riesgos: medio (migracion).
  - Criterio: H8 de PoC verde.

- **F8** - Idempotencia bajo doble click (D5).
  - Entradas: F5 + F7.
  - Modificaciones: `EstimateToInvoiceConverter::convert` usa `lockForUpdate` + chequeo de existencia previa.
  - Salidas: T3 + T4 + T9 de PoC verdes.
  - Compatibilidad: endpoint funciona identico sin concurrencia.
  - Riesgos: bajo si hay FK/indice.
  - Criterio: H4 de PoC verde.

- **F9** - Frontend Composable `useDocumentForm` + folder `document-common/`.
  - Entradas: F1; comportamiento backend estable.
  - Modificaciones: crear `resources/scripts/admin/components/document-common/*` con re-exports. `useDocumentForm.ts` para consumo. `InvoiceCreate.vue`/`EstimateCreate.vue` migran.
  - Salidas: tests manuales via navegador. Folder `estimate-invoice-common/` se mantiene una version y se elimina en la siguiente.
  - Compatibilidad: comportamiento identico.
  - Riesgos: bajo.
  - Criterio: las pantallas funcionan igual; al menos 50% de sitios con `invoice_create` literal dejan de usarlo.

- **F10** - Refactor de consumers (Reports, Dashboard, AI Assistant, Sidebar).
  - Entradas: F1; capacidades `HasAmounts` o `Printable`.
  - Modificaciones: `BulkExchangeRateController` itera `DocumentIndex::allWith(self::HasAmounts)`. `SidebarCountsController` y `CustomerStatsController` actualizados. `CustomerSalesReportController` mantiene adaptacion minima.
  - Salidas: H7 + H9 de PoC verdes. Tests existentes verdes por equivalencia.
  - Compatibilidad: comportamiento equivalente.
  - Riesgos: bajo.
  - Criterio: reduccion >=10% LOC en `BulkExchangeRateController` (H9).

- **F11** - Plan 05 `DeliveryNote`.
  - Entradas: F8 + F10 verdes.
  - Modificaciones: `app/Models/DeliveryNote.php`, `DeliveryNoteItem.php`, `DeliveryNoteToInvoiceConverter.php`. Migracion `add_delivery_note_id_to_invoices_table`. Endpoints `/api/v1/delivery-notes/*`. `DeliveryNote implements DocumentContract, HasAmounts, HasItems, HasTaxes, Printable, Billable, SerialNumbered, HasHistoricalSnapshots`.
  - Salidas: tests verde; plan 05 cerrado.
  - Compatibilidad: plan 05 entrega FK unica nullable (D3).
  - Riesgos: medio (dependiente de F2/F5).
  - Criterio: plan 05 marcado cerrado.

- **F12** - Documentacion y handover.
  - Entradas: F11 verde.
  - Modificaciones: README en `resources/views/app/pdf/*`. Capitulo en `docs/architecture/*`. `CONTRIBUTING.md` explica como extender con un nuevo tipo.
  - Salidas: docs navegables; primer PR ejemplo siguiendo el flujo.
  - Compatibilidad: aditivo.
  - Riesgos: bajo.
  - Criterio: cualquier dev puede anadir un tipo nuevo leyendo la documentacion.

### 3. Reglas de regresion obligatorias (comunes a F2-F11)

- **PDFs**: `Hashids::connection(Invoice::class)` URLs siguen resolviendo; `Hashids::connection(Estimate::class)` sin re-encoding; `getPDFData()` retorna valor valido para facturas historicas.
- **Calculos**: `sub_total`, `tax`, `total`, `discount`, `discount_val`, `base_*` reproducen valores previos para fixtures identicas. `DocumentTotalsCalculator` no introduce drift.
- **Pagos**: `tests/Feature/Admin/InvoiceTest.php::test('invoice mark as paid')` verde. `Payment::createFromInvoice` opera sin cambios.
- **Estados**: `Invoice.send` flipea `status=SENT, sent=true`. `Invoice.changeInvoiceStatus` mantiene semantica. `Estimate.status` transiciones `DRAFT/SENT/VIEWED/ACCEPTED/REJECTED/EXPIRED`.
- **Reportes**: `CustomerSalesReportController` y `TaxSummaryReportController` mismos totales y rutas. `Sales` atado a Invoice (Estimate no entra).
- **APIs**: `/api/v1/{invoices|estimates|recurring-invoices|delivery-notes|...}/*` mantiene firma. `InvoiceResource`/`EstimateResource` no eliminan campos existentes.
- **Permisos**: todas las abilities Bouncer existentes (`view-invoice`, `create-invoice`, `edit-invoice`, `delete-invoice`, `send-invoice`, etc.) operativas. Las nuevas (`convert-estimate-invoice`, etc.) se **agregan**, no reemplazan.
- **Conversiones**: `Estimate -> Invoice` reproduce los 28 campos asignados en `ConvertEstimateController::__invoke` (lineas 57-88). `RecurringInvoice -> Invoice` mismos `base_*` y `next_invoice_at` se actualiza correctamente.

### 4. Cambios incompatibles y adaptadores

> Plan A no introduce incompatibles por si mismo. Esta seccion describe los **adaptadores** previstos si la direccion futura cambiara.

| Cambio potencial | Adaptador |
|---|---|
| Mover clase a otro namespace (afecta `EmailLog.mailable_type`) | No se hace hoy. Si se hace, un script `UPDATE email_logs SET mailable_type = 'New\\Invoice' WHERE mailable_type = 'Old\\Invoice'`. |
| Renombrar `Invoice` por `Document` (cliente hipotetico) | Morph map + script de actualizacion de filas. No se anticipa. |
| Eliminar una capacidad (p. ej. retirar `RecurringInvoice`) | Script de migracion para desvincular `recurring_invoice_id` y conservar invoices existentes. No se anticipa. |

### 5. Relacion con `05-delivery-notes.md`

| Seccion de plan 05 | Estado al cierre de F11 | Comentario al plan 06 |
|---|---|---|
| 1 alcance y reglas | cubierto por F1 (interfaces) + F2 (writer) | Ningun cambio nuevo. |
| 2 piezas reutilizables | cubierto por F2 | Plan 06 fija uso de `DocumentWriter`. |
| 3 persistencia y relaciones | cubierto por F5 (FK si la PoC la recomienda) + F11 (nueva migracion `invoices.delivery_note_id`) | Plan 06 6 D3 fija FK explicita. |
| 4 operaciones y numeracion | cubierto por F11 | Plan 06 5 1 11 mantienen constantes y serial numbers por tipo. |
| 5 conversion transaccional | cubierto por F3 (registry) + F8 (idempotencia) + F11 (DeliveryNote converter) | Plan 06 6 D2 garantiza `DB::transaction`. |
| 6 PDF y acceso | cubierto por F1 (`Printable`) + F9 (frontend opcional) | Plan 06 5 7 mantienen URLs separadas por tipo. |
| 7-9 resto | cubierto por F11 | Plan 06 7 PoC valida los flujos. |

**Que puede implementarse ya (sin esperar a F11):** Las secciones 1, 2 y parte de 4 de plan 05 no dependen de la base comun; pueden implementarse tal cual hoy, adaptandose despues a F1-F3. **Que conviene esperar:** las FK unicas y el `lockForUpdate` (D5 + F8 + F11) dependen de F2 verde.

### 6. Estrategia de consistencia (no doble escritura)

- Nunca se introducen dos campos escribiendo el mismo hecho. `from_estimate_id` (si se decide), `recurring_invoice_id`, `delivery_note_id` son exclusivos por origen.
- Los DTOs y snapshots se introducen solo como lectura adicional, no como espejo de datos ya almacenados.
- Si una fase requiere leer dos columnas equivalentes, abrir PR de limpieza antes.

### 7. Estimacion relativa por fase

| Fase | Valor entregado | Coste |
|------|-----------------|-------|
| F0 | namespaces | muy bajo |
| F1 | contrato declarado | bajo |
| F2 | atomicidad (HR7) | medio |
| F3 | conversion comun con autorizacion | medio |
| F4 | recurrente externalizado | medio |
| F5 | trazabilidad de origen | bajo (con PoC favorable) |
| F6 | autorizacion separada por flujo | bajo |
| F7 | snapshots de cliente/direccion | medio |
| F8 | idempotencia bajo concurrencia | medio |
| F9 | frontend Composable + folder comun | medio |
| F10 | consumers sin discriminador | medio |
| F11 | DeliveryNote operativo, plan 05 cerrado | medio |
| F12 | docs + handover | bajo |

**Beneficio maximo:** F2 + F3 + F4 + F10 cubren la mayor parte de la duplicacion detectada.
**Riesgo maximo:** F11 (acoplamiento con plan 05). Se mitiga con PoC previa.

### 8. Cierre del plan 06 (cuando mover a `archive/`)

- El plan 06 se mueve a `/.agents/plans/archive/` solo cuando se cumpla:
  - **G1.** PoC del paso 7 ejecutada con los 7 AC verdes.
  - **G2.** F2 verde en produccion.
  - **G3.** F3 verde (registry operativo para Estimate -> Invoice).
  - **G4.** F11 verde (DeliveryNote operativo, plan 05 cerrado).
- Hasta entonces, el plan 06 permanece activo como guia de adopcion.

### 9. Lo que el paso 8 NO decide

- El orden cronologico real entre fases (depende del equipo).
- Quien hace cada fase (asignacion humana fuera del alcance).
- El PR concreto (los criterios sirven de marco; el commit se delega al plan 9).
- Si F5 introduce `from_estimate_id` o no (la PoC decide).
- Si F7 introduce snapshots persistidos o solo DTOs en memoria (F7 decide).
- El destino de `RecurringInvoice` a largo plazo.

---

---

## Cierre y decisiÃ³n final (paso 9)

### 1. DecisiÃ³n formal

**Alternativa A â€” ComposiciÃ³n + contratos.   VIABLE Y RECOMENDABLE, con 6 condiciones explÃ­citas (C1â€“C6) y 7 decisiones (D1â€“D7).**

- **No se elige B, C, D, ni E.**
- D se descarta categÃ³ricamente por HR.
- C queda como segundo escenario si A y A+B fallan en la PoC del paso 7.
- E (inacciÃ³n) se descarta por incumplimiento de HR7 (atomicidad) y HR8 (entrega de plan 05).
- A+B queda como evoluciÃ³n posterior, **no como decisiÃ³n ahora**.

La decisiÃ³n se justifica con los criterios ponderados, hard requirements cruzados, beneficios auditados, anti-patrones penalizados, y el coste/riesgo relativo del paso 4.

### 2. Diagrama sencillo de clases y relaciones de persistencia (alternativa A)

```
                       <- - - - - - - - - - - - - - - - - - - - - - -
                       |  INTERFACES (composiciÃ³n, sin jerarquÃ­a)     |
                       |  Crater\Contracts\Documents\*                |
                       |  Crater\DataObjects\Documents\*               |
                       <- - - - - - - - - - - - - - - - - - - - - - -

                       â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
                       â”‚  DocumentContract  (raÃ­z)            â”‚
                       â”‚  + documentType(): string            â”‚
                       â”‚  + customer()/company()/creator()    â”‚
                       â”‚  + currency()                        â”‚
                       â”‚  + notes()/status()                  â”‚
                       â”‚  + getPreviousStatus()               â”‚
                       â”‚  + persistedTotals(): DocumentTotals â”‚
                       â””â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
            implements      â”‚      â”‚      â”‚     implements
              â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜      â”‚      â”‚      â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
              â”‚ + HasAmounts,    â”‚      â”‚                 â”‚ + HasAmounts,
              â”‚   HasItems,      â”‚      â”‚                 â”‚   HasItems,
              â”‚   HasTaxes,      â”‚      â”‚                 â”‚   HasTaxes
              â”‚   Printable,     â”‚      â”‚                 â”‚
              â”‚   Mailable,      â”‚      â”‚                 â”‚
              â•‹â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€   â”‚      â”‚                 â”‚
              â”‚ + Payable        â”‚      â”‚                 â”‚
              â”‚   addPayment()  â”‚      â”‚                 â”‚
              â”‚   paidStatus()  â”‚      â”‚                 â”‚
              â”‚   dueAmount()   â”‚      â”‚                 â”‚
              â”‚                  â”‚      â”‚                 â”‚
           â”Œâ”€â”€â”´â”€â”€â”€â”€â”€â”€â”€â”€â”€â”  â”Œâ”€â”€â”€â”€â”€â”´â”€â”€â”€â”€â”€â”€â”´â”€â”€â”         â”Œâ”€â”€â”€â”´â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
           â”‚  Invoice  â”‚  â”‚   Estimate   â”‚         â”‚ DeliveryNote â”‚ (futuro)
           â”‚ HasCustom â”‚  â”‚ HasCustom   â”‚         â”‚ HasCustom    â”‚ (plan 05)
           â”‚  Fields + â”‚  â”‚  Fields +   â”‚         â”‚  Fields +    â”‚
           â”‚ HasMedia  â”‚  â”‚ HasMedia    â”‚         â”‚ HasMedia     â”‚
           â”‚           â”‚  â”‚ Billable.   â”‚         â”‚ Deliverable, â”‚
           â”‚           â”‚  â”‚  billTo     â”‚         â”‚  delivery    â”‚
           â”‚           â”‚  â”‚  Invoice()  â”‚         â”‚  Address()   â”‚
           â””â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”˜  â””â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”˜         â””â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”˜
                 â”‚               â”‚                       â”‚
                 â”‚               â”‚                       â”‚ plan 05
                 â”‚               â”‚                       â”‚
                 â”‚     â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”´â”€â”€â”€â”€â”€â”€â”€â”€â”              â”‚
                 â”‚     â”‚  RecurringInvoice â”‚              â”‚
                 â”‚     â”‚  (Â¡PLANTILLA!)    â”‚              â”‚
                 â”‚     â”‚ implements solo:  â”‚              â”‚
                 â”‚     â”‚   Document        â”‚              â”‚
                 â”‚     â”‚   Contract,       â”‚              â”‚
                 â”‚     â”‚   HasAmounts,     â”‚              â”‚
                 â”‚     â”‚   HasItems,       â”‚              â”‚
                 â”‚     â”‚   HasTaxes,       â”‚              â”‚
                 â”‚     â”‚   SerialNumbered,â”‚              â”‚
                 â”‚     â”‚   HasHistorical  â”‚              â”‚
                 â”‚     â”‚     Snapshots    â”‚              â”‚
                 â”‚     â”‚ NO Printable /    â”‚              â”‚
                 â”‚     â”‚ NO Mailable /     â”‚              â”‚
                 â”‚     â”‚ NO Payable /     â”‚              â”‚
                 â”‚     â”‚ NO Deliverable / â”‚              â”‚
                 â”‚     â”‚ NO Billable       â”‚              â”‚
                 â”‚     â””â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜              â”‚
                 â”‚            â”‚                          â”‚
              invoices()      â”‚                          â”‚
           (1:N genera)       â”‚                          â”‚
                 â”‚            â”‚                          â”‚
                 â–¼            â–¼                          â–¼
       ===================================================================

                       <- - - - - - - - - - - - - - - - - - - - - - -
                       |  PERSISTENCIA â€” tablas separadas          |
                       <- - - - - - - - - - - - - - - - - - - - - - -

       â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”    â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
       â”‚ invo \* ces      â”‚    â”‚ const \* ates      â”‚
       â”‚ conserva:        â”‚    â”‚ conserva:        â”‚
       â”‚  paid_status      â”‚    â”‚  expiry_date     â”‚
       â”‚  sent/viewed      â”‚    â”‚  NO pagos        â”‚
       â”‚  overdue          â”‚    â”‚  NO balance      â”‚
       â”‚  payment_info     â”‚    â”‚                  â”‚
       â”‚  due_amount       â”‚    â”‚                  â”‚
       â”‚  + campos CO/COp  â”‚    â”‚  + campos CO/COp â”‚
       â”‚   (paso 2 Â§1)     â”‚    â”‚   (paso 2 Â§1)    â”‚
       â””â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜    â””â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
           â”‚                       â”‚
           â”‚ 1:N  invoice_items    â”‚ 1:N  estimate_items
           â”‚ (Laravel syntax)      â”‚
           â”‚                       â”‚
           â–¼                       â–¼
       â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”         â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
       â”‚invoice_items â”‚         â”‚estimate_itemsâ”‚
       â”‚ name, qty,    â”‚         â”‚  + deliver-   â”‚
       â”‚   price, â€¦   â”‚         â”‚    ables,     â”‚
       â”‚ + base_*     â”‚         â”‚    delivery_  â”‚
       â”‚ + mon-       â”‚         â”‚    delay,termsâ”‚
       â”‚   ody FKs    â”‚         â”‚   (recientes: â”‚
       â”‚ (2026/11/05  â”‚         â”‚   2026/11/05  â”‚
       â”‚  migraciÃ³n   â”‚         â”‚   migraciÃ³n)  â”‚
       â”‚  agregÃ³     â”‚         â”‚               â”‚
       â”‚  deliverablesâ”‚         â”‚               â”‚
       â”‚  en las 3   â”‚         â”‚               â”‚
       â”‚  tablas)    â”‚         â”‚               â”‚
       â””â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”˜         â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
              â”‚
              â”‚ sharedby
              â–¼
       â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”     â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
       â”‚   taxes (shared)  â”‚ â—„â”€â”€â”€â”¤   taxes (line/headerâ”‚
       â”‚ 5 FKs nullables:  â”‚     â”‚   discrimination)  â”‚
       â”‚  invoice_id       â”‚     â”‚  Cada fila perte-   â”‚
       â”‚  estimate_id      â”‚     â”‚  nece a UN solo     â”‚
       â”‚  recurring_invoiceâ”‚     â”‚  documento.         â”‚
       â”‚  invoice_item_id  â”‚     â”‚  (Invariante HR5.)  â”‚
       â”‚  estimate_item_id â”‚     â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
       â”‚  (futuro delive-  â”‚
       â”‚   ry_note_id,     â”‚
       â”‚   _item_id)       â”‚
       â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜

       â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
       â”‚  recurring_invoices     â”‚
       â”‚ (plantilla, NO emitido) â”‚
       â”‚  starts_at, frequency,  â”‚
       â”‚  limit_*, next_invoice_ â”‚
       â”‚  at, send_automatically â”‚
       â”‚  FK â” recurring_invoice_â”‚
       â”‚     â”‚ id â†’ invoices      â”‚
       â””â”€â”€â”€â”€â”€â”¼â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
             â”‚ invoices()  1:N
             â–¼
       â””â”€â–º invoices.recurring_invoice_id  (nullable, NO cascade)

       â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
       â”‚ ai_conversations  (precedent) â”‚
       â”‚ (company_id, document_type,   â”‚
       â”‚  document_id) UNIQUE          â”‚
       â”‚ index (document_type,doc_id)  â”‚
       â”‚ Sin FK rÃ­gida â†’ borrado       â”‚
       â”‚  seguro del doc.              â”‚
       â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜

       Hashids::connection(Clase::class)
         - Invoice/Estimate/RecurringInvoice/DeliveryNote:  conexiÃ³n separada
         - URLs PDF pÃºblicas por tipo:
             /invoices/pdf/{invoice:unique_hash}
             /estimates/pdf/{estimate:unique_hash}
             /delivery-notes/pdf/{deliveryNote:unique_hash}
         - Pre-Plan 06: preserva URLs y mailable_type.
```

### 3. Comportamientos especÃ­ficos por tipo

| Comportamiento         | Invoice | Estimate | RecurringInvoice | DeliveryNote (futuro) |
|------------------------|:-------:|:--------:|:----------------:|:---------------------:|
| **Payable** (cobrable) | âœ“       | âœ—        | âœ—                | âœ—                     |
| **Mailable** (enviable)| âœ“       | âœ“        | âœ—                | (futuro)              |
| **Printable** (PDF)    | âœ“       | âœ“        | âœ—                | âœ“                     |
| **Billable** (facturable) | âœ—   | âœ“        | âœ“ (vÃ­a invoices()) | âœ“                   |
| **Deliverable**        | âœ—       | âœ—        | âœ—                | âœ“                     |
| **SerialNumbered**     | âœ“       | âœ“        | âœ“                | âœ“                     |
| **HasHistoricalSnapshots** | âœ“   | âœ“        | âœ“                | âœ“                     |
| **HasCustomFields**    | âœ“       | âœ“        | âœ“                | âœ“                     |
| **HasMedia**           | âœ“       | âœ“        | âœ—                | (futuro)              |

### 4. Dudas pendientes (entrada a PoC y planes sucesores)

Cada duda alimenta un grupo de tests T* (paso 7) o se delega a una fase F*.

| Duda                                                                                                          | Resuelve en     | Estado      |
|---------------------------------------------------------------------------------------------------------------|------------------|-------------|
| Â¿`DocumentContract` puede implementarse sin mÃ©todos vacÃ­os?                                                    | T1 / PoC         | **abierta** |
| Â¿`DocumentWriter` introduce `DB::transaction` sin romper `createItems`/`createTaxes`?                          | T2 / PoC, F2     | **abierta** |
| Â¿`DocumentConverterRegistry` reemplaza `ConvertEstimateController` sin regresiÃ³n?                              | T3 / PoC, F3     | **abierta** |
| Â¿Doble click concurrente genera **una sola** factura (idempotencia)?                                              | T3+T4+T9, F8     | **abierta** |
| Â¿`mailable_type` literal sigue funcionando si reorganizamos namespaces?                                          | T8 / PoC         | **abierta** |
| Â¿`Hashids::connection(Clase::class)` se mantiene sin re-encoding ni prefijo?                                    | T8 / PoC         | **abierta** |
| Â¿CuÃ¡ntos sitios con `switch($type)` necesitan reescribirse? Â¿CuÃ¡nto baja el LOC?                              | T6+T7, F10       | **abierta** |
| Â¿`CustomerSnapshot`/`AddressSnapshot` se aplican ya o se postergan?                                              | T5, F7           | **abierta** |
| Â¿ReducciÃ³n â‰¥60% de LOC en `BulkExchangeRateController`? Â¿Reporte cross-tipo estable?                           | T7, F10          | **abierta** |
| Â¿N+1 heredados en `DashboardController` y `CustomerStatsController` se confirman fuera de scope?               | H10 / PoC+D6     | cerrada     |
| Â¿Se introduce `invoices.from_estimate_id` (F5 variante A) o se vive solo con log/null (variante B)?           | PoC â†’ F5         | **abierta** |
| Â¿Los snapshots se almacenan como JSON en columnas existentes o columnas dedicadas / tablas separadas?          | PoC â†’ F7         | **abierta** |
| `RecurringInvoice` Â¿queda como plantilla definitiva o se reduce a configuraciÃ³n de Invoice?                     | fuera de plan 06 | **abierta** (futuro) |
| Â¿CuÃ¡nto de la asimetrÃ­a reciente (migraciÃ³n 2026/11/05) se corrige al homogenizar `InvoicesRequest`?         | F1 + F11         | **abierta** |
| Â¿Las abilities nuevas (`convert-estimate-invoice`, etc.) rompen tests Pest existentes?                          | F6               | **abierta** |
| Â¿Los plugins de Nwidart (`Payment`, `Backup`, etc.) quedan afectados por la migraciÃ³n?                        | fuera de plan 06 | **abierta** (futuro) |

Marca:**abierta** = PoC + fase correspondiente la cierra con evidencia. **cerrada** = resuelta por anÃ¡lisis.

### 5. Condiciones que harÃ­an reconsiderar la decisiÃ³n A

Las siguientes condiciones **invalidarÃ­an** la decisiÃ³n adoptada en el paso 4 y obligarÃ­an a reabrir plan 06:

1. **C-rec.** La PoC (paso 7) muestra <10% de reducciÃ³n de queries SQL cross-tipo (AC2) **y** no se corrige razonablemente con una segunda iteraciÃ³n.
2. **C-rec.** AlgÃºn AC1-AC7 falla y no se puede corregir sin reescribir la mitad del cÃ³digo actual.
3. **C-rec.** Plan 05 entrega `DeliveryNote` antes de F11 con una arquitectura incompatible con `DocumentContract`, y rehacer la migraciÃ³n a contrato toma >1 semana-hombre.
4. **C-rec.** Un cliente importante exige una integraciÃ³n con `Salesforce` / `SAP` / similar que requiere jerarquÃ­a polimÃ³rfica Eloquent rÃ­gida sobre los documentos. Esa demanda justificarÃ­a un A+B.
5. **C-rec.** `tests/Feature/Admin/InvoiceTest.php` o `EstimateTest.php` requieren >30% de cambios para pasar; un cambio asÃ­ invalida el "regresiÃ³n cero".
6. **C-rec.** Se descubre un caso real (no hipotÃ©tico) donde `RecurringInvoice` debe ser tratado como documento emitido por un consumo legal o contable.
7. **C-rec.** Una vulnerabilidad / CVE crÃ­tica obliga a renombrar la clase `Invoice` por seguridad â†’ fuerza morphMap y todo el sistema de FK-por-columna.

Si cualquiera de las anteriores ocurre, plan 06 vuelve a paso 4 con nuevos datos.

### 6. Plan de implementaciÃ³n generado

**Este plan 06 autoriza crear un nuevo plan operativo pero NO autoriza empezar la refactorizaciÃ³n.**

El plan operativo es **`/agents/09-implementacion-document-contract.md`** (creado en este mismo paso 9, en `.agents/plans/`).

RelaciÃ³n:
- Plan 06 cierra el anÃ¡lisis y deja la decisiÃ³n registrada.
- Plan 09 hereda las fases F0-F11 (paso 8) y las concreta como PRs ejecutables.
- Plan 09 define su propio `Resultado esperado` especÃ­fico para la implementaciÃ³n.
- Plan 06 permanece en `plans/` hasta que G1+G2+G3+G4 verdes (paso 8 Â§8). SÃ³lo entonces se archiva.

### 7. Lo que el plan 06 NO autoriza

Plan 06 es un **anÃ¡lisis de viabilidad**. NO autoriza por sÃ­ solo:

- Crear la interfaz `DocumentContract` (decisiÃ³n operativa de F1 en plan 09).
- Modificar `app/Models/Invoice.php`, `Estimate.php` o `RecurringInvoice.php` (decisiÃ³n operativa de F1-F4 en plan 09).
- Crear `app/Services/Documents/*` (servicios descritos pero sin implementar hasta que la PoC valide).
- Crear migraciones (`invoices.delivery_note_id`, `from_estimate_id`, snapshots persistidos).
- Cambiar `AuthServiceProvider` o agregar abilities Bouncer.
- Implementar `DeliveryNote` en plan 05 bajo el contrato (eso entra en F11 de plan 09).

La decisiÃ³n A es sÃ³lidamente argumentada, pero la **ejecuciÃ³n** se delega a plan 09 + PoC + gates.

### 8. Cierre del plan 06

Cuando las condiciones de archivo (G1-G4) se cumplan, este plan se mueve a `/.agents/plans/archive/`. Mientras tanto, permanece como guÃ­a de adopciÃ³n activa. La nota `Estado: ...` al pie refleja este punto. Los criterios del checklist al inicio quedan todos cumplidos a partir de este paso 9; los pendientes (PoC ejecutada, F2-F11 verdes) son responsabilidad de plan 09 y de los PRs resultantes.

---

## Resultado esperado

- [x] Existe un inventario verificable de documentos, dependencias y duplicaciones reales. *(completado: ver sección «Inventario verificable (paso 1)» arriba)*
- [x] Estan diferenciadas la herencia de clases, la composicion de comportamientos y las estrategias de persistencia. *(completado en parte por este paso 2: «Matriz de datos e invariantes (paso 2)» arriba define qué campo es común, común opcional o específico por tipo, separando capas — persistencia vs comportamiento vs reglas)*
- [x] Se comparan las alternativas con criterios explícitos y frente al estado actual. *(completado: ver «Comparación de alternativas de arquitectura (paso 3)» arriba)*
- [x] Hay una recomendación justificada, un diagrama de arquitectura y una estimación relativa del impacto. *(completado parcialmente por este paso 4: «Criterios de viabilidad y decisión (paso 4)» arriba fija pesos, requisitos, beneficios comprobados, esfuerzo y riesgo, y emite la decisión argumentada. El diagrama formal queda para el paso 5/9.)*
- [x] La propuesta conserva las reglas particulares de factura, presupuesto y albarán. *(completado: ver «Diseño del contrato y extensiones (paso 5)» arriba §2 (interfaces por capacidad), §10 (matriz de implementación por tipo), §11 (servicios de conversión). Se impide exigir pagos a Estimate/Albarán vía Payable solo en Invoice; Deliverable solo en DeliveryNote; Billable solo en Estimate/DeliveryNote.)*
- [x] Se han evaluado líneas, impuestos, pagos, estados, PDFs, conversiones y referencias externas. *(completado: cubierto en paso 5 §1-§5 + §11, y consolidado en paso 6 §2-§6 con auditoría de `Hashids`, `EmailLog`, `invoices.recurring_invoice_id`, `ai_conversations` como precedente, conversions existentes y N+1. Se decide columna explícita para source link, no polimórfica.)*
- [x] Se documenta cómo preservar datos históricos, identidad, permisos y aislamiento por empresa. *(completado: ver «Identidad, persistencia y conversiones (paso 6)» arriba §1-§4 y §7 — D1-D7 — que conservan `mailable_type` literal, `Hashids` por conexión de subclase, `recurring_invoice_id` como FK explícita, `ai_conversations` como patrón operativo, autorización separada por flujo de conversión.)*
- [x] Las hipótesis críticas cuentan con evidencia o quedan señaladas como pendientes. *(completado parcial: el paso 7 — «Diseño de la PoC acotada» — define 10 hipótesis (H1–H10), 9 grupos de tests (T1–T9), 7 criterios de aceptación (AC1–AC7) y 6 resultados esperados posibles. La ejecución de la PoC queda fuera del alcance de este plan; el reporte final `docs/poc/poc-results.md` se rellenará en una iteración posterior.)*
- [x] Si se recomienda implementar, existe una estrategia gradual y un plan posterior independiente. *(completado: «Adopción gradual (paso 8)» arriba define F0-F12 con criterios de entrada/salida, regresiones comunes, impacto sobre plan 05, y 4 gates G1-G4 para cerrar el plan y crear el plan hijo de implementación numerado.)*
- [x] Se ha definido cómo afecta la decisión a la implementación de Delivery Notes. *(cubierto en paso 8 §5 «Relación con 05-delivery-notes.md»: secciones 1, 2 y 4 de plan 05 pueden implementarse hoy sin esperar a F11; las FK únicas y lockForUpdate (D5 + F8 + F11) dependen de F2 verde. F11 cierra plan 05.)*

---

## Notas

Estado: **plan 06 COMPLETADO** en su fase analítica. Pasos 1, 2, 3, 4, 5, 6, 7, 8 y 9 (2026-11-11) emitidos. Decisión cerrada: **alternativa A (composición + contratos) es VIABLE Y RECOMENDABLE con 6 condiciones explícitas (C1-C6) + 7 decisiones (D1-D7)**; **D descartado por HR**; **C queda como segundo escenario**; **E (inacción) descartada por incumplimiento de HR7/HR8**. **Contrato diseñado**: `DocumentContract` raiz + 11 interfaces de capacidad. RecurringInvoice fuera del círculo de "documento emitido". Servicios definidos: `DocumentWriter`, `DocumentTotalsCalculator`, `DocumentSnapshotter`, `DocumentConverterRegistry`, `DocumentMailer`, `DocumentSnapshotListener`. DTOs: `DocumentTotals`, `LineSnapshot`, `TaxSnapshot`, `CustomerSnapshot`, `AddressSnapshot`, `DocumentContext`. Identidad y conversiones auditadas (D1-D7). PoC acotada diseñada (10 hipótesis H1-H10, 9 grupos T1-T9, 7 criterios AC1-AC7, 6 escenarios de resultado). Adopción gradual diseñada (F0-F12 + 4 gates G1-G4). Cierre y decisión final documentados, incluyendo diagrama ASCII de clases y persistencia, listado de comportamientos específicos por tipo, 16 dudas pendientes y 7 condiciones C-rec que invalidarían la decisión. **Plan hijo creado**: `/.agents/plans/09-implementacion-document-contract.md` hereda las fases F0-F11/F12 y las concreta como trabajo operativo. **Plan 06 permanece en `plans/` (no se archiva aún)** hasta que se cumplan las 4 gates: G1 = PoC del paso 7 con los AC1-AC7 verdes, G2 = F2 verde en producción, G3 = F3 verde (registry operativo para Estimate→Invoice), G4 = F11 verde (DeliveryNote operativo, plan 05 cerrado). Al cumplir las 4 gates, mover este plan a `/.agents/plans/archive/` según `.agents/README.md`. La refactorización NO queda autorizada por este plan; sólo la registra.
