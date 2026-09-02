# ADR-0003: Entry-point I/O contracts are not shared

* **Status:** Proposed — needs sign-off
* **Date:** 2026-09-02
* **Deciders:** Patricio Rossi

## Context

ADR-0002 commits to shared core services behind two delivery layers. This record answers the
question that decision leaves open: **what exactly is shared?**

The existing `Peku\Messages` layer answers "the Request and Response abstractions
themselves". That answer is already producing contracts that describe two unrelated things:

```php
// Responseable::setCode()
// "HTTP: Status code (200, 404, 500) / CLI: Exit code (0=success, 1+=error)"

// Responseable::getCodeMessage()
// "HTTP: 'OK', 'Not Found' / CLI: 'Success', 'Error'"
```

These are not one concept with two renderings:

| | HTTP status code | Process exit code |
|---|---|---|
| Range | 3-digit registry, 100–599 | 0–255; the OS truncates modulo 256 |
| Semantics | classes 1xx–5xx, registered meanings | 0 = success, non-zero = failure; `sysexits.h` is convention only |
| Reason phrase | yes, part of the protocol | none exist |
| Per execution | many possible (redirects, retries) | exactly one, at process end |

The same divergence runs through the rest of the contract. `wants()` returns a negotiated
MIME type, which has no CLI meaning (a CLI has an output *format* flag, chosen by the user,
not negotiated). HTTP has headers; a CLI has **stdout/stderr separation**, a load-bearing
concept with no HTTP analogue. HTTP sends a response once; a CLI streams output
incrementally while it works.

`MessageFactory::createRequest()` — `php_sapi_name() === 'cli' ? new CliRequest() : new
HttpRequest()` — exists only to serve the unified abstraction. `CliRequest` was never
written, which is the design telling us something.

## Decision

We will **not** share Request/Response abstractions between entry points. Sharing happens
strictly below them, at the service layer.

| Layer | HTTP | CLI | Shared? |
|---|---|---|---|
| Transport I/O | `HttpRequest` / `HttpResponse` | `Input` / `Output` | **No** |
| Delivery | `Controller` | `Command` | **No** |
| Application / domain services | — | — | **Yes** |
| Core services: config, logging, errors, files, collections, db | — | — | **Yes** |

Concretely:

* `Peku\Messages\Requestable` and `Responseable` become HTTP contracts and move under the HTTP namespace. They stop claiming CLI semantics in their docblocks.
* The CLI gets `Input`, `Output`, and `Command::execute(Input, Output): int` — returning an exit code, never calling `exit()`.
* `MessageFactory`'s SAPI dispatch is removed. Each entry point has its own bootstrap; the process already knows which one it is.
* Mapping an application outcome to an HTTP status or an exit code is the **delivery layer's** job, done once per entry point.

## Consequences

* Two small I/O contracts instead of one contract that fits neither. Each is honest about its medium.
* The `CliRequest` gap disappears rather than needing to be filled.
* Some duplication between delivery layers is accepted deliberately. Two thin honest adapters beat one abstraction that lies to both callers.
* Core services stay free of `php_sapi_name()` checks — which is the property that keeps them testable.
* Work already written against `Responseable`'s dual semantics needs adjusting. This is cheapest now, before either layer ships.

## Alternatives considered

**Keep the unified `Requestable`/`Responseable`.** Rejected: the contract has to be
documented in terms of "if HTTP … if CLI …", which is the definition of a leaky
abstraction. Every future implementer must read both branches to implement either.

**Share a semantic outcome instead of a representation.** An enum such as
`Outcome::{Success, NotFound, Invalid, Denied, Error}` that each delivery layer maps to
either an HTTP status or an exit code. This is a genuinely good idea and the *only* form of
sharing here that works, because it shares meaning rather than encoding. It is not adopted
now because there is no application layer yet to produce such outcomes — adopting it today
would repeat the mistake this ADR corrects, abstracting ahead of a consumer. **Revisit once
the first real application service exists**, as its own ADR.

**Share via a common marker interface with no methods.** Rejected: buys nothing, costs a
concept.
