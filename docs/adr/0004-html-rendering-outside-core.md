# ADR-0004: HTML rendering lives outside the core

* **Status:** Proposed — needs sign-off
* **Date:** 2026-09-02
* **Deciders:** Patricio Rossi

## Context

The question raised was whether HTML generation/rendering should remain in the core or be
extracted into a standalone `peku/html` package, to be assessed after the Request/Response
work lands.

**Finding: there is no HTML generation or rendering code in the repository.** Searched
`develop` and `task_5-Request/Response_Founda` for templating, escaping and DOM
construction — nothing. `HttpResponse::processContent()` is:

```php
protected function processContent(): string {
    $this->sendHeaders();
    return (string) $this->content;
}
```

A cast. `validate()` requires content to be `string|Stringable` and nothing else. There is
no `htmlspecialchars` call anywhere in `src/`.

So this is not an extraction decision. It is a **boundary decision taken before the first
line is written**, which is the cheap moment to take it. The realistic failure mode is not a
large HTML subsystem accreting in core; it is one convenience method — an
`HttpResponse::html()` that escapes a string — after which the core owns an escaping policy,
a charset assumption, and a context-sensitivity problem (HTML body vs attribute vs URL vs
JavaScript context each need different escaping), permanently.

## Decision

HTML generation, templating, escaping and view composition will **not** live in the core.
When needed they go in a separate `peku/html` package that depends on the core, never the
reverse.

The boundary test, applicable to any future component:

> Does the CLI entry point need this to function?
> If no, it is not core.

HTML fails that test. Config, logging, error handling, files, collections and database
access pass it.

The core's HTTP layer is responsible for **transport**: status, headers, and a body it
treats as an opaque string. It is not responsible for producing that string.

## Consequences

* `HttpResponse` keeps its `string|Stringable` contract. That contract is now a deliberate boundary rather than an implementation detail — do not add `html()`, `view()` or `render()` convenience methods to it.
* Escaping policy, charset handling and context-aware encoding belong to `peku/html`, where they can be designed properly instead of being implied by a helper.
* Anything rendering HTML pulls a second package. That is the intended cost.
* The core stays honest about the "lightweight" claim: it ships transport, not presentation.
* A `Stringable` view object from `peku/html` drops into `HttpResponse` with no adapter, so the boundary costs nothing at the call site.

## Alternatives considered

**Keep a minimal escaping helper in core "just for safety".** Rejected, and this is the one
worth arguing. A single `escape()` in core looks harmless, but it establishes that the core
has an opinion on output encoding while covering only the HTML-body case. Callers then reach
for it in attribute, URL and JavaScript contexts, where it is wrong. A security-oriented
framework should offer context-aware escaping properly, in the package that owns rendering,
or offer none at all. Half a policy is worse than none, because it gets trusted.

**Decide after the HTTP layer ships.** Rejected: by then `HttpResponse` will likely have
grown the convenience method, and the decision becomes an extraction with callers to
migrate. The assessment was requested for post-deployment, but since nothing exists yet it
can be settled now at zero cost.
