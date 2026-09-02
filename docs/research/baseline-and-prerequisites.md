# Peku — Research Baseline & Prerequisites

Preliminary research for Project Peku: what exists today, what is missing, what must be
decided, and what has to happen before epics and tickets can be written.

**Status:** research output — no framework code changed by this document.
**Partly superseded:** HTTP is now explicitly out of scope and the project is CLI-first. `S-01`/`T-10` ("land `task_5`") no longer hold — see [`architecture-evaluation.md` §0](architecture-evaluation.md#0-correction-to-the-previous-research-doc). Everything else stands.
**Analysed refs:** `origin/develop` @ `226581f`, `origin/main` @ `77faf60`,
`origin/task_5-Request/Response_Founda` @ `23cc0be`.

## Method & confidence

| Kind | How it was established |
|---|---|
| **Verified (executed)** | Reproduced on PHP 8.4.19 CLI against the real source. Snippets in Appendix A. |
| **Verified (static)** | Read directly from tracked files; line references given. |
| **Open question** | Not decidable from the repository — needs a decision. |

The dev toolchain could **not** be installed in the research sandbox (`composer install`
fails: GitHub API auth/proxy). The full test suite, PHPStan and PHPCS were therefore not
executed. Every finding below is either executed against source directly or read from
tracked files — none depend on a suite run.

---

## 1. Baseline — what actually exists

### 1.1 On `develop` / `main` (identical trees)

| Namespace | Components | Tests |
|---|---|---|
| `Peku\Controllers` | `Controller` (abstract; holds a name, nothing else) | yes |
| `Peku\Helpers\Loggers` | `Loggeable`, `Logger`, `LogLevel`, `Noop`, `File`, `Syslog` | yes |
| `Peku\Helpers\Configs` | `Configurable`, `Config`, `ConfigException`, `Noop`, `Php`, `Env` | yes |
| `Peku\Helpers\Errors` | `ErrorHandler` | yes |
| `Peku\Helpers\Files` | `FileException` | yes |
| `Peku\Helpers\Utils` | `StaticUtility`, `Data\Values` | yes |

Zero runtime dependencies. PHP `>=8.3`. PSR-4 only.

### 1.2 Unmerged work on `task_5-Request/Response_Founda`

**~7,200 lines / 22 new source files** that are not on `develop`, introducing three
top-level namespaces:

| Namespace | Components |
|---|---|
| `Peku\Abstractions` | `Retrievable`, `Mutable`, `Collection`, `MutableCollection` |
| `Peku\Messages` | `Requestable`, `Responseable`, `Request`, `Response`, `RequestType`, `MessageFactory` |
| `Peku\Messages\Http` | `HttpRequest`, `HttpResponse`, `StatusCodes` |
| `Peku\Helpers\Http` | `Extractable`, `Extractor`, `Normal`, `UploadedFile`, `UploadException` |
| `Peku\Helpers\Files` | `FileInterface`, `File` (641 lines) |

**This is the single most important scoping fact in this document.** Any epic breakdown
written against `develop` is written against a baseline that is already superseded. This
branch must be reviewed and landed, or explicitly parked, *before* ticketing — see `S-01`.

### 1.3 Issue backlog

| # | Title | State |
|---|---|---|
| 1 | Framework Foundation | closed |
| 2 | Error Handling System | closed |
| 5 | Request/Response Foundation | **open** — work in progress on `task_5-…` |

There is no issue covering routing, DI, security primitives, persistence, views, or
release engineering. The backlog currently describes ~5% of a framework.

---

## 2. Blocking setup prerequisites

These prevent a clean clone from building, testing, or being reviewed. They are cheap and
they gate everything else.

| ID | Finding | Evidence | Impact |
|---|---|---|---|
| **S-01** | `task_5` branch (§1.2) is unmerged and unreviewed | `git diff origin/develop origin/task_5-RR --stat` | Baseline is undefined; ticketing cannot start |
| **S-02** | `phpunit.xml` is **absent from version control and gitignored** | `.gitignore:182`; `ls phpunit.xml*` → missing | `composer test` (bare `phpunit`) has no suite; `test:unit`/`test:integration` reference testsuites defined nowhere. CI's test step cannot pass on a fresh clone |
| **S-03** | `composer.lock` not committed | `git ls-files composer.lock` → empty | Dev toolchain floats within caret ranges; CI can break with no code change. A PHPUnit 11 release alone would break the suite (see `D-19`) |
| **S-04** | `composer lint` = `phpcs src/`, but `phpcs.xml` declares `<file>src</file><file>tests</file>` | `composer.json` scripts; `phpcs.xml:5-6` | `tests/` is never style-checked despite being in the ruleset |
| **S-05** | `composer analyse` passes `--level=8` while `phpstan.neon` also sets `level: 8`; `paths:` is `src` only | `composer.json`; `phpstan.neon` | Duplicate config; `tests/` unanalysed. The blanket ignore `#no value type specified in iterable type array#` cancels most of what level 8 buys |
| **S-06** | CI triggers only on `pull_request` → `develop` | `.github/workflows/ci.yml:3-6` | No push builds, no `main` gating, no scheduled runs. PR #4 (develop→main) merged with no CI |
| **S-07** | CI matrix is PHP 8.3 only | `ci.yml` matrix | Forward-compat unknown; see `D-01`, which currently blocks adding 8.4 |
| **S-08** | README (`develop`) says "PHP 8.0+"; `composer.json` requires `>=8.3` | README "Requirements" vs `composer.json` | Contradictory published contract. Fixed on `task_5` — another reason to land it |
| **S-09** | README documents `composer require peku/framework`; package is not published | README "Installation" | Documented install path does not work |
| **S-10** | `tests/README.md` describes a directory layout that does not exist (`Fixtures/configs/`, `Fixtures/Mocks/`, `tests/phpunit.xml`) | `tests/README.md` vs `find tests` | Contributors follow a fictional structure |
| **S-11** | README "Project Structure" omits `Env`, `ErrorHandler`, `Utils/`, `Values` | README vs `find src` | Stale on the first thing a contributor reads |
| **S-12** | Missing repo hygiene: `.gitattributes`, `SECURITY.md`, `CONTRIBUTING.md`, `CHANGELOG.md`, `.editorconfig`, issue/PR templates, CODEOWNERS | `ls`; `find .github` | No `export-ignore` → tests ship in the dist package. Tabs-and-alignment is a hard project rule with nothing enforcing it in editors. No security disclosure path for a security-first framework |
| **S-13** | No coverage gate; `test:coverage` never runs in CI | `ci.yml` | Coverage is claimed but unmeasured on every PR |
| **S-14** | Branch name contains `/` in the task segment: `task_5-Request/Response_Founda` | `git ls-remote --heads` | Ref-namespace collision hazard (`task_5-Request` can never exist as a branch) and a truncated name. Needs a branch-naming convention |

---

## 3. Architectural decisions required before ticketing

Each of these changes the shape of the tickets that follow it. They are ADRs, not tickets.
**`A-01` and `A-02` are on the critical path — they must be settled before `task_5` lands**,
because Request/Response is the first component with real external surface area.

| ID | Decision | Why it cannot wait |
|---|---|---|
| **A-01** | **PSR interop stance** — PSR-3 (log), PSR-7/17 (HTTP messages/factories), PSR-15 (middleware), PSR-11 (container), PSR-14 (events) | Everything is bespoke today (`Loggeable`, `Requestable`, `Responseable`). This decides whether third-party middleware and loggers can *ever* be used. Retrofitting PSR-7 onto a shipped `HttpRequest` is a breaking rewrite; deciding now is free. A middle path exists (own interfaces + thin PSR bridges) and should be evaluated explicitly, not by default |
| **A-02** | **Composition root & object lifecycle** — container vs. static registries vs. constructor injection everywhere | There is no bootstrap, no front controller, no wiring. `Controller`'s docblock claims it "provides logging, configuration…" — it provides neither (`Controller.php:19-20`). `MessageFactory` chose mutable static registries by default rather than by decision |
| **A-03** | **Runtime model** — classic per-request SAPI only, or also long-running workers (FrankenPHP / RoadRunner / Swoole) | Mutable process-global state already exists in three places: `ErrorHandler::$logger`, `MessageFactory::$customMappings`, `HttpRequest::$trustProxies`. All are unsafe and order-dependent under a worker runtime, and all are hard to test in-process. Decide before more accumulate |
| **A-04** | **Error semantics** — fail loud vs. silent defaults | Currently mixed: `Values::cast()` silently falls back on malformed input (`Values.php:43-51`), `Config::get()` returns a default, `Loggers\File::log()` discards `file_put_contents()`'s result (`File.php:50`), `Noop` swallows everything. The project's stated principle is *expose invalid states and crash loudly*. Pick one policy and apply it uniformly — this is not per-class patching |
| **A-05** | **Exception hierarchy** — a common `PekuException` marker | `ConfigException` and `FileException` each extend `RuntimeException` directly. Consumers cannot `catch` framework errors as a class. Cheap now, breaking later |
| **A-06** | **Security model & threat model** | The framework is positioned as security-first and currently has **no** security primitives: no output escaping, no CSRF, no session abstraction, no input validation layer, no cookie/header security defaults, no upload policy, no trusted-host or trusted-proxy configuration, no rate limiting. `D-11`, `D-15`, `D-16` are direct consequences. A written threat model + security baseline is a prerequisite for every HTTP ticket |
| **A-07** | **Routing & dispatch** | Undefined. It determines `Controller`'s real shape, which today is a name-holder. Cannot write controller tickets without it |
| **A-08** | **Performance contract** | "Performance" is the headline claim and is entirely unmeasured. No benchmark harness, no baseline, no comparison target. Design arguments about per-call `openlog()` (`Syslog.php:71-73`), per-write file opens (`Loggers/File.php:50`), and `Collection` copying cannot be settled without numbers |
| **A-09** | **Configuration strategy** | No multi-source layering/merging, no compiled/cached config, no schema validation, no `.env` file support — although `.gitignore:4-7` already reserves `.env`, `.env.*`, `config/.env`, implying it was intended |
| **A-10** | **Versioning & BC policy** | No CHANGELOG, no SemVer statement, no `@api`/`@internal` markers. Version is `0.1.0-dev`. Deciding the BC promise now is free; after the first tagged release it is not |
| **A-11** | **Namespace & module boundaries** | `Helpers` is already a catch-all, and HTTP is split across two trees (`Peku\Helpers\Http` and `Peku\Messages\Http`) on `task_5`. Also produces `D-18` |
| **A-12** | **Target system scope** — see §4 | Determines the epic list itself |

---

## 4. Undefined scope — decisions needed from the project owner

Epic creation is blocked on these. They are not research questions; they are product scope.

| Question | Options | Notes |
|---|---|---|
| What *is* Peku's finished v1.0? | (a) HTTP micro-kernel: request/response, routing, controllers, errors, config, logging — nothing else. (b) Full-stack: + persistence, views, sessions, auth, cache, events, CLI. (c) Component library, no kernel | The README's "lightweight, zero bloat" positioning argues (a); the existing `Controllers/` + planned CLI request argues toward (b) |
| First-class runtimes? | HTTP only / HTTP + CLI / + workers | `RequestType`, `MessageFactory` and issue #5 all assume CLI is in scope, yet `CliRequest` does not exist (`D-14`) |
| Persistence in scope? | Yes / no / adapter-only | Nothing exists. If out of scope, say so in the README so it stops being an implied gap |
| Views / templating in scope? | Yes / no | `HttpResponse` already does content negotiation; response *rendering* is undefined |
| Distribution model? | Packagist single package / monorepo split packages | Affects `.gitattributes`, tagging, CI, and `S-09` |
| Minimum PHP? | 8.3 / 8.4 | Drives `D-01` and the CI matrix |

---

## 5. Defects & edge cases found

Ordered by severity. `[E]` = reproduced by execution, `[S]` = verified statically.

### 5.1 Error handling — `Helpers/Errors/ErrorHandler.php`

| ID | Finding |
|---|---|
| **D-01** `[E]` | **`E_STRICT` is deprecated in PHP 8.4 and is resolved at runtime inside the handler.** In `mapErrorToLogLevel()` (`:119`) and `getErrorTypeName()` (`:153`), `E_STRICT` sits mid-list in the `match` arms. `match` resolves arms left to right, so **any** errno not caught by an earlier arm resolves `E_STRICT` and emits a fresh `E_DEPRECATED`. Reproduced: handling one `E_USER_DEPRECATED` produced two self-inflicted `Constant E_STRICT is deprecated` log entries. This is what blocks adding PHP 8.4 to the CI matrix (`S-07`) |
| **D-02** `[E]` | **`@`-suppressed errors are logged.** `handleError()` never consults `error_reporting()`, so operations deliberately suppressed with `@` are recorded as warnings. The framework's own code does this: `@mkdir` (`Loggers/File.php:72`) and `@unserialize` (`Utils/Data/Values.php:75,117`). Every consumer's `@`-suppressed call also pollutes the log |
| **D-03** `[E]` | **`handleFatal()` re-logs the last non-fatal error at shutdown.** `error_clear_last()` (`:73`) is defeated by `return false` (`:74`), which hands control to PHP's internal handler, which re-populates the last error. Reproduced: a single warning logged twice, once at the call site and once at shutdown |
| **D-04** `[E]` | **Typed static with no default.** `private static Loggeable $logger` (`:31`). If any handler method runs before `initialize()`, PHP throws `Error: Typed static property … must not be accessed before initialization` *from inside the error handler*. Reproduced as an uncaught fatal |
| **D-05** `[S]` | **`initialize()` sets `error_reporting(0)` process-wide** (`:40`) — a library silently overriding the host application's error configuration. There is also no `shutdown()`/`restore()`: `ErrorHandlerTest` calls `restore_error_handler()`, but the registered shutdown function can never be unregistered, so state leaks across tests |

### 5.2 Configuration — `Helpers/Configs/`

| ID | Finding |
|---|---|
| **D-06** `[S]` | `Configurable.php` is the **only** PHP file in the project without `declare(strict_types=1)` — on both `develop` and `task_5` |
| **D-07** `[S]` | `Env::getEnvValue()`'s docblock says "Checks both `$_ENV` and `getenv()`"; the code only calls `getenv()`. Under a `variables_order` without `E`, or php-fpm `clear_env=yes`, behaviour diverges from the documented contract |
| **D-08** `[S]` | **An intentionally-empty string is unrepresentable.** `Values.php:49` — `'string' => $value === '' ? $default : $value`. Setting `APP_PREFIX=""` silently yields the default. Related: malformed numerics fall back silently (`filter_var(… FILTER_NULL_ON_FAILURE) ?? $default`). Both are instances of `A-04` |
| **D-09** `[S]` | **`Env`'s array shape is overloaded and collides with PHP semantics.** Int key = required variable, string key = key-with-default (`Env.php:73-79`). PHP casts numeric-string keys to int, so a config key named `"0"` or `"8080"` silently becomes a "required" entry. Undocumented, and required variables can never be typed — they always come back as `string` |
| **D-10** `[S]` | **`Php` violates the `Config` contract.** `Config` is contractually 2-level (`section` → `key`), but `Php::import()` returns whatever the file returns, arbitrarily nested, with no validation (`Php.php:41`). `Config::get()` then returns an array where the contract promises a scalar. PHPStan cannot see it — `import(): array` |
| **D-11** `[S]` | **`Php::import()` `include`s an arbitrary path** with no realpath check or allowlist (`Php.php:41`). Documented with a `WARNING` comment (`:23`), which is not a decision. For a security-first framework this needs a recorded position (`A-06`) |

### 5.3 Logging — `Helpers/Loggers/`

| ID | Finding |
|---|---|
| **D-12** `[S]` | `File::log()` ignores `file_put_contents()`'s return value (`File.php:50`) → **silent log loss**, which is exactly the failure mode a logger must not have. It also reopens the file on every single write, and there is no rotation or size cap |
| **D-13** `[S]` | `Syslog::log()` calls `openlog()`/`closelog()` per message (`:71-73`). Global-state churn per log line, and `closelog()` affects any other syslog user in the process |
| **D-18** `[S]` | **Class-name collision:** `Peku\Helpers\Loggers\File` and `Peku\Helpers\Files\File` (task_5). Both named `File`, both commonly imported together. Guaranteed `use … as` friction (`A-11`) |

### 5.4 Contracts

| ID | Finding |
|---|---|
| **D-17** `[S]` | **Two overlapping key-value contracts.** `Abstractions\Retrievable::get(string $key, mixed $default)` vs `Configs\Configurable::get(string $section, string $key, mixed $default)`. `Config` predates `Retrievable` and does not implement it. Reconcile before more consumers appear |
| **D-19** `[S]` | Tests use PHPUnit **annotations** (`@backupGlobals`, `@runInSeparateProcess` — `ErrorHandlerTest.php:23,234`, `SyslogTest.php:39,59`). Deprecated in PHPUnit 10, removed in 11. Migrating to attributes is a prerequisite for any PHPUnit upgrade, and `S-03` (no lockfile) means that upgrade can arrive unannounced |
| **D-20** `[S]` | `ErrorHandlerTest` extends `PHPUnit\Framework\TestCase` directly instead of `Peku\Tests\Fixtures\TestCase`. Base-class use is inconsistent across the suite |

### 5.5 On the unmerged `task_5` branch

These are pre-merge review findings, not shipped defects — but they must be resolved as
part of `S-01`.

| ID | Finding |
|---|---|
| **D-14** `[S]` | **`MessageFactory::createRequest()` fatals under the CLI SAPI.** It instantiates `Peku\Messages\Cli\CliRequest` (`MessageFactory.php:16,66`); no `Cli` namespace exists anywhere on the branch (`git ls-tree -r origin/task_5-RR \| grep -i cli` → empty) |
| **D-15** `[S]` | **Host-header poisoning.** `HttpRequest::getHost()` returns the raw `Host` header with no trusted-host allowlist (`HttpRequest.php:243`), and `getUrl()` is built from it. Classic cache-poisoning / password-reset-link forgery vector, in a framework whose headline is security |
| **D-16** `[S]` | **Trusted-proxy model is a global boolean.** `HttpRequest::$trustProxies` (`:31,65`) is all-or-nothing and process-global. No trusted-proxy CIDR list, no RFC 7239 `Forwarded` support, no hop counting. With it on, `X-Forwarded-For` / `CF-Connecting-IP` / `X-Real-IP` are trusted from *any* peer, so `getRemoteIp()` is spoofable — which matters because that value will end up in rate limiting, audit logs, and access control |
| **D-21** `[S]` | `MessageFactory::$defaultMappings` maps 17 MIME types to the same `HttpResponse::class` with `// Future:` comments. Placeholder mapping table masquerading as content negotiation — decide whether the table is the design or a stub |

---

## 6. Actionable task list

Ordering is dependency-driven: **P0 unblocks everything, P1 unblocks ticketing, P2 is the
first real backlog.**

### P0 — Setup (blocking, ~1–2 days)

| Task | Covers | Done when |
|---|---|---|
| **T-01** Add `phpunit.xml.dist` to VCS with `Unit` + `Integration` testsuites; remove `phpunit.xml` from `.gitignore` (keep ignoring the local override only) | `S-02` | Fresh clone → `composer test`, `test:unit`, `test:integration` all run |
| **T-02** Commit `composer.lock`; add a scheduled CI job that runs with `--prefer-lowest` and latest to catch drift | `S-03`, `D-19` | CI reproducible; toolchain drift surfaces on a schedule, not in a PR |
| **T-03** Align quality scripts: `lint` → `phpcs` (ruleset-driven, covers `tests/`), `analyse` → `phpstan analyse` (config-driven), add `tests` to `phpstan.neon` paths | `S-04`, `S-05` | One source of truth per tool; `tests/` covered by both |
| **T-04** Revisit the blanket `#no value type specified in iterable type array#` ignore — scope it per-file or fix the array shapes | `S-05` | Level 8 means level 8 |
| **T-05** CI: add `push` triggers for `develop`/`main`, PRs into `main`, and a weekly scheduled run | `S-06` | `main` cannot receive an unbuilt merge |
| **T-06** CI: add PHP 8.4 to the matrix (**depends on T-12**) | `S-07`, `D-01` | Green on 8.3 and 8.4 |
| **T-07** CI: add a coverage step with a floor; publish the clover report | `S-13` | Coverage regressions fail the build |
| **T-08** Repo hygiene: `.gitattributes` (`export-ignore` for `tests/`, `docs/`, CI, phpcs/phpstan configs), `.editorconfig` (tabs), `SECURITY.md`, `CONTRIBUTING.md` (incl. branch-naming: no `/` in the task segment), `CHANGELOG.md`, issue + PR templates, CODEOWNERS | `S-12`, `S-14` | Dist package ships source only; conventions are enforced, not folklore |
| **T-09** Documentation truth pass: README structure + PHP version, `tests/README.md` layout, remove or gate the Packagist install line | `S-08`, `S-09`, `S-10`, `S-11` | Docs match `find src tests` |

### P1 — Decisions & review (blocking ticketing)

| Task | Covers | Output |
|---|---|---|
| **T-10** **Review and land or park `task_5-Request/Response_Founda`** — 22 files, ~7.2k lines, three new namespaces. Blocked on `T-11`/`T-13`, and must resolve `D-14`, `D-15`, `D-16`, `D-21` | `S-01` | A single agreed baseline to write tickets against |
| **T-11** ADR: PSR interop stance | `A-01` | `docs/adr/0001-psr-interop.md` |
| **T-12** ADR + fix: error-handler semantics — drop `E_STRICT`, honour `error_reporting()`, fix double-log, give `$logger` a safe default, add `restore()`, stop forcing `error_reporting(0)` | `A-04`, `D-01`–`D-05` | Handler correct on 8.3 **and** 8.4; unblocks `T-06` |
| **T-13** ADR: composition root, DI, and global mutable state | `A-02`, `A-03` | Decides whether `MessageFactory`/`trustProxies` statics survive review |
| **T-14** ADR: exception hierarchy (`PekuException` marker) + contract reconciliation (`Retrievable` vs `Configurable`) | `A-05`, `D-17` | Applied before more contracts land |
| **T-15** **Threat model + security baseline document** — trusted hosts, trusted proxies, escaping, CSRF, sessions, cookie/header defaults, upload policy, config-file execution (`D-11`) | `A-06`, `D-11`, `D-15`, `D-16` | The gate for every HTTP ticket |
| **T-16** ADR: versioning, BC promise, `@api`/`@internal`, release process | `A-10` | Signed before v0.2.0 |
| **T-17** **Scope decision session** — answer §4 | `A-12` | The epic list itself |

### P2 — Research spikes (timeboxed, parallel with P1)

| Task | Question | Deliverable |
|---|---|---|
| **R-01** | Benchmark harness + baseline numbers for what exists (logging, config load, request construction), and comparison targets | Reproducible harness in `tests/Benchmark/` or a separate tool; a numbers table. Prerequisite for `A-08` and for defending "performance" in the README |
| **R-02** | Cost of PSR-7/PSR-15 compatibility: full adoption vs. bridge adapters vs. none | Input to `T-11` — must include what a bridge costs at runtime, given `A-08` |
| **R-03** | Runtime survey: does Peku target FrankenPHP / RoadRunner / Swoole? What does each demand of global state? | Input to `T-13` |
| **R-04** | Routing/dispatch approaches at Peku's weight class; what `Controller` must expose | Input to `A-07`; unblocks controller epics |
| **R-05** | Config strategy: layering, compiled/cached config, `.env` support, schema validation | Input to `A-09`; reconciles `.gitignore`'s `.env` reservations with reality |
| **R-06** | Logging: PSR-3 conformance cost, buffered/persistent handles, rotation, context interpolation | Inputs to `T-11` and the fixes behind `D-12`/`D-13` |
| **R-07** | Packaging: single package vs. split components; Packagist publication mechanics | Input to `S-09`, `T-08`, `T-16` |

### P3 — Fix backlog (write as tickets once P1 lands)

`D-06` strict_types · `D-07` Env docblock/behaviour · `D-08` empty-string & silent-cast policy ·
`D-09` Env array-shape overload · `D-10` `Php` contract violation · `D-12` logger write
failures + rotation · `D-13` syslog handle churn · `D-18` `File`/`File` collision ·
`D-19` PHPUnit attributes migration · `D-20` test base-class consistency.

Each is small, but several are instances of `A-04` and should be fixed as one policy
change rather than ten patches.

---

## 7. Proposed epic map

Valid only once §4 is answered; offered as a starting shape.

| Epic | Contains | Gated by |
|---|---|---|
| **E0 — Project Foundations** | T-01 … T-09 | nothing (start now) |
| **E1 — Architecture Baseline** | T-11, T-13, T-14, T-16, T-17, R-01…R-07 | E0 |
| **E2 — Error & Diagnostics Hardening** | T-12, D-01…D-05, D-12, D-13 | E1 (A-04) |
| **E3 — Security Baseline** | T-15, D-11, D-15, D-16 | E1 |
| **E4 — HTTP Messages** | T-10, issue #5, D-14, D-21 | E1, E3 |
| **E5 — Configuration** | D-07…D-10, R-05 | E1 |
| **E6 — Routing & Dispatch** | R-04, `Controller` redesign | E1, E4 |
| **E7 — Release Engineering** | T-16, R-07, Packagist, tagging | E0, E1 |

**Critical path:** `E0 → T-11/T-13 → T-15 → T-10` (land `task_5`) → everything else.

---

## Appendix A — Reproductions

All run on PHP 8.4.19 (CLI) against the tracked source.

### A-1 `D-01` — `E_STRICT` resolved at runtime inside the handler

```php
namespace Foo;
function f(int $x): string { return match($x) { E_WARNING, E_STRICT => "w", default => "o" }; }
f(2);     // matches E_WARNING first — no deprecation
f(2048);  // reaches E_STRICT   — "Deprecated: Constant E_STRICT is deprecated"
```

`ErrorHandler::mapErrorToLogLevel()` places `E_STRICT` after only six errnos, so
`E_DEPRECATED`, `E_USER_DEPRECATED`, every error arm, and `default` all resolve it.

### A-2 `D-01`, `D-02`, `D-03` — handler noise, live

Installing `ErrorHandler` with a counting logger and triggering **one**
`E_USER_DEPRECATED` produced four log entries:

```
LOG#1[warning] User Deprecated [16384]: legacy call in recur.php:23
--- end, count=1 ---
LOG#2[warning] Deprecated [8192]: Constant E_STRICT is deprecated in ErrorHandler.php:153
LOG#3[warning] Deprecated [8192]: Constant E_STRICT is deprecated in ErrorHandler.php:119
LOG#4[warning] User Deprecated [16384]: legacy call in recur.php:23
```

`#2`/`#3` are self-inflicted (`D-01`). `#4` is `handleFatal()` re-logging at shutdown
(`D-03`). A separate run showed `@file_get_contents('/definitely/not/here')` logged in
full (`D-02`).

### A-3 `D-04` — uninitialised typed static

```
PHP Fatal error: Uncaught Error: Typed static property
Peku\Helpers\Errors\ErrorHandler::$logger must not be accessed before initialization
in src/Helpers/Errors/ErrorHandler.php:72
```

### A-4 `D-14` — `CliRequest` does not exist

```console
$ git ls-tree -r --name-only origin/task_5-Request/Response_Founda | grep -i cli
$ # empty — but MessageFactory.php:16 imports Peku\Messages\Cli\CliRequest
```
