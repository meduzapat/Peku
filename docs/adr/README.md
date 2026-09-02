# Architecture Decision Records

Structural decisions for Peku, with the reasoning that produced them.

## Why

Requirements are fluid during the design phase. ADRs make changing our mind *cheap*: the
original reasoning is preserved, so a reversal is an informed choice rather than a
rediscovery. Without them the same questions — PSR adoption, package boundaries, exit-code
policy — get re-litigated every few weeks.

## Rules

1. One decision per record. Numbered sequentially, never renumbered.
2. Records are **immutable once Accepted.** To change a decision, write a new ADR and mark
   the old one `Superseded by ADR-NNNN`. Do not edit history.
3. Any PR that changes a public contract adds or amends an ADR.
4. `Proposed` means drafted and awaiting sign-off. It is not in force.

## Index

| # | Title | Status |
|---|---|---|
| [0001](0001-record-architecture-decisions.md) | Record architecture decisions | Accepted |
| [0002](0002-unified-cli-and-http-entry-points.md) | Unified architecture with CLI and HTTP entry points | Accepted |
| [0003](0003-entry-point-io-contracts.md) | Entry-point I/O contracts are not shared | Proposed |
| [0004](0004-html-rendering-outside-core.md) | HTML rendering lives outside the core | Proposed |
| [0005](0005-database-package-topology.md) | Database package topology | Proposed |
