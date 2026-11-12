# Adding a new document type

This runbook describes the eight steps required to add a new
`Document` type to the system, following the contract introduced by
plan 09. The companion "CreditNote" document — used to undo a previously
emitted invoice — is used as the running example because it does not
yet exist in production and it exercises every layer of the contract.

> Before you start: read `docs/architecture/documents.md` to
> understand the contract shape and the matrix of capabilities
> defined in plan 06 §paso-2 §3.

## Step 0 — Decision

Before writing code, answer three questions:

1. **What is the logical document type?** A short string. Used as
   the `documentType()` value and as the source/target key in the
   `DocumentConverterRegistry`. Pick a stable identifier; you'll
   be returning it from `CreditNote::documentType()`. Examples:
   `"credit-note"`, `"receipt"`, `"sales-order"`.

2. **What is the matrix of capabilities?** Which capabilities
   (HasAmounts, HasItems, HasTaxes, Printable, Mailable, Payable,
   Deliverable, Billable, SerialNumbered, HasHistoricalSnapshots)
   apply to your document? The matrix guides every interface you
   `implements` on step 3.

3. **Should we add database tables or reuse existing ones?**
   Plan 06 §paso-3 decided to never share the document table. So
   plan 09 always goes for new tables. For "CreditNote", that means
   a new `credit_notes` table with the same shape as `invoices`.

If the answer is "the new document doesn't need any of the existing
capabilities" (uncommon), you can choose `Plan B` and reuse the
`BaseDocument` abstract class. Today, every real document fills at
least `HasAmounts`, `HasItems` and `SerialNumbered`, so practically
this runbook focuses on adopting Plan A.

## Step 1 — Migration

Create a new migration in `database/migrations/` (timestamped
`YYYY_MM_DD_HHMMSS_create_credit_notes_table.php`).

Schema conventions: align the table with `invoices` where shared.
Add an `X_index` per company_id + each filterable column.
For CreditNote, schema differs in:

- `parent_invoice_id` (nullable, FK to `invoices`) — unlike
  Estimate/Recurring/DeliveryNote which all share `parent_X_id`,
  the "undo" document has its own inverse link to Invoice.

The down() must be a safe inverse. Plan 09 migrations all have
`dropForeign` + `dropIndex` + `dropColumn` in the right order.

## Step 2 — Model and line model

Create `app/Models/CreditNote.php` mirroring `DeliveryNote.php`:

```php
class CreditNote extends Model implements
    DocumentContract, HasAmounts, HasItems, HasTaxes, Printable,
    SerialNumbered, HasHistoricalSnapshots
{
    // STATUS_DRAFT, STATUS_ISSUED, STATUS_VOID constant triples
    // implement the same shape as Estimate/RecurringInvoice's
    // STATUS_DRAFT pattern
}
```

> **Adopting `implements X` is a one-way door**: each interface
> forces you to implement all its methods. Audit the matrix
> before doing this for an existing producer.

The model needs:

- `company()`, `customer()`, `creator()`, `currency()` relations
  following the same pattern as `DeliveryNote`.
- `documentType(): string` returning `"credit-note"`.

Add the accessors from plan 09 §F7:

- `getCustomerSnapshotAttribute()`, `getBillingAddressAttribute()`,
  `getShippingAddressAttribute()` via `DocumentSnapshotter`.

## Step 3 — Items and taxes tables

If your document has items, create `credit_note_items` (one
migration). If it has a `taxes` header association, the taxes table
is shared — add `credit_note_id` (FK, nullable, `onDelete('set
null')`) via a small migration that mirrors the `taxes`
`delivery_note_id` migration from plan 09 §F11.

Items follow `invoice_items` schema:

```php
Schema::create('credit_note_items', function (Blueprint $table) {
    // 1:1 with invoice_items schema, but $table->unsignedInteger('credit_note_id')
    // instead of invoice_id, and `credit_note_id` externally.
});
```

## Step 4 — Writer (atomic creation / update)

Plan 09 §F2 already lives in `DocumentWriter`. New document types
do NOT need a new writer; the existing one accepts the document
model and the items / taxes payloads. You only need to expose
new public helpers on the model:

```php
class CreditNote {
    public function createNotes($request, $items, $taxes): self
    public static function writeFromRequest($request): self
}
```

Follow the pattern of `RecurringInvoice::createInvoice()` from
plan 09 §F4: each new document type keeps its own dedicated entry
point that delegates to `DocumentWriter::createInvoice` /
`DocumentWriter::updateInvoice` for atomic-write performance.

## Step 5 — Converter(s)

If the new document participates in `Billable` (it can be converted
to an Invoice), create
`app/Services/Documents/Converters/CreditNoteToInvoiceConverter.php`.

The converter's `convert()` MUST:

1. Open `DB::transaction`.
2. `lockForUpdate()` on the source credit note.
3. Check `Invoice::where('from_credit_note_id', $note->id)` —
   it must already exist as the destination. If it does, return.
4. Build the Invoice (header + items + taxes).
5. Commit.

Register the converter in `DocumentServiceProvider::register()`:

```php
$registry->register(new CreditNoteToInvoiceConverter(
    $app['db.connection']
));
```

## Step 6 — Ability, policy, request, resource

In `config/abilities.php`, add a new ability section for each
operation you want to expose (typically: `view-credit-note`,
`create-credit-note`, `edit-credit-note`, `delete-credit-note`,
`convert-credit-note-invoice`).

Add `Crater\Policies\CreditNotePolicy.php`. Mirror the shape of
`DeliveryNotePolicy`. Each method checks the Bouncer ability and
the multi-tenant company scope:

```php
public function view(User $user, CreditNote $note): bool
{
    return BouncerFacade::can('view-credit-note', $note)
        && $user->hasCompany($note->company_id);
}
```

`compile abilities can be performed in a single `php artisan
bouncer:cache` run; the administrative role seed (`Company::setupRoles`)
auto-imports new abilities from `config/abilities.php`.

Create:

- `app/Http/Requests/CreditNoteRequest.php` (validation rules,
  case-insensitive uniqueness via `Rule::unique` if applicable).
- `app/Http/Resources/CreditNoteResource.php`.

## Step 7 — Controller(s) and routes

`app/Http/Controllers/V1/Admin/CreditNote/CreditNotesController.php`
is a CRUD over the resource. Convert endpoint goes to its own
`__invoke` controller (one route per complex action):

- `Crater\Http\Controllers\V1\Admin\CreditNote\ConvertCreditNoteController`

Routes in `routes/api.php`:

```php
Route::post('/credit-notes/{credit_note}/convert-to-invoice',
    ConvertCreditNoteController::class);

Route::apiResource('credit-notes', CreditNotesController::class);
```

The `:credit_note` route-model binding is provided by Laravel out of
the box; you just need a `CreditNotePolicy::view` so unauthorised
returns 403 instead of 404.

## Step 8 — Tests

The checklist for the document's tests (CI gate):

1. **`DocumentIndex` regression** — `tests/Unit/Documents/Index/…`:
   the new document must be iterated under the multi-tenant scope.
2. **Converter idempotency** — sequential test calling `convert()`
   twice produces one Invoice. Use the existing T9 PoC pattern.
3. **Atomicity rollback** — re-use plan 09 §F2's
   `DocumentWriterTest` pattern; if your document uses the writer
   directly, the rollback via hook still applies.
4. **Snapshotter inmutability** — verify that the document's
   `getCustomerSnapshotAttribute` doesn't mutate when the live
   Customer is changed after the snapshot was taken.
5. **Bouncer ability smoke** — using `Silber\Bouncer\BouncerFacade::allow()`
   in tests is safe; the `ContractComplianceTest` ensures the
   implemented interfaces match the plan matrix.

## Outputs to update once the new type lands

- `config/abilities.php` — already updated in step 6.
- `app/Providers/DocumentServiceProvider.php` — converter
  registered.
- `app/Providers/AuthServiceProvider.php` — `$policies` map entry.
- `app/Services/Documents/Index/DocumentIndex.php` — `allWith()`
  union includes the new model (guarded by `class_exists`).
- `tests/Unit/Documents/ContractComplianceTest.php` — the matrix
  for the new type, with the new row added.
- `docs/architecture/documents.md` matrix — add the new model's
  row.
- Optional: a new plan in `/.agents/plans/` documenting the
  adoption (each new document is itself a feat with its own PoC).

## What happens if you skip a step?

- Skip Step 5 (converter registration): the controller throws
  "No DocumentConverter registered for credit-note->invoice"
  (500). It's a regression visible at smoke test time.
- Skip Step 6 (ability seed): the super admin can still operate,
  but the registry dropdown does not show the new ability for
  custom roles.
- Skip Step 8 (tests): merges that drop a doc-type without
  contract coverage will fail in CI under the brand-new
  `ContractComplianceTest`. Plan 09 introduced the regression lock
  exactly to make this kind of regression impossible.
- Skip Step 7 (routes): the controller is unreachable via HTTP.
  Functional tests do cover it but no UI client can interact.

## Code review checklist (for the reviewer)

- [ ] The new model's `implements` list matches the matrix in this
      runbook.
- [ ] The new converter is in `DocumentConverterRegistry` and
      injects `ConnectionInterface`.
- [ ] The new ability has `depends_on: [view-X, …]` so the
      dependability graph is correct.
- [ ] The `ContractComplianceTest` has been updated for the new
      model (or the matrix row has been added).
- [ ] `DocumentIndex::allWith()` returns the new model rows
      in tests.

End of runbook.
