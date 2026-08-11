# Architecture

Product Finder follows **functional core, imperative shell**: the product-matching and question logic that actually is the product is plain, WordPress-free PHP/JS, and everything that talks to WordPress or WooCommerce sits around it, not inside it.

This document exists because this is the first project where "beyond just me" is a real possibility — the pattern was mostly arrived at by instinct while building (pure logic naturally ended up testable under plain PHPUnit, so it stayed that way), then named and made durable once that became a deliberate goal. If you're a future contributor: this is the one rule the rest of this file explains and the test suite enforces.

## The one rule

**Core modules never call a WordPress or WooCommerce function, class, or global directly.** Not `get_option()`, not `WC_Product`, not `add_action()`, not `$_GET`. They take plain arrays/scalars in, return plain arrays/scalars out.

The one deliberate exception is i18n (`__()`, `_n()`, `_x()`): translation is a presentation concern, not a dependency on WordPress's *behavior* or *data*, so it doesn't threaten the reason this rule exists. `RelaxationExplainer` uses it.

## What's core, what's shell

| Core (pure, WordPress/WooCommerce-free) | Shell (talks to WordPress/WooCommerce) |
|---|---|
| `includes/Engine/MatchEngine.php` | `includes/Query/*` — WC_Product-facing adapters |
| `includes/Finder/RuleBuilder.php` | `includes/Finder/FinderService.php` — orchestrates: pulls WC data, calls the core |
| `includes/Finder/RelaxationExplainer.php` | `includes/Finder/ConfigRepository.php`, `EventCounter.php` — `wp_options` I/O |
| `includes/Finder/QuestionSetResolver.php` | `includes/Admin/*` — admin screen, hooks, capability checks |
| `includes/Finder/AttributeMapResolver.php` | `includes/Templates/TentsTemplate.php` — static config, but WP-touching (`__()` is fine; it also isn't tested as core because it's plain data, not logic) |
| `includes/Attributes/AttributeCompleteness.php` | `src/product-finder/view.js`, `edit.js`, `render.php` — the Interactivity API / block-editor / server-render glue |
| `src/product-finder/matchEngine.js` | |
| `src/product-finder/rules.js` | |
| `src/product-finder/relaxationExplainer.js` | |

The core list above is also, literally, the list each boundary test iterates over — see below.

## Why this instead of a named pattern (DDD / hexagonal / vertical slice)

At ~1,650 lines of PHP and one real feature (find matching products), a formal ports-and-adapters reorg or DDD's tactical patterns (aggregates, entities, domain events) would add indirection this codebase doesn't have a problem to justify yet — WordPress itself is also a poor fit for strict hexagonal, since hooks are inherently global/side-effecting. Functional core / imperative shell gets the actual benefit (business logic that's cheap to test, safe to reason about, and hard to accidentally couple to a WP/WC version) without paying for machinery the project's size doesn't need.

**Revisit this if:** a second genuinely separate feature lands — the premium tier or Finder Insights (see `PRODUCT-FINDER-PROPOSAL.md` §12) are the natural trigger. That's when organizing by feature (vertical slice) starts to pay for itself, because there'd be more than one slice. Reorganizing now, with one feature, would fragment `MatchEngine`/`RuleBuilder` rather than clarify anything.

## Enforcement

The rule is enforced by two tests, not just this document:

- `tests/php/Architecture/CoreBoundaryTest.php` — tokenizes each core PHP file (via `token_get_all()`, so comments/strings can't cause false positives) and fails if it finds a call to `wp_*`/`WC_*`/`WP_*` or a short list of option/hook/capability functions.
- `src/product-finder/coreBoundary.test.js` — fails if `matchEngine.js`, `rules.js`, or `relaxationExplainer.js` import an `@wordpress/*` or `@woocommerce/*` package.

Both run as part of the normal `npm run test:php` / `npm run test:js`. Adding a new core module means adding it to both files' file lists — that's intentional; the list is meant to require a conscious decision, not silently expand.

No dependency-analysis tool (e.g. Deptrac) yet — there's exactly one boundary to check today, and a plain test needs nothing new installed. If more layers/boundaries show up later (see "revisit" above), that's the point to reconsider.

## How this maps to the testing tiers (§9 of the proposal doc)

- **Core** → plain PHPUnit (`tests/php/`) and Jest (`src/product-finder/*.test.js`), no WordPress bootstrap. Fast, and where TDD is practiced strictly.
- **Shell** → `WP_UnitTestCase` integration tests (`tests/integration/`) for anything touching `wp_options`/WooCommerce, plus a small Playwright e2e suite for the handful of critical full-stack paths (`tests/e2e/`).

If a class needs `WP_UnitTestCase` to test, it's shell by definition — that's a useful smell check when deciding where new code belongs.

## Where a premium add-on would plug in

Nothing to build for this today — the point is that the seam already exists without having to invent it. `ConfigRepository` and `FinderService` are where a premium module would hook in: additional templates, more question types, or multi-step conditional paths would extend the data these already read/return, not require changes inside `Engine/` or the rest of the core. Keeping the core boundary intact *is* the premium-readiness work; building actual extension points now, before there's a premium feature to plug in, would be solving a problem that doesn't exist yet.
