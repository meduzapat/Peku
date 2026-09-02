# Peku — Architectural Evaluation (CLI-First Design Phase)

Peer review of the modular package vision, the `core → abstraction → driver` topology, and
the CLI-first sequencing. Companion to `baseline-and-prerequisites.md`, which it partly
supersedes — see §0.

**Verified on:** PHP 8.4.19 CLI, ext `pcntl`/`posix`/`readline`/`pdo_{mysql,pgsql,sqlite}`.
Probe results are reproduced inline; every empirical claim below was executed, not recalled.

---

## 0. Correction to the previous research doc

The earlier baseline put **"land `task_5-Request/Response_Founda`"** on the critical path.
With HTTP now explicitly out of scope, that is wrong and I am retracting it.

`task_5` is ~7,200 lines across 22 files. Under CLI-first it splits three ways:

| Part | Files | Disposition |
|---|---|---|
| `Peku\Abstractions` — `Collection`, `MutableCollection`, `Retrievable`, `Mutable` | 4 | **Keep** — domain-neutral, belongs in `peku/base` |
| `Peku\Helpers\Files` — `FileInterface`, `File` (641 lines) | 2 | **Keep, review** — CLI needs file I/O |
| `Peku\Messages\Http`, `Peku\Helpers\Http`, `UploadedFile`, `MessageFactory`, `StatusCodes` | 16 | **Park** on a long-lived branch, do not merge |

Cherry-pick the first two groups onto `develop`; leave the HTTP work on the branch with a
note saying why. Do not delete it — it is a good draft of a layer you will want later, and
it is the reference for what the HTTP adapter must eventually satisfy.

**Consequence for the abstraction layer:** `Peku\Messages` is HTTP-shaped even in its
supposedly generic parts, and this is the clearest live example of the risk in §1.2:

> `Responseable::setCode(int $code)` documents "HTTP: status code (200, 404) / CLI: exit
> code (0=success, 1+=error)", and `getCodeMessage()` returns `"Not Found"` for one context
> and `"Success"` for the other.

Those are not the same concept wearing two hats. HTTP status codes are a 3-digit registry
with reason phrases and semantic classes; process exit codes are 0–255, have no reason
phrases, carry `sysexits.h` conventions, and are truncated modulo 256 by the OS. Unifying
them behind one `int` produces an interface that lies to both callers. Under CLI-first this
resolves itself — build the CLI contract for what a CLI actually is, and let the HTTP
adapter map onto it later (that direction works; the reverse does not).

---

## 1. Architectural evaluation

### 1.1 Strengths — real ones

| | Why it matters |
|---|---|
| **Zero runtime dependencies** | Genuinely rare and genuinely valuable. It is also a *constraint you can market*, which means it should be enforced mechanically in CI, not by intent |
| **PSR-4 only, no `autoload.files`** | The single most common lightweight-framework regression is a `functions.php` in `autoload.files`, which is eager and executes on every invocation. Currently clean — see §1.3 |
| **Noop implementations as defaults** | Correct pattern. Removes null checks at every call site without a null-object bolt-on later |
| **`LogLevel` as a backed enum** | Better than PSR-3's string levels. Keep it — see §2.3 |
| **`strict_types` + typed properties throughout** | One file missing (`Configurable.php`); otherwise disciplined |
| **One test file per source file, established from commit 1** | The habit is the hard part and it is already there |
| **CLI-first sequencing** | The right call for architectural reasons, not just scope ones — see §3.1 |

### 1.2 Risks — ranked by cost of fixing later

**R1 — Abstraction ahead of use case.** `Config`, `Configurable`, `Collection`,
`Retrievable`, `Mutable`, `MessageFactory` and `Responseable` all exist before a single
application consumes any of them. An interface written without a second implementation is a
guess about the future, and it is the most expensive kind of guess because it is public API.

The evidence is already in the tree — two key-value contracts that disagree:

```php
Peku\Abstractions\Retrievable::get(string $key,                mixed $default = null)
Peku\Helpers\Configs\Configurable::get(string $section, string $key, mixed $default = null)
```

Neither is wrong. They disagree because each was designed in its own moment with no
consumer to arbitrate. `Config` predates `Retrievable` and does not implement it.

*Rule worth adopting:* extract an interface when there are **two real implementations**, or
one implementation plus a test double that genuinely differs in behaviour. `Noop` counts
only if it is a deliberate null-object, not a stub.

**R2 — `Helpers` is a junk drawer, and it will decide your package boundaries by accident.**
It currently holds `Loggers`, `Configs`, `Errors`, `Files`, `Utils`, and (on `task_5`)
`Http`. `Helpers\Utils\Data\Values` is four levels deep to reach a class of static casting
functions. "Helper" is not a domain; it is the absence of one.

This matters more than it looks: when you split packages, the question "does this go in
`peku/base`?" will be answered by which folder something happens to sit in. Name namespaces
after what they *are* (`Peku\Console`, `Peku\Config`, `Peku\Log`, `Peku\Db`) and the package
boundaries fall out for free.

**R3 — Mutable process-global state.** `ErrorHandler::$logger`,
`MessageFactory::$customMappings`, `HttpRequest::$trustProxies`. CLI makes this less
dangerous than a worker SAPI would, but two costs survive:

- tests become order-dependent (already visible: `ErrorHandlerTest` must `restore_error_handler()` in `tearDown`, and the registered shutdown function can never be unregistered at all);
- you cannot run two contexts in one process — which is exactly what you need to *test your own CLI end-to-end* without shelling out.

**R4 — `Controller` is HTTP MVC vocabulary in a CLI framework.** It currently holds a name
and nothing else, and its docblock claims it "provides logging, configuration, and naming
infrastructure", which it does not. A CLI has **Commands**. Rename it now while it costs a
`git mv` and one test file — and give it the signature that makes the whole layer testable:

```php
abstract public function execute(Input $input, Output $output): int;   // returns, never exits
```

**R5 — Interface naming will outlive you.** `Loggeable` is not a word — the `-able` form of
"log" is `Loggable`. Same family: `Responseable` (→ `Respondable`, or just drop the suffix).
These are already on two branches and are public API. Cheap now, permanent after the first
tag. Worth ten minutes; not worth an argument.

**R6 — Interface width.** `Configurable` requires 5 methods plus `IteratorAggregate` — six
things to implement for "read some settings". Every one is a promise every future source
must keep. Consider a 2-method read contract with iteration as an optional companion
interface. Interface Segregation is cheapest to apply before implementations exist.

### 1.3 Autoloader patterns

| Practice | Position |
|---|---|
| PSR-4 only, one root per package | Keep. Never add `autoload.files` — it is eager and runs on every CLI invocation |
| Package name ↔ namespace root ↔ directory | `peku/db` → `Peku\Db\` → `src/`. One rule, no exceptions, no nested roots |
| Production install | `--classmap-authoritative` for distribution; `--optimize-autoloader` minimum. **CLI pays autoloader cost on every single invocation and never amortizes it** — unlike a warm HTTP worker |
| `class_exists()` for feature/driver discovery | **Never.** It is exactly what `--classmap-authoritative` breaks, and it makes startup cost proportional to how many optional things exist |
| Driver discovery via Composer plugins or directory scanning | **Never.** Incompatible with authoritative classmaps and with `opcache.preload`; unreviewable; magic. Use explicit registration from the consuming app, or a `const` map inside the driver package |
| `opcache.preload` | Real lever for long-running SAPIs, near-useless for one-shot CLI. Do not design for it now; do not preclude it (it is precluded by `autoload.files` and by constructors with side effects) |

Startup cost is currently **unmeasured**. Since it is now the framework's most load-bearing
performance claim under CLI-first, it needs a number before it needs an optimisation.

---

## 2. Dependency & package topology

### 2.1 The `peku/db` → `peku/db-mysql` split needs justifying before it is built

The stated philosophy is *leverage native PHP, do not reinvent the wheel*. Applied honestly
to databases, that philosophy has a sharp consequence: **PDO is already the driver layer.**
`peku/db-mysql` therefore cannot be a driver — that role is occupied — so it must be
something else, and it is worth naming what.

Verified against PHP 8.4:

```
PDO methods: __construct, beginTransaction, commit, connect, errorCode, errorInfo, exec,
             getAttribute, getAvailableDrivers, inTransaction, lastInsertId, prepare,
             query, quote, rollBack, setAttribute
has quoteIdentifier? NO
```

| Concern | PDO handles it | Notes |
|---|---|---|
| Connect, prepare, bind, execute, fetch | **yes** | uniform across drivers |
| Transactions (begin/commit/rollback) | **yes** | savepoints are *not* uniform |
| Value quoting | **yes** | `quote("O'Brien")` → `'O''Brien'` |
| Error reporting | **yes** | SQLSTATE common; vendor codes are not |
| Driver identification | **yes** | `PDO::ATTR_DRIVER_NAME` |
| **Identifier quoting** | **no** | backtick vs `"` vs `[` — no API at all |
| **LIMIT / OFFSET syntax** | **no** | `LIMIT n` vs `FETCH FIRST n ROWS ONLY` vs `TOP n` |
| **Upsert** | **no** | `ON DUPLICATE KEY` vs `ON CONFLICT` vs `MERGE` |
| **`RETURNING`** | **no** | PG/SQLite yes, MySQL no |
| **`lastInsertId()` semantics** | **partly** | returns `string`; PostgreSQL needs a sequence name argument |
| **Schema introspection** | **no** | `information_schema` vs `pg_catalog` vs `sqlite_master` |
| **Type mapping** | **no** | notably `BOOLEAN`, `JSON`, date/time |

Everything in the "no" rows is **dialect**, not driver. So:

> **The split is justified if and only if `peku/db` generates SQL.**
> A query builder, schema builder, or migration engine needs dialects. A "prepare, execute,
> fetch, transact" wrapper does not — and `peku/db-mysql` would be an empty package with a
> `composer.json`.

That is a **scope** decision (do you want a query builder — likely the largest single
component in the framework?) masquerading as a **packaging** decision. Answer the scope
question first.

**Recommendation:** ship one `peku/db` that wraps PDO thinly and contains an internal
`Dialect` interface with a `MySql` implementation *in the same package*. Split it out when a
second dialect exists and the package is genuinely too big. Splitting later is a
`composer.json` edit plus a `replace` entry. Guessing wrong now is public API you must
support forever.

### 2.2 Dependency direction rules

These are the rules that keep a `core → abstraction → driver` topology from rotting:

1. **Core depends on nothing but PHP.** Enforce in CI, not by good intentions.
2. **Abstraction packages depend on core only.**
3. **Driver/dialect packages depend on their own abstraction — never on each other, never on the application.**
4. **No package depends on a sibling at the same level.** If `peku/db` wants logging, it depends on the *logging interface in core*, never on `peku/log`. This one rule prevents nearly every cycle.
5. **Contracts live with the abstraction that owns them.** Resist a shared `peku/contracts` package: it becomes a second core, everything depends on it, and it is a coupling magnet. Rule 4 removes the cycle-breaking motive that usually justifies it.
6. **Inter-package constraints use `^1.0`, never `self.version`.** `self.version` forces lockstep releases of every package forever, which is precisely the flexibility the split was meant to buy.
7. **Optional extensions go in `suggest` + a runtime capability check, never `require`.** Applies immediately to `ext-pcntl` (§3.2).

Rules 1–4 are mechanically checkable. A `deptrac` config, or a PHPStan rule, or in the
simplest case a CI grep over `use` statements — any of them beats review discipline.

### 2.3 PSR interfaces vs native contracts

The framing "PSR vs lightweight" is mostly a false trade. A PSR package is a handful of
`interface` declarations: it costs a line in `composer.json`, a few files on disk, and
**zero runtime overhead** — interfaces are not instantiated. The real cost is *design
conformance*: a PSR can impose a worse design than the one you have.

So decide per-PSR on design merit, not on dependency count.

| PSR | Verdict | Reasoning |
|---|---|---|
| **PSR-4** autoloading | **Adopt** (done) | Non-negotiable |
| **PSR-3** Logger | **Bridge, do not adopt** | PSR-3 mandates `log($level, $message, $context)` with *string* levels and `{placeholder}` interpolation. Your `LogLevel` enum is genuinely better in a strict-types codebase. Keep `Loggable` native; publish `peku/log-psr` — one adapter class each way. You get Monolog compatibility without importing a weaker contract into your core |
| **PSR-11** Container | **Adopt the interface — if you build a container at all** | Two methods, no behaviour, total interop. But first decide whether Peku has a container (see baseline doc, A-02). Do not adopt an interface for a component you have not committed to |
| **PSR-14** Events | **Adopt if you add events** | Three tiny interfaces, no better design available |
| **PSR-6 / PSR-16** Cache | **Defer** | No cache component exists |
| **PSR-7 / 15 / 17** HTTP | **Out of scope — do not pre-adopt** | Deciding an interop stance for a deferred layer is speculative work, and PSR-7's immutability model is a large commitment to make blind |

The pattern to internalise: **native contracts in the core, PSR bridges as separate optional
packages.** The core stays yours and stays lightweight; the ecosystem still reaches you; and
nobody pays for a bridge they do not install.

---

## 3. CLI-first strategy

### 3.1 Why this is architecturally right, not just scope-convenient

CLI is the honest test of a kernel. With no request lifecycle, no superglobals, no output
buffering, no headers, and no session, a component either works on its own merits or it does
not. Build the kernel that way and the HTTP layer is later a genuine adapter. Build it the
other way and HTTP assumptions leak into the core permanently — `Responseable::setCode()`
in §0 is that leak, already present, before any HTTP code has even merged.

CLI also applies real backpressure on startup cost: every invocation pays the full bootstrap
with nothing warm to amortize against. That makes the "performance" claim measurable, which
in turn makes it arguable.

### 3.2 `getopt()` cannot parse a subcommand CLI — verified

Since `peku/base` will be subcommand-driven (`peku db:migrate --force`), this is decisive.
`getopt()` **stops at the first non-option argument**:

```console
$ php probe.php db:migrate --force --name=x -v      # the natural CLI shape
opts      = []                                       # ← parsed nothing
rest      = ["db:migrate","--force","--name=x","-v"]

$ php probe.php --force --name=x -v db:migrate      # options first: works
opts      = {"force":false,"name":"x","v":false}

$ php probe.php -v -v -v                            # repeated flag
opts      = {"v":[false,false,false]}                # ← string|false|array union
```

Three problems, all confirmed: it parses nothing in the shape users actually type; making it
work requires `peku --force db:migrate`, which nobody writes; and its return type is a
`string|false|array` union that fights `strict_types` on every access.

**Conclusion:** hand-roll the argv parser. It is ~200 lines and fully testable. This is the
one place where "use native PHP" does not apply, and it is worth stating explicitly in the
docs *with this evidence* — otherwise the philosophy gets applied dogmatically by the next
contributor and you inherit a broken CLI.

### 3.3 `peku/base` component set, in dependency order

| # | Component | Native primitive | Notes |
|---|---|---|---|
| 1 | **Argv parser** | none usable (§3.2) | tokens → `Input`. Pure function of `array $argv`, no globals |
| 2 | **Input / Output** | `STDIN`/`STDOUT`/`STDERR`, `fwrite`, `stream_get_contents` | **Inject as objects.** Never write to the constants from a command |
| 3 | **Command** + registry | — | `execute(Input, Output): int`. Returns, never `exit()`s |
| 4 | **Application kernel** | — | owns argv → dispatch → exit code. The *only* place `exit()` appears |
| 5 | **TTY / formatting** | `stream_isatty()` — verified `false` when piped | Honour `NO_COLOR` and `TERM=dumb`. Colour only when all three agree |
| 6 | **Exit-code policy** | `exit()` | `sysexits.h` (64 usage, 65 dataerr, 70 software, 77 noperm) or plain 0/1. Pick one, write the ADR. Note the OS truncates mod 256 |
| 7 | **Signals** | `pcntl_async_signals(true)` + `pcntl_signal` — verified, clean SIGTERM → exit 143 | `ext-pcntl` in **`suggest`**, not `require` (rule 7). Degrade to unhandled |
| 8 | **Prompts / hidden input** | `readline`, `stty -echo` | `ext-readline` optional |
| 9 | **Subprocess** | `proc_open` | exit-code propagation, stream capture |
| 10 | **Progress / spinners** | ANSI | gated on #5 |

Existing components map in cleanly: `Config`, `Logger`, `ErrorHandler`, `Files\File`,
`Abstractions\Collection` all belong to `peku/base` unchanged in purpose.

### 3.4 The one design risk that decides whether any of this is testable

Coupling to process globals — `exit()`, `STDOUT`, `$argv`, `getcwd()`, `getenv()` — scattered
through command classes. It is the default outcome, it is invisible until you try to write
the second test, and it is expensive to undo.

The discipline is small and absolute:

- commands **return** exit codes, never call `exit()`;
- `exit()` appears exactly once, in the kernel;
- `$argv`, env, and cwd are constructor/method arguments, never read in place;
- output goes through an injected `Output`, so a test can pass an in-memory stream.

Get this right and the whole CLI is unit-testable in-process with no fixtures and no
subprocess spawning. Get it wrong and every test shells out, and the suite takes minutes.

---

## 4. Documentation & DX roadmap

The design phase needs **decision records and contracts** — not user documentation. User
docs written now describe an API that will not survive the month, and stale docs cost more
credibility than missing ones.

### 4.1 Write now

| Deliverable | Why now | Effort |
|---|---|---|
| **ADR log** — `docs/adr/NNNN-title.md` + `0000-template.md` | **Highest value item in this document.** Requirements are explicitly fluid; ADRs are what let you change your mind *cheaply* — with the original reasoning preserved. Without them you re-litigate PSR-3, exit codes, and the `db` split every few weeks | 1 page each |
| **Architecture overview** — one page, one diagram | Package boundaries and dependency arrows. Enforces §2.2 by making violations visible | ~half a day |
| **Contract catalog** | Every public interface, with `@api` / `@internal` marked. This *is* your BC surface; it cannot be reconstructed later | grows with code |
| **Package creation guide + skeleton** | Written before package #2 so packages #2 and #3 do not each invent their own layout, CI, and namespace convention | ~half a day, pays back at package 2 |
| **Coding standard, prose** | `phpcs.xml` encodes part of it. The tabs-for-indent / spaces-for-alignment rule is unwritten and unenforceable by PHPCS — it exists only in your head and in the existing code | ~half a day |
| **CONTRIBUTING.md** | Branch naming (the current `task_5-Request/Response_Founda` contains a `/`, which permanently blocks any branch named `task_5-Request`), commit format, PR expectations | short |
| **SECURITY.md** | Required before any public tag | short |

### 4.2 Defer until after the v0.1.0 API freeze

User guide, tutorials, generated API reference, a docs site. All of it will be invalidated by
design churn, and maintaining it during churn is a tax on changing your mind — the one thing
you most need to stay cheap right now.

### 4.3 Two DX rules worth adopting today

1. **Any PR changing a public contract adds or amends an ADR.** Mechanically checkable in CI later; a review habit now.
2. **Docs live in-repo as markdown, versioned with the code.** No site, no generator, no external wiki until the API is stable.

---

## 5. Options matrix — repository & package topology

| | **A — Monorepo, split later** | **B — Polyrepo now** | **C — Single package, defer split** |
|---|---|---|---|
| Structure | one repo, `packages/base`, `packages/db`; CI subtree-splits to read-only consumer repos | `peku/base`, `peku/db`, `peku/db-mysql` as separate repos from day one | one `peku/peku`; "packages" are namespaces only |
| Cross-package refactor | free — one PR, one CI run | painful — coordinated PRs + version dance | free |
| Consumer install | `composer require peku/base` works, via splits | works natively | all-or-nothing |
| Independent release cadence | yes, after splits | yes | no |
| Boundary enforcement | directory + CI dependency check | enforced by physics | **review discipline only** — needs a tool |
| Setup cost | moderate — split CI, ~a day | low | none |
| Fits "requirements remain fluid" | **yes** | **no** | yes |
| Main risk | split tooling to maintain | premature version coupling: a one-line fix becomes three releases and a constraint bump | boundary erosion — nothing stops `base` from reaching into `db` |
| Reversible | yes, cheaply | expensive | yes, if boundaries were policed |

**Recommendation: Path A.** It is the only option that gives real package boundaries *and*
free refactoring, and free refactoring is exactly what a fluid design phase spends.

**Path C is an acceptable interim** if split CI feels like a distraction this month — but
only with a mechanical dependency check (deptrac, or a PHPStan rule, or a CI grep over `use`
statements) from day one. Without it, C is not a path to A; it is a path to a monolith with
aspirational folder names.

**Path B is wrong at this stage.** You would pay full multi-repo coordination cost to buy
independent release cadence — a benefit that is worth nothing with one maintainer and zero
external consumers, and which Path A grants you anyway the day you need it.

### 5.1 Immediate next steps

Ordered by dependency. Roughly two weeks.

| # | Step | Unblocks |
|---|---|---|
| 1 | Cherry-pick `Abstractions\*` and `Files\File` from `task_5` onto `develop`; park the HTTP remainder with a note | a single agreed baseline |
| 2 | Clear the P0 items from `baseline-and-prerequisites.md` — chiefly `phpunit.xml` into VCS, commit `composer.lock`, fix the `lint`/`analyse` script/config mismatch | CI that can actually pass |
| 3 | Fix `ErrorHandler` (`E_STRICT` on 8.4, `error_reporting()` check, double-log, uninitialised static) and add PHP 8.4 to the CI matrix | a trustworthy error path — a CLI's error path *is* its UX |
| 4 | ADR-0001 repository topology → decide Path A / C | the directory layout |
| 5 | ADR-0002 PSR stance per §2.3; ADR-0003 exit-code policy; ADR-0004 `Command` replaces `Controller` | the CLI contracts |
| 6 | Build `peku/base` components 1–4 from §3.3 with the §3.4 discipline | the first runnable `peku` binary |
| 7 | Write the architecture overview + package creation guide against what now exists | package #2 |
| 8 | Answer the query-builder scope question, then decide `peku/db` topology per §2.1 | the `db` epic |

Steps 1–3 are unblocking work on what exists. Step 4 onward is the new architecture. They
can run in parallel if you want momentum on both.

---

## Appendix — probe commands

```bash
# §3.2 getopt subcommand failure
php -r '$o=getopt("f:v",["force","name:"],$i); echo json_encode($o),"\n";' -- db:migrate --force

# §2.1 PDO API surface
php -r 'print_r(get_class_methods("PDO"));'
php -r 'echo implode(",", PDO::getAvailableDrivers());'

# §3.3 TTY detection and signals
php -r 'var_dump(stream_isatty(STDOUT));'                 # false when piped
php -r 'pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn($s) => exit(143));
        posix_kill(posix_getpid(), SIGTERM);'             # → exit 143
```
