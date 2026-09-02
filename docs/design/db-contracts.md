# Design Draft — `peku/db` Contracts & Interface Boundaries

**Status:** draft for review. No implementation. Companion to
[ADR-0005](../adr/0005-database-package-topology.md), which is blocked on the scope question
in §1.

---

## 1. The question this draft is downstream of

> **Does `peku/db` generate SQL — a query builder, schema builder, or migrations?**

Everything below assumes **no**: a thin, honest wrapper over PDO. If the answer is yes, the
contracts change shape (a `Grammar`/`Compiler` seam appears, `Dialect` grows substantially,
and the vendor split in ADR-0005 becomes justified). Answer this before implementation
starts.

## 2. Design principles

| # | Principle | Consequence |
|---|---|---|
| 1 | **PDO is the driver. We do not replace it.** | No connection pooling, no protocol code, no re-implemented prepared statements |
| 2 | **The escape hatch is public, not hidden.** `pdo()` is part of the contract | The wrapper can never block you. A wrapper you have to fight is worse than no wrapper |
| 3 | **Lazy connection.** Constructing a connection must not open a socket | A CLI command that never touches the database must not pay for one. This is a real cost under ADR-0002 |
| 4 | **Exceptions always.** `PDO::ERRMODE_EXCEPTION` is set, not optional | Silent failure is the one thing a data layer must never do |
| 5 | **Results stream by default.** Iteration yields rows; materialising is explicit | A CLI batch job over a large table must not be a memory decision made for it |
| 6 | **Minimum viable contract.** Ship what a consumer needs; add on demand | See §5 — the deliberate exclusions matter as much as the inclusions |

Principle 6 applies this project's own lesson: `Configurable::get($section, $key)` and
`Retrievable::get($key)` already disagree because both were designed without a consumer to
arbitrate. Every method below should be justifiable by a caller that exists.

## 3. Contracts

Namespace `Peku\Db`. Naming follows the project's existing `-able` convention.

### 3.1 `Connectable` — connection lifecycle

```php
interface Connectable {

	/**
	 * Get the underlying PDO handle, connecting on first use.
	 *
	 * Deliberately public: anything this package does not cover remains reachable.
	 *
	 * @throws ConnectionException If the connection cannot be established
	 */
	public function pdo(): \PDO;

	/**
	 * Dialect for this connection's vendor.
	 */
	public function dialect(): Dialect;

	/**
	 * Whether a connection is currently open.
	 */
	public function isOpen(): bool;

	/**
	 * Close the connection. Safe to call when already closed.
	 */
	public function close(): void;
}
```

### 3.2 `Queryable` — statement execution

```php
interface Queryable {

	/**
	 * Run a statement that returns rows.
	 *
	 * @param string $sql    Statement with named or positional placeholders
	 * @param array  $params Bound parameters
	 * @throws QueryException On failure, carrying the statement and SQLSTATE
	 */
	public function query(string $sql, array $params = []): Result;

	/**
	 * Run a statement that returns no rows.
	 *
	 * @return int Affected row count
	 * @throws QueryException On failure
	 */
	public function execute(string $sql, array $params = []): int;

	/**
	 * Last generated identity value.
	 *
	 * @param string|null $sequence Sequence name; required by PostgreSQL, ignored by MySQL.
	 *                              The dialect supplies it when the caller does not.
	 */
	public function lastInsertId(?string $sequence = null): string;
}
```

`lastInsertId(): string` matches PDO, which returns a string even for integer keys — verified
on PHP 8.4. Do not silently cast it to `int`: the value can exceed `PHP_INT_MAX` on
`BIGINT UNSIGNED` columns.

### 3.3 `Transactional` — transaction control

```php
interface Transactional {

	public function begin(): void;
	public function commit(): void;
	public function rollback(): void;
	public function inTransaction(): bool;

	/**
	 * Run work inside a transaction, committing on return and rolling back on throw.
	 *
	 * @param callable $work fn(Queryable $db): mixed
	 * @return mixed Whatever $work returns
	 */
	public function transaction(callable $work): mixed;
}
```

`transaction()` is the method that will actually get used; the four primitives exist because
some workflows genuinely need manual control. **Open question in §6:** whether nested calls
map to savepoints or are an error.

### 3.4 `Result` — row access

```php
interface Result extends \IteratorAggregate, \Countable {

	/**
	 * First row, or null when the result is empty.
	 *
	 * @return array<string, mixed>|null
	 */
	public function one(): ?array;

	/**
	 * All rows. Materialises the whole result — see getIterator() for large sets.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function all(): array;

	/**
	 * A single column across all rows.
	 *
	 * @return list<mixed>
	 */
	public function column(int $index = 0): array;

	/**
	 * First column of the first row — the scalar-query case.
	 */
	public function value(mixed $default = null): mixed;

	/**
	 * Stream rows one at a time. Does not buffer.
	 *
	 * @return \Traversable<int, array<string, mixed>>
	 */
	public function getIterator(): \Traversable;
}
```

`getIterator()` returning a generator over `PDOStatement::fetch()` is the memory-safe path,
and it is native PHP doing the work. Note the MySQL caveat in §6.

### 3.5 `Dialect` — the vendor seam

This is the interface that decides ADR-0005. Under the thin-wrapper assumption it stays
small:

```php
interface Dialect {

	/**
	 * Driver name as reported by PDO::ATTR_DRIVER_NAME.
	 */
	public function name(): string;

	/**
	 * Quote a table or column name. PDO has no equivalent — verified: no quoteIdentifier().
	 */
	public function quoteIdentifier(string $identifier): string;

	/**
	 * Sequence name for lastInsertId(), or null when the vendor does not need one.
	 */
	public function sequenceFor(string $table, string $column): ?string;

	/**
	 * Translate a driver exception into the framework hierarchy.
	 */
	public function translate(\PDOException $error): DatabaseException;
}
```

Four methods. If this interface stays this size, ADR-0005's "one package" conclusion holds —
a `MySql` implementation is perhaps 40 lines and does not deserve its own repository. If the
scope answer in §1 is "yes, query builder", this interface grows to cover `LIMIT`/`OFFSET`
syntax, upsert, `RETURNING`, savepoints, schema introspection and type mapping — at which
point the split earns itself.

**The size of this interface is the decision criterion.** Watch it.

### 3.6 Exceptions

```
DatabaseException            (extends the core framework exception — see §6)
├── ConnectionException      cannot connect, connection lost
├── QueryException           statement failed; carries SQL + SQLSTATE
├── TransactionException     commit/rollback failure, illegal nesting
└── IntegrityException       constraint violation (SQLSTATE 23xxx)
```

`IntegrityException` is separate because a unique-constraint violation is routinely a normal
application outcome — a duplicate registration — not an error. Callers should be able to
catch it without catching every database failure.

## 4. Package boundaries

```
peku (core)          config · logging · errors · files · collections
   ↑
peku/db              Connectable · Queryable · Transactional · Result · Dialect
                     + Dialect\MySql (in-package, per ADR-0005)
```

Rules from the topology work, applied here:

* `peku/db` depends on the **core**, never on a sibling package.
* Query logging depends on the core's **logging interface**, never on a concrete logger.
* `peku/db` never depends on an application or on a delivery layer.
* Requires `ext-pdo`; each dialect declares its own `ext-pdo_*` in `suggest`, not `require`.

## 5. Deliberately excluded from v1

Per principle 6. Each of these is a real feature; none has a consumer yet.

| Excluded | Add when |
|---|---|
| Query builder / schema builder / migrations | §1 is answered "yes" — then it drives the whole design |
| Connection pooling / multiple named connections | A second connection genuinely exists |
| Read/write splitting, replica routing | There is a replica |
| ORM, hydration, identity map | Never in this package; a separate one if ever |
| Result caching | The cache component exists (none does) |
| Async / non-blocking queries | The runtime model supports it |
| Automatic reconnection on lost connection | Its semantics are decided — silent retry is dangerous inside a transaction |

## 6. Open questions blocking implementation

| # | Question | Blocks |
|---|---|---|
| 1 | **Query builder in scope?** | Everything. ADR-0005, `Dialect` size, package split |
| 2 | **Nested transactions:** savepoints, or throw on nested `begin()`? | `Transactional`. Savepoint syntax is vendor-specific, so supporting them enlarges `Dialect` and strengthens the split case |
| 3 | **Core exception base.** `DatabaseException` needs a parent. `ConfigException` and `FileException` currently extend `RuntimeException` directly, so there is no `PekuException` to inherit | Exception hierarchy across the whole framework — decide once, not per package |
| 4 | **Buffered vs unbuffered queries.** MySQL buffers by default; streaming large results needs `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY = false`, which then forbids further queries on the connection until the result is consumed | `Result::getIterator()` — principle 5 is not free on MySQL and the contract must be honest about it |
| 5 | **Query logging:** built in, or a decorator? | Whether `peku/db` depends on the core logging interface at all. A decorator keeps the dependency at zero |
| 6 | **Config binding.** How does a connection get its DSN — `Configurable`, an explicit DSN string, or both? | The constructor signature, and whether `peku/db` depends on the config component |

Questions 1 and 3 should be answered first: 1 decides the package, 3 decides every
signature that throws.
