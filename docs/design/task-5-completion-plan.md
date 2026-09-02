# Task 5 — Completion Plan (Request/Response Foundation)

Work plan for finishing `task_5-Request/Response_Founda` and landing it on `develop`.
Scope: 20 new source files (~3,100 lines) plus 9 test files (~4,100 lines), unmerged
since the branch was opened.

---

## 1. Blockers — now measured

### B-1 — no test suite was runnable. **Cleared by this branch.**

`phpunit.xml` is gitignored (`.gitignore:182`) and was absent from version control, so
`composer test` — bare `phpunit` — had no suite to run. Verified, not inferred:

```console
$ php vendor/bin/phpunit            # exactly what `composer test` runs
… usage dump …
EXIT=1

$ php vendor/bin/phpunit --testsuite=Unit
EXIT=1
```

This branch adds `phpunit.xml.dist` (committed) plus `tests/Integration/` so the
`Integration` suite has a directory to point at. `phpunit.xml` stays gitignored — that is
correct for the `.dist` convention: the committed file is the default, a local `phpunit.xml`
overrides it per developer.

### B-2 — the suite runs now, and `develop` is not green on PHP 8.4

With the config in place, on `origin/develop` at `226581f`, PHP 8.4.19:

| | Tests | Result |
|---|---|---|
| Everything except the two classes below | 145 | **OK — 228 assertions, all passing** |
| `Helpers\Errors\ErrorHandlerTest` | 35 | 4 errors, 16 failures, 12 risky |
| `Helpers\Loggers\FileTest` | 14 | 2 failures — **environment artifact, not a bug** |
| **Total** | **194** | **4 errors, 18 failures, 12 risky** |

**`ErrorHandlerTest` — real, and it is the `E_STRICT` defect.** The failures name the
mechanism directly:

```
Failed asserting that 'Deprecated [8192]: Constant E_STRICT is deprecated in
ErrorHandler.php:119' contains "parse error"
```

The handler resolves `E_STRICT` mid-`match` on PHP 8.4, which emits an `E_DEPRECATED`, which
the handler then logs — displacing the message the test expected. The 4 errors are the
companion defect: `MockDelegateFunction::delegate() was not expected to be called more than
once`, i.e. `handleFatal()` re-logging at shutdown.

These almost certainly pass on PHP 8.3, where `E_STRICT` is not deprecated. *(Inferred — only
8.4 was available here.)* That is exactly why a CI matrix pinned to 8.3 hides them.

**`FileTest` — not a bug.** `testThrowsExceptionForNonWritableFile` and
`…NonWritableDirectory` fail because this sandbox runs as uid 0, and root bypasses permission
bits — verified: `is_writable()` returns `true` on a `0444` file as root, and the write
succeeds. On CI (`ubuntu-latest`, non-root) these pass. Worth a `markTestSkipped()` guard on
`posix_getuid() === 0` so the suite is honest wherever it runs.

### B-3 — static analysis and style are unverified, and they are CI gates

`phpstan/phpstan` distributes as a phar with no clonable source and this sandbox cannot
authenticate to the GitHub API, so `composer analyse` was never run here. Every other dev
dependency installs fine over git once `use-github-api` is disabled. **This is a sandbox
limitation, not a CI one** — GitHub Actions has normal API access and will install phpstan
without trouble.

That matters, because the workflow runs three unconditional steps:

```yaml
- run: composer test        # phpunit
- run: composer analyse     # phpstan analyse src/ --level=8
- run: composer lint        # phpcs src/
```

Tooling priority is currently phpunit only, and the lock file stays ignored until things
stabilise — both deliberate. The consequence to plan around: `analyse` and `lint` still run
on every PR as hard gates, over 3,100 new lines of source, and **nobody has yet established
that either passes**. If they fail on the Task 5 PR for reasons that are not a priority yet,
the PR goes red on noise — and a gate that is red for reasons nobody acts on is a gate people
learn to ignore, which is the one failure mode worth avoiding while the PR is the only thing
running tests at all.

Two cheap ways out, either fine: run `composer analyse` and `composer lint` locally once to
find out where they stand, or mark those two steps `continue-on-error: true` until they are a
priority. What should not happen is discovering the answer for the first time on the PR that
lands 20 files.

## 2. Prerequisite decision — do this before touching the code

**[ADR-0003](../adr/0003-entry-point-io-contracts.md) must be signed off first.** It proposes
that Request/Response contracts are *not* shared between HTTP and CLI. If accepted, three
things on this branch change shape:

| Change | Effect on the branch |
|---|---|
| `Requestable` / `Responseable` become HTTP contracts, moved under the HTTP namespace | namespace move + docblock rewrite |
| `MessageFactory`'s `php_sapi_name()` dispatch is removed | deletes the `CliRequest` problem instead of solving it |
| `Responseable::setCode()` stops documenting dual HTTP/CLI meaning | docblock + contract narrowing |

Doing the fix work before this decision means doing parts of it twice.

## 2b. Verified state of the branch at `094f550`

Run here on PHP 8.4.19, as root, with `phpunit.xml.dist`:

```
Tests: 544, Assertions: 876, Errors: 4, Failures: 32, Risky: 12
```

544 matches the count from Pat's PHP 8.3 run, which is fully green. The entire delta is
explained by two causes, neither of which is new work on this branch:

| Cause | Count | Real? |
|---|---|---|
| `ErrorHandlerTest` on PHP 8.4 — the `E_STRICT` defect | 4 errors, 16 failures, 12 risky | **Yes.** Pre-existing on `develop` |
| Permission tests running as uid 0 | 16 failures | **No** — environment |

The 16 permission failures are `Helpers\Files\FileTest` (13), `Loggers\FileTest` (2) and
`UploadedFileTest::testMoveToThrowsOnMkdirFailure` (1). Root bypasses permission bits, so the
expected `FileException` never fires. Verified directly: `is_writable()` returns `true` on a
`0444` file as uid 0, and the write succeeds.

They pass on GitHub Actions (`ubuntu-latest` runs as non-root) and on Pat's machine. They
would fail in any root container, which is a common CI shape. A guard makes them honest
wherever they run:

```php
if (\posix_getuid() === 0) {
    $this->markTestSkipped('Permission checks are meaningless as root');
}
```

### The bug the suite does not catch

`MessageFactory::createResponse()` **throws a `TypeError` on every call.** Verified by
execution:

```
TypeError: Peku\Messages\Http\HttpResponse::__construct(): Argument #3 ($headers)
must be of type array, string given, called in .../MessageFactory.php on line 92
```

`MessageFactory.php:92` passes the negotiated MIME string into a parameter declared
`array $headers`. `grep -rl MessageFactory tests/` returns nothing — **the class that wires
request to response has no tests at all**, which is exactly why 544 green tests say nothing
about it.

The fix is not mechanical, because it exposes a contract problem. `createResponse()` is typed
against `Responseable`, but what it needs to do — set a Content-Type — is an HTTP concept that
`Responseable` does not carry, since it is meant to serve CLI too. Two options:

| | Change | Trade-off |
|---|---|---|
| **A** | `new $class($content, $code, ['Content-Type' => $mime])` | Type-safe today, no contract change. Loses the `; charset=utf-8` that `setContentType()` adds — and charset omission has real security consequences |
| **B** | Construct, then `$response->setContentType($mime)` | Preserves charset and uses the existing API. Requires `setContentType()` on the contract, or an `instanceof` check that the "no defensive checks" rule would reject |

**B is the honest fix, and it points at [ADR-0003](../adr/0003-entry-point-io-contracts.md):**
if `Responseable` were simply the HTTP response contract rather than a shared HTTP/CLI one,
`setContentType()` would live on it and this would not be a decision at all. The `TypeError`
is the first concrete cost of the shared abstraction, not a typo.

## 3. Fix list

Severity: **H** blocks merge · **M** should land with it · **L** follow-up ticket.

| | Finding | Location | Fix |
|---|---|---|---|
| **H** | `MessageFactory::createRequest()` instantiates `Peku\Messages\Cli\CliRequest`, which does not exist anywhere on the branch — fatal error under the CLI SAPI | `MessageFactory.php:16,66` | Per ADR-0003, remove the SAPI dispatch entirely. Each entry point bootstraps itself |
| **H** | `getHost()` returns the raw `Host` header with no trusted-host allowlist, and `getUrl()` is built from it — cache poisoning and forged absolute links | `HttpRequest.php:243` | Trusted-host allowlist from config; reject or fall back when the header does not match |
| **H** | `trustProxies` is a process-global boolean. With it on, `X-Forwarded-For` / `CF-Connecting-IP` / `X-Real-IP` are trusted from *any* peer, so `getRemoteIp()` is spoofable — and that value lands in rate limiting, audit logs and access control | `HttpRequest.php:31,65,372,396` | Replace with a trusted-proxy CIDR list, per-instance not static. Consider RFC 7239 `Forwarded`. Default closed |
| **M** | Header values are written with `header("$name: $value")`. PHP itself blocks CRLF injection (verified: warning, not exception) so this is **not** a vulnerability — but `ErrorHandler` sets `error_reporting(0)`, so the warning is swallowed and the header is **silently dropped** | `HttpResponse.php:259-262` | Validate header names/values and throw. Do not rely on a warning nobody sees |
| **M** | `Request::setDefaultExtractor()` is a static setter on an abstract class — a fourth instance of process-global mutable state, alongside `ErrorHandler::$logger`, `MessageFactory::$customMappings` and `trustProxies` | `Request.php:86` | Constructor injection. Statics here make tests order-dependent |
| **M** | 17 MIME types map to the same `HttpResponse::class` behind `// Future:` comments — a placeholder table presented as content negotiation | `MessageFactory.php:33-49` | Either implement the specialised responses or reduce the table to what actually differs. A map where every value is identical is a `switch` that has not been written |
| **L** | `Peku\Helpers\Loggers\File` and `Peku\Helpers\Files\File` — same class name, both commonly imported together | both | Rename one. `LogFile`, or move the logger to `Peku\Log` |
| **L** | `File::open()` has `// TODO: Detect MIME and return specialized types` | `Helpers/Files/File.php:127` | Ticket it or delete the comment |
| **L** | `HttpResponse` content is `string\|Stringable` only | `HttpResponse.php:239` | Correct, and now deliberate — see [ADR-0004](../adr/0004-html-rendering-outside-core.md). Do not "fix" by adding `html()` |

Carried over from the earlier baseline and still open on this branch: `Configurable.php`
remains the only file without `declare(strict_types=1)`, and the `ErrorHandler` PHP 8.4
defects (`E_STRICT` deprecation resolved inside the handler, `@`-suppressed errors logged,
shutdown double-logging, uninitialised typed static) are unfixed. The `ErrorHandler` ones
interact directly with the **M** header finding above.

## 4. Suggested sequence

1. ~~Clear **B-1**~~ — done on this branch. Still outstanding: commit `composer.lock`, generated somewhere with working GitHub access (B-3).
2. **Fix `ErrorHandler` first.** It is not a prerequisite on paper any more — it is 20 failing tests on PHP 8.4 in code already merged to `develop`. Fix `E_STRICT`, honour `error_reporting()`, stop the shutdown double-log, give `$logger` a default, then add 8.4 to the CI matrix.
3. Sign off or reject **ADR-0003**.
4. Apply the **H** fixes, then **M**.
5. Run `composer test`, `analyse`, `lint` green on 8.3 and 8.4.
6. Merge to `develop`.

## 5. How this lands

Project workflow: branches are cut from `develop`, work returns via a pull request into
`develop`, and Pat approves after code review. Nothing is pushed to `develop` directly.

This matters more than process hygiene here: `ci.yml` triggers on `pull_request` → `develop`
(`ci.yml:3-6`) and on nothing else. The PR *is* the only event that runs tests, static
analysis and the style check. For a change of this size — 20 source files including three
security-relevant fixes — that gate is the whole safety net.

Two notes on mechanics:

* Branch names must not contain `/` in the task segment. The existing `task_5-Request/Response_Founda` permanently blocks any branch named `task_5-Request` from existing, and truncates awkwardly in every tool that shortens refs. Name the follow-up branch `task_5-request-response` or similar.
* Because §2 may move `Requestable`/`Responseable` between namespaces, do the ADR-0003 refactor as its own commit within the PR rather than mixing it into the security fixes. It keeps the security review readable, which is the part that most needs reviewing.
