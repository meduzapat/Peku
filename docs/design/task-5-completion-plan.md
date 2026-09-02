# Task 5 — Completion Plan (Request/Response Foundation)

Work plan for finishing `task_5-Request/Response_Founda` and landing it on `develop`.
Scope: 20 new source files (~3,100 lines) plus 9 test files (~4,100 lines), unmerged
since the branch was opened.

---

## 1. Two blockers to clear first

**B-1 — The work cannot be validated as things stand.** `phpunit.xml` is absent from version
control and gitignored (`.gitignore:182`), so `composer test` has no suite definition on a
clean clone, and the `test:unit` / `test:integration` scripts reference testsuites that exist
nowhere in the repository. Task 5 ships ~4,100 lines of new tests that currently cannot be
run by anyone who clones the repo. Fixing 7,200 lines of HTTP code without a runnable suite
and merging it to `develop` is not a defensible sequence.

*Clear by:* committing `phpunit.xml.dist` with `Unit` and `Integration` testsuites, and
committing `composer.lock`. Roughly an hour, and it unblocks every subsequent step.

**B-2 — I could not run the toolchain in this environment.** `composer install` fails here
against the GitHub API (authentication/rate limiting through the sandbox proxy), across
`--prefer-dist` and `--prefer-source`. PHPUnit, PHPStan and PHPCS were therefore never
executed against this branch by me. Every finding below is from reading the source or from
executing individual classes directly against PHP 8.4 — none depends on a suite run, but
none is a substitute for one either.

*Consequence:* the fixes below need running on a machine where the toolchain installs.

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

1. Clear **B-1** — `phpunit.xml.dist` + `composer.lock` committed. *Nothing else is verifiable until this is done.*
2. Sign off or reject **ADR-0003**.
3. Fix `ErrorHandler` (PHP 8.4 + `error_reporting`) and add 8.4 to the CI matrix — it is a prerequisite for the header finding and it makes every subsequent test run trustworthy.
4. Apply the **H** fixes, then **M**.
5. Run `composer test`, `analyse`, `lint` green on 8.3 and 8.4.
6. Merge to `develop`.

## 5. On merging directly to `develop`

Two notes, both practical rather than procedural:

* This session is restricted to pushing to `claude/peku-research-prerequisites-j67igz`. I have not pushed anything to `develop` and will not without explicit instruction. The documents in `docs/` are on that branch and can be merged or cherry-picked at will.
* Independently of who does it: `develop` is the CI-gated branch, and the CI workflow only triggers on `pull_request` → `develop` (`ci.yml:3-6`). A direct push therefore runs **no** checks at all. For ~7,200 lines including three security-relevant fixes, that is the one place a PR is worth the ceremony — not for review theatre, but because it is currently the only way any test runs.
