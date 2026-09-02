# ADR-0005: Database package topology

* **Status:** Proposed — blocked on a scope answer
* **Date:** 2026-09-02
* **Deciders:** Patricio Rossi

## Context

The proposed topology is `peku` → `peku/db` → `peku/db-mysql`: a core, a database
abstraction, and per-vendor driver packages. Both are design concepts; no code exists.

The project philosophy is to leverage native PHP rather than reinvent it. Applied to
databases, that has a sharp consequence: **PDO is already the driver layer.** Verified
against PHP 8.4:

```
PDO methods: __construct, beginTransaction, commit, connect, errorCode, errorInfo, exec,
             getAttribute, getAvailableDrivers, inTransaction, lastInsertId, prepare,
             query, quote, rollBack, setAttribute
has quoteIdentifier? NO
```

| Concern | PDO covers it |
|---|---|
| Connect, prepare, bind, execute, fetch | yes |
| Transactions (begin/commit/rollback) | yes — savepoints are not uniform |
| Value quoting | yes — `quote("O'Brien")` → `'O''Brien'` |
| Error reporting | yes — SQLSTATE common, vendor codes not |
| Driver identification | yes — `PDO::ATTR_DRIVER_NAME` |
| Identifier quoting | **no** — no API at all |
| `LIMIT` / `OFFSET` syntax | **no** |
| Upsert, `RETURNING` | **no** |
| `lastInsertId()` semantics | **partly** — returns `string`; PostgreSQL needs a sequence name |
| Schema introspection | **no** |
| Type mapping (bool, JSON, date/time) | **no** |

Everything PDO does not cover is **dialect**, not driver. A `peku/db-mysql` package
therefore cannot be a driver — that role is occupied — so it can only carry dialect
knowledge. And dialect knowledge only has somewhere to live if `peku/db` generates SQL.

This makes the topology question downstream of a scope question:

> **Does `peku/db` include a query builder, schema builder, or migration engine?**

* **No** → `peku/db` is connection management, prepared statements, fetching and transactions over PDO. The three places behaviour differs are handled by `ATTR_DRIVER_NAME`. `peku/db-mysql` would contain a `composer.json` and nothing else.
* **Yes** → dialects are real, and you have signed up for what will likely be the framework's largest component.

## Decision

*(Pending the scope answer above.)* The proposed decision is:

Ship **one `peku/db` package** wrapping PDO, containing an internal `Dialect` interface with
a `MySql` implementation in the same package. Split dialects into separate packages only
when a second dialect exists **and** the package has become genuinely unwieldy.

Interface boundaries are drafted in [`docs/design/db-contracts.md`](../design/db-contracts.md).

## Consequences

* Splitting later is a `composer.json` edit plus a `replace` entry — cheap, and reversible.
* Guessing the split now produces public API that must be supported regardless of whether it earned its existence.
* A `Dialect` seam exists from day one, so the split remains available without a rewrite.
* Users install one package for database access rather than two.
* If the scope answer turns out to be "yes, query builder", this ADR is superseded rather than merely adjusted — the package shape genuinely differs.

## Alternatives considered

**`peku/db` + `peku/db-mysql` from the start, as originally proposed.** Rejected for now:
it pays the full cost of a package boundary (versioning, release coordination, cross-repo
refactoring, published API) to buy modularity that has no second implementation to justify
it. If `peku/db` stays thin, the second package is empty.

**Skip the abstraction; use PDO directly in applications.** A serious option, consistent
with the "do not reinvent" philosophy. Rejected because connection lifecycle, configuration
binding, error translation into the framework's exception hierarchy, and logging integration
are real and would otherwise be rewritten in every application.

**Name driver packages for what they are (`peku/db-dialect-mysql`).** Kept in reserve. If
the split ever happens, the name should say "dialect", because "driver" is PDO's job and the
wrong name would mislead implementers about what belongs inside.
