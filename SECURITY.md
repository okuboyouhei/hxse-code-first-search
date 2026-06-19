# Security Policy — HXSE — Code-First Search

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.0.x   | ✅ Active |
| < 1.0   | ❌ No longer supported |

Upgrade to the latest version is always recommended.

---

## Reporting a Vulnerability

**Please do not report security vulnerabilities in the public WordPress.org support forum.**

Contact via the WordPress.org support forum with the subject prefix `[Security]`, or reach out through the GitHub repository:

- **GitHub:** https://github.com/okuboyouhei/hxse-code-first-search
- **Subject line:** `[HXSE Security] Brief description`

### What to include

- Plugin version
- WordPress version and PHP version
- Steps to reproduce
- Expected vs actual behavior
- Impact assessment if known

### Response timeline

| Step | Target |
|------|--------|
| Acknowledgement | Within 3 business days |
| Assessment | Within 7 business days |
| Fix release | Within 30 days (critical: as soon as possible) |

---

## Security Design

HXSE is designed with a minimal attack surface:

- **No database writes** — HXSE reads posts and taxonomies via standard WordPress APIs. It does not write to the database.
- **No GUI** — All configuration lives in PHP code. There is no admin UI that processes user-supplied schema definitions.
- **No external dependencies** — htmx is bundled and pinned. No npm, no CDN calls at runtime.
- **Schema lives in code** — Filter definitions are PHP arrays in version-controlled files, not stored in the database.
- **Read-only by design** — HXSE executes `WP_Query` based on schema-defined parameters. User input from search forms is sanitized before being passed to the query.

### Security measures in place

| Area | Measure |
|------|---------|
| Input sanitization | All URL parameters are sanitized before use in `WP_Query` |
| Output | All output goes through WordPress template functions (`get_the_title()`, `get_the_permalink()`, etc.) |
| AJAX endpoint | Nonce-verified WordPress AJAX (or direct HTML output with `header()+echo+exit`) |
| Schema parameters | Only schema-defined keys are accepted; arbitrary query parameters are ignored |

---

## Forking and Self-Maintenance

HXSE is licensed under GPLv2 or later. You are free to fork and maintain your own version.

If you are concerned about long-term maintenance by a solo developer, see `MAINTENANCE.md` for:

- Plugin architecture overview
- How to update the bundled htmx version
- Common modification patterns
- Fork-friendly notes

The plugin's code-first design means modifications are localized and predictable. There are no compiled assets, no build steps, and no external services required for core functionality.

---

## Disclosure Policy

- Security fixes are released as soon as possible after confirmation.
- The fix version and a brief description are noted in the changelog (`readme.txt`).
- Critical vulnerabilities may be disclosed publicly after a fix is available and users have had reasonable time to update.
- We follow responsible disclosure — please allow time for a fix before public disclosure.

---

## Known Limitations

- This plugin is maintained by a solo developer. Response times may vary.
- Search result accuracy depends on WordPress core `WP_Query` behavior and post meta indexing.

---

*Last updated: 2026-06-19*
