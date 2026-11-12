# Contributing

Thank you for taking an interest in `crater`. This file gives you the
shortest path to a contribution that lands cleanly.

## Before you write any code

1. Read [`docs/architecture/documents.md`](docs/architecture/documents.md).
   It explains the contract under which every document model lives
   (Invoice, Estimate, RecurringInvoice, DeliveryNote) and how new
   types should be added.

2. If you are adding a new document type, follow
   [`docs/dev/adding-a-document-type.md`](docs/dev/adding-a-document-type.md)
   step by step. The runbook ends with a code-review checklist that
   matches what we look for in PR review.

3. Browse `/.agents/plans/archive/` and `/.agents/plans/` to
   see the historical context. `09-implementacion-document-contract.md`
   is the most recent and is the source of truth for the contract's
   current shape.

4. Read `/.agents/` for shared documentation about how the
   repository organises, what gets ignored, and why some files are
   tracking-ignored locally.

## Workflow

- Branch from `main` (or follow the stacked-PR pattern under
  `plan09/...` if you are extending the contract work).
- Keep commits focused. If your change mixes a feature and a
  refactor, split them.
- We use the proof-by-test pattern that ships with each plan:
  write the regression lock test, see it go red, implement the
  smallest change that makes it green.
- Use conventional Spanish commit messages
  (`Agregado ...`, `Cerrado plan NN`, `fix(area): short title`) like
  the ones already in `git log`.

## Layout overview

- `app/Contracts/Documents/`: shape of the contract
  (`DocumentContract`, ten capability interfaces).
- `app/Services/Documents/`: services (writer, registry,
  snapshotter, index) — these are the wiring between consumers and
  the contract.
- `app/DataObjects/Documents/`: DTOs (CustomerSnapshot,
  AddressSnapshot, DocumentTotals, etc.). Read-only.
- `app/Models/`: Eloquent models. The contract adoption here is
  gradual: the regression lock in
  `tests/Unit/Documents/ContractComplianceTest.php` is the
  authoritative list of what each model has additive `implements`
  for.
- `database/migrations/`: timestamped for reproducible replay. New
  migrations must have a safe `down()` (plan 09 pattern).
- `tests/`: Pest. Tests are split between `Unit/` and `Feature/`.

## Adding a new document type

There is a single runbook for this: see the second-readme link
above. In a few lines:

- Pick a logical `documentType()` string.
- Migrate a new table (`table_x`) with `parent_y_id` FK by plan
  pattern.
- Extend `DocumentIndex::allWith()` with the new model
  (guarded by `class_exists`).
- Register the converter in
  `app/Providers/DocumentServiceProvider::register()`.
- Add the policy, request, resource, controller, and capability
  row in `ContractComplianceTest`.

A `CreditNote` doc is used as a step-by-step in the runbook; if
you find yourself needing features that the runbook does not
cover, **open an issue first** so the runbook gets the missing
detail rather than baking the unwritten convention into the
codebase.

## Tests

- We use Pest. Test files are named after the controller / model
  / concept under test.
- All new tests must pass against SQLite in-memory (the default
  CI fixture). Avoid database-engine-specific SQL in test
  fixtures, but Postgres/MySQL-flavour checks are welcome in
  the absence.
- If you add a new artificial behaviour, add the matching
  regression lock test in the same commit. Plan 09 introduced
  ContractComplianceTest exactly as a lock for new `implements`.
- When in doubt about whether to put a test in `Unit/` or
  `Feature/`, ask: does the test resolve an application context
  (HTTP, queue, mail)? If yes, `Feature/`. Otherwise `Unit/`.

## Pull request conventions

- Title format: "Plan NN - PR-NN (F-keyword): Subject".
- Body must reference the plan it implements and list:
  - Files added (with brief purpose).
  - Files modified (with LOC delta).
  - Tests added (with assertion count).
  - Output CI expectations and whether the four gates G1/G2/G3/G4
    are touched.
- PRs reach ready-for-review state with **all tests passing**
  and a working commit-message that reflects the dominant intent.

## Working with the AI assistant

The AI assistant (`opencode`) can read all directory contents in
this repo but not write to `/` outside the working tree. Always
specify which branch a new feature must land on if you don't want
it on `main`.

## Privacy and security

`crater` is distributed to users across the world and is
self-hosted: please don't include credentials, PII, or production
logs in commits or PRs. When reporting an issue, redact
company / customer names.

## License and credits

By contributing you agree that your contribution follows the
license under which `crater` is distributed. See the root `License.md`
file for the exact terms.
