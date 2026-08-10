# Product Finder

A Gutenberg-native product finder for WooCommerce block-theme stores. See [PRODUCT-FINDER-PROPOSAL.md](PRODUCT-FINDER-PROPOSAL.md) for the full product plan, scope decisions, and build order.

## Local development setup

Prerequisites: Node.js, Docker Desktop (running), and `gh`/`git`.

```bash
npm install
npm run env start   # boots WordPress + WooCommerce via wp-env (Docker)
npm start            # webpack watch mode for the block source in src/
```

Dev site: http://localhost:8888 (admin/password). Test site (used by PHPUnit/wp-env's test container): http://localhost:8889.

Useful commands:
- `npm run env -- run cli wp <command>` — run any WP-CLI command inside the environment, e.g. `npm run env -- run cli wp plugin list`.
- `npm run env stop` / `npm run env start` — stop/start without losing data.
- `npm run env destroy` — tear down and start clean.

### Known environment quirks (Windows / this network)

Two workarounds are baked into this repo because of the local network and the project's folder path — neither should need attention again, but they're documented here so they're not a mystery later:

1. **`scripts/fix-wp-env-dns.js`** (runs automatically via `npm install`'s `postinstall`): `@wordpress/env`'s offline-detection check uses a raw DNS query (`dns.resolve`) to decide whether it can reach WordPress.org. On this network that raw query fails even though normal HTTPS/DNS resolution works fine, so `wp-env` silently decided it was offline and skipped downloading WordPress/WooCommerce entirely (no error — just empty containers). The script patches the vendored package to use `dns.lookup` instead, which works correctly here.
2. **The project folder is named "New folder" (contains a space).** `wp-env` derives a plugin's activation slug from its source folder's basename, and a space in that name broke `wp plugin activate` (it tried to activate plugins named `"New"` and `"folder"`). Rather than move the whole project (which the coding environment's working directory is pinned to), `.wp-env.json` points at a small Windows directory junction, `C:\dev\product-finder`, which resolves to this same folder under a clean name. The junction is host-local infrastructure, not part of the repo — if you ever set this project up on a fresh machine, only recreate it if your own path also contains a space:
   ```powershell
   New-Item -ItemType Junction -Path "C:\dev\product-finder" -Target "<path to this repo>"
   ```
   and point `.wp-env.json`'s `plugins` entry at that junction path instead of `"."`.
