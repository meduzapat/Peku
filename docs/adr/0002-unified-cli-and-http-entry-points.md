# ADR-0002: Unified architecture with CLI and HTTP entry points

* **Status:** Accepted
* **Date:** 2026-09-02
* **Deciders:** Patricio Rossi

## Context

An earlier direction scoped the framework to CLI only, deferring HTTP entirely. That was
reconsidered: both entry points are wanted, and the framework should serve them from one
architecture rather than shipping two.

Work already exists for the HTTP side (`Peku\Messages\Http`, `Peku\Helpers\Http`) on the
`task_5-Request/Response_Founda` branch. The CLI side does not exist —
`MessageFactory::createRequest()` dispatches to a `Peku\Messages\Cli\CliRequest` that was
never written.

The risk in serving two entry points from one architecture is well known and already visible
in the codebase: assumptions from the dominant entry point leak into the shared layer. See
ADR-0003, which addresses the specific instance.

## Decision

We will retain both HTTP and CLI entry points within a single architecture.

Each entry point owns its own delivery layer — **HTTP Controllers** and **CLI
Commands/Handlers** — and both consume shared core services.

The layering is:

```
  HTTP entry point                     CLI entry point
  ├─ HttpRequest / HttpResponse        ├─ Input / Output
  └─ Controller                        └─ Command
                    ↓             ↓
              shared core services
     config · logging · errors · files · collections · db
```

The dependency rule is one-directional and absolute: **delivery layers depend on core
services; core services never depend on a delivery layer.** A core service that needs to
know whether it is running under HTTP or CLI is misdesigned.

## Consequences

* One codebase, one set of services, two thin delivery layers. Business logic is written once.
* The CLI delivery layer must actually be built — it is currently an unimplemented import.
* Every core service must be testable with no request lifecycle, no superglobals, no output buffering, and no headers. This is a constraint on the core, and a useful one.
* Shared *services* must not be confused with shared *I/O contracts*. See ADR-0003.
* `Peku\Controllers\Controller` currently holds a name and nothing else, and its docblock claims responsibilities it does not have. It needs to become a real HTTP delivery-layer base class, with a CLI `Command` as its sibling rather than its subclass.

## Alternatives considered

**CLI-only first, HTTP later.** Rejected by the project owner. Its genuine merit was that a
kernel proven without a request lifecycle makes the later HTTP layer a thin adapter, and
prevents HTTP assumptions leaking into the core. That merit is preserved here by ADR-0003's
boundary rule rather than by sequencing.

**Two separate frameworks sharing a vendor directory.** Rejected: duplicates config,
logging, and error handling, which is most of what the core is.

**One unified Request/Response abstraction covering both.** Rejected — see ADR-0003.
