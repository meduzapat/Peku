# ADR-0001: Record architecture decisions

* **Status:** Accepted
* **Date:** 2026-09-02
* **Deciders:** Patricio Rossi

## Context

Peku is in an active design phase. Requirements, package boundaries, and feature sets are
explicitly fluid, and several structural questions are open at once: package topology, PSR
adoption, entry-point layering, database abstraction shape.

Decisions taken in conversation leave no artifact. Three months on, the reasoning is gone
and only the code remains — at which point a decision cannot be distinguished from an
accident, and reversing one means reconstructing the argument from scratch.

## Decision

We will record structural decisions as Architecture Decision Records in `docs/adr/`,
numbered sequentially, using the Nygard format captured in `0000-template.md`.

An ADR is required for: package boundaries and dependencies, public contracts and their BC
status, third-party or PSR adoption, entry-point and lifecycle design, and cross-cutting
policy (error semantics, exit codes, versioning).

An ADR is *not* required for implementation detail contained within a single class.

## Consequences

* Reversing a decision becomes cheap and honest: a new ADR superseding the old one, with the original context intact.
* Every structural PR carries a small documentation cost.
* Records are immutable once Accepted. Changes are made by superseding, never by editing — an edited ADR is worse than none, because it looks authoritative while hiding that the reasoning changed.
* `Proposed` records are drafts, not policy. Code must not rely on them.

## Alternatives considered

**Design document kept continuously up to date.** Rejected: a living document shows the
current state but erases the reasoning, which is the part that is expensive to reconstruct.
It also decays silently — nothing signals that a section is stale.

**Issue tracker discussion.** Rejected: decisions get buried in comment threads, are not
versioned alongside the code, and are not reviewable in a PR.

**Nothing, until the design settles.** Rejected: the fluid period is exactly when the
decision rate is highest and the reasoning is most worth capturing.
