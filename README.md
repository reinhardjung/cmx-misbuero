# Mis Buero

Mis Buero is a WordPress plugin project for small-business office workflows: contacts, invoices, documents, projects and budget.

The immediate packaging goal is a lean Free plugin for WordPress.org, plus separate paid/optional add-ons outside the WordPress.org repository.

## Package Goal

### `mis-buero`

Free WordPress.org plugin.

Included modules:

- Contacts/customers
- Invoices/documents
- Projects
- Budget
- Basic settings
- PDF and QR invoice support where required for the free workflows

### `mis-buero-business`

Paid add-on distributed outside WordPress.org.

Included modules:

- Bookings
- Banks/CAMT
- Emails
- Calendar
- Automation
- Advanced reports

### Optional Add-ons

Optional modules such as Payrexx, Factur-X, trustee integrations, OpenAI and industry-specific workflows should stay outside the WordPress.org Free plugin.

## Source Layout

- `src/` is the current legacy/live plugin structure.
- `packages/` is the target package structure.
- `packages/mis-buero/` is the Free WordPress.org target.
- `dist/` and `tmp/` are generated build outputs.

For the detailed package rules, see [PACKAGES.md](PACKAGES.md).

## Build

Build release ZIPs with:

```bash
bin/plugin-all.sh
```

Generated ZIPs are written to `dist/`.

## WordPress.org Focus

The Free plugin should be reviewed as a real standalone product, not as trialware. Business features must not be shipped locked inside the Free ZIP.

Before submitting to WordPress.org, the Free package needs:

- clean plugin header and `readme.txt`
- GPL-compatible code and assets
- no hard external service dependency
- no hidden tracking or remote requests without consent
- proper capabilities, nonces, sanitizing and escaping
- no debug output, warnings or deprecated notices
- local install/activate/deactivate/delete smoke test

