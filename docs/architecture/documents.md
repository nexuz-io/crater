# Document contract architecture

Plan 09 introduced a unified contract for the documents the system
emits: invoice, estimate, recurring-invoice, and (new) delivery-note.
This document describes the architecture, capabilities, and the
operational guarantees we provide.

> Reference: `/.agents/plans/09-implementacion-document-contract.md`.
> Plan 06 backdrop: `/.agents/plans/archive/06-document-base-feasibility.md`
> (PoC validation reports live at
> `https://github.com/nexuz-io/crater/branches/origin/poc/document-base-feasibility`).

## Goals of the contract

The previous codebase had three thoroughly-similar models
(Invoice, Estimate, RecurringInvoice) with no shared abstraction.
Adding a fourth document (DeliveryNote) threatened to triple the
boilerplate, and the operator-visible duplication was visible in
every consumer — controllers, jobs, mailers, sidebars, bulk
exchange-rate operators, dashboards, etc.

Plan 06 reviewed three architectural alternatives (Plan A:
composition + contracts, Plan B: abstract class, Plan C/D: shared
DB tables) and selected Plan A. Plan A is now in production.

The contract:

1. **Segregated capabilities**, opt-in via interfaces. A model
   declares `implements HasAmounts` only if it has amounts. No
   empty methods; no `switch`/`match` by discriminator in consumers.
2. **No shared DB table.** Each document keeps its own table and
   schema. Tables are joined conceptually via the contract, not
   physically.
3. **Idempotent operations.** Conversions and atomic writes must
   not double-create on retry. Enforced via Postgres-friendly
   additive semantics (lock + unique row + in-transaction check).
4. **Historical accuracy.** Customer / address data delivered to
   the rendered output reflects the moment the document was
   emitted unless and until the document is migrated to a
   persisted snapshot column.

## The contract hierarchy

### `DocumentContract` (root)

`Crater\Contracts\Documents\DocumentContract` declares 10 methods.
The PoC verified that no method has a body (AC5 green). Each method
is either a question ("what is the latest status?") or a building
block surfaced for the converter and consumer layer.

| Method | Purpose |
|---|---|
| `documentType(): string` | Logical type identifier ("invoice", "estimate", …). |
| `customer(): BelongsTo` | FK to `Customer`. |
| `company(): BelongsTo` | FK to `Company`. |
| `creator(): BelongsTo` | FK to the User that authored the document. |
| `currency(): BelongsTo` | FK to `Currency`. |
| `notes(): ?string` | Free-form notes from the request body. |
| `status(): string` | Commercial status (DRAFT / SENT / DRAFT / etc.). |
| `getPreviousStatus(): string` | Status before the current mutation. |
| `getFormattedNotes(): ?string` | Sanitised, company-localised representation. |
| `persistedTotals(): DocumentTotals` | Snapshot of the persisted totals. Disjoint from `recompute()` because it must not mutate state. |

### The 10 segregated capabilities

Each capability is an interface that a model can declare
independently. The contract is opt-in: a document can implement
only the capabilities it actually has.

| Capability | Required for | Methods (high-level) |
|---|---|---|
| `HasAmounts` | documents with monetary totals | `totals()`, `recompute()` |
| `HasItems` | documents with line items | `items()`, `syncItems()` |
| `HasTaxes` | documents with header taxes | `taxes()`, `syncTaxes()` |
| `Printable` | documents that emit PDF | `pdfView()`, `getPDFData()`, `getGeneratedPDF()` |
| `Mailable` | documents sent by email | `send()`, `preview()` |
| `Payable` | documents receiving payments | `addPayment()`, `subtractPayment()`, `paidStatus()`, `dueAmount()` |
| `Deliverable` | documents recording physical delivery | `deliveryStatus()`, `markAsDelivered()`, `cancel()`, `deliveryAddress()` |
| `Billable` | documents convertible to Invoice | `billToInvoice()`, `alreadyBilled()` |
| `SerialNumbered` | documents with server-issued sequence | `nextNumber()`, `getSerialContext()` |
| `HasHistoricalSnapshots` | documents embedding customer/address snapshots | `customerSnapshot()`, `billingAddress()`, `shippingAddress()` |

The matrix of "which model implements what" is derived from plan 06
§paso-2 §3 and matches the codebase as of:

| Capability | Invoice | Estimate | RecurringInvoice | DeliveryNote |
|---|---|---|---|---|
| `DocumentContract` | — | — | — | ✅ |
| `HasAmounts` | — | — | — | ✅ |
| `HasItems` | — | — | — | ✅ |
| `HasTaxes` | — | — | — | ✅ |
| `Printable` | — | — | — | ✅ |
| `Mailable` | — | — | — | — |
| `Payable` | — | — | — | — |
| `Deliverable` | — | — | — | (semantic root) |
| `Billable` | — | (handled by controller) | (handled by controller) | ✅ |
| `SerialNumbered` | — | — | — | ✅ |
| `HasHistoricalSnapshots` | — | — | — | ✅ |

The empty cells mean "not implemented in production yet" — the model
exists, has the field, and the relationship exists; only the
`implements X` declaration is pending. They are progressively adopted
in subsequent PRs of plan 09. The `ContractComplianceTest` is the
regression lock that prevents premature adoption.

> Strategy "A" of plan 09: we do not force the existing models to
> declare `implements` on day one. Each capability is adopted
> gradually to avoid accidentally breaking the existing methods.
> The `ContractComplianceTest` lists the current "yields" so the
> reviewer knows what isn't yet migrated.

## Services

| Service | Responsibility |
|---|---|
| `DocumentTotalsCalculator` | Total computation for the `DocumentTotals` DTO. Pure. |
| `DocumentWriter` | Atomic creation / update of a document, items and taxes in a single `DB::transaction` (HR7). |
| `DocumentConverterRegistry` | Indexes `(sourceType, targetType)` converters. |
| `DocumentSnapshotter` | Builds customer/address snapshots from live models. |
| `DocumentIndex` | Polimorphic iteration across Invoice + Estimate + RecurringInvoice + DeliveryNote (when available), without discriminator. |

### `DocumentConverterRegistry` — how consumers resolve a converter

`DocumentConverterRegistry::get('estimate', 'invoice')` returns the
converter for that pair. Controllers don't need to know the
concrete implementation class; they only know the source and target
logical types.

Adding a new converter is `register(new MyConverter())` in
`DocumentServiceProvider`. The PoC validated the index pattern with
`DocumentConverterRegistryTest.php`.

## Idempotency

`DocumentWriter::createInvoice`, `DocumentWriter::updateInvoice`,
and the converter wrappers (`EstimateToInvoiceConverter`,
`RecurringToInvoiceConverter`, `DeliveryNoteToInvoiceConverter`) all
share the same idempotency policy:

1. `DB::transaction` opens.
2. `lockForUpdate()` on the source row.
3. Check if the destination row already exists — if yes, return it
   unchanged.
4. Otherwise, build and persist.
5. Commit.

For Estimate → Invoice, the destination row check is
`Invoice::where('from_estimate_id', $estimate->id)`. The FK is
nullable on `invoices` (`add_from_estimate_id_to_invoices_table` in
plan 09 §F5).

For RecurringInvoice → Invoice, the check is `Invoice::where(
'recurring_invoice_id', $recurring->id)->whereDate('invoice_date',
today())`. Same-day conflates into a single Invoice.

For DeliveryNote → Invoice, the check is
`Invoice::where('delivery_note_id', $deliveryNote->id)`. The FK is
**UNIQUE nullable** on `invoices.delivery_note_id` (`add_delivery_note_id_to_invoices_table`
in plan 09 §F11). The unique constraint provides a database-level
guarantee on top of the in-PHP check.

## Historical snapshots

Three DTOs:

| DTO | Origin |
|---|---|
| `CustomerSnapshot` (`app/DataObjects/Documents/`) | 9 readonly fields + `capturedAt`. |
| `AddressSnapshot` | 9 readonly fields + a `type` enum. |
| `DocumentTotals` | 12 readonly fields (mirrors the model's total columns). |
| `DocumentContext` | Converter orchestration metadata (origin, target type, overrides). |
| `LineSnapshot`, `TaxSnapshot` | Used by converters for in-memory payloads. |

`DocumentSnapshotter::snapshotCustomer()` and
`DocumentSnapshotter::snapshotAddress()` produce immutable DTOs.
The DTOs are distinct from the underlying models so a downstream
mutation to the live Customer / Address cannot retroactively edit
what a delivered Invoice rendered.

Currently the snapshots are derived on each read
(`Invoice::getCustomerSnapshotAttribute()` etc.). Persistence —
either as JSON columns on the documents or as separate tables — is
deferred. The accessors return `null` when the customer / address
doesn't exist (foreign customer_id) so legacy rows remain safe.

## What the contract does NOT provide

- The contract does not include User-side feeds (populate default
  values, AI-suggested notes, etc.). Those are out of scope.
- The contract does not replace `RecurringInvoice::generateInvoice`
  for generating an Invoice *from scratch* (cron-driven). The
  generator still uses the model methods directly. Adopting
  `DocumentWriter` there is in scope of plan 10 items.

## Migration policy between Eloquent models and the contract

Three waves have been observed at write-time:

1. **Adding a method** to a method-table: just add it. The interface
   may force it but the model is free to implement it however it
   wants.
2. **Adopting `implements X`**: must keep the existing implementation
   intact. Run `ContractComplianceTest`, then update the assertion.
   This is the "go-live" sign for that capability.
3. **Removing legacy slow path**: only after a capability has been
   adopted in production for at least one release.

The PoC tests are non-modifying at the migration boundary.

## Where to find related artefacts

- Plan 09: `/.agents/plans/09-implementacion-document-contract.md`.
- Plan 06 (feasibility / decision, archived at PR-time):
  `/.agents/plans/archive/06-document-base-feasibility.md`.
- PoC source: `https://github.com/nexuz-io/crater/branches/origin/poc/document-base-feasibility`.
  Browse the `app/Poc/Documents/…` directory.
- Sources of truth in production tree:
  - `app/Contracts/Documents/`
  - `app/Services/Documents/`
  - `app/DataObjects/Documents/`
  - `app/Services/Documents/Index/DocumentIndex.php`
  - `app/Models/DeliveryNote.php` — the canonical example model that
    implements the contract in full.
