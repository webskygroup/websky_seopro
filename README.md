# Websky SEO Pro for OpenCart 4

Websky SEO Pro is a native OpenCart 4 extension for technical, on-page and AI-assisted SEO. It keeps the existing `websky_seo` package code so upgrades do not create a second extension.

## Included in v2.0

- Per-store and per-language SEO records for products, categories, information pages, manufacturers and the home page.
- Custom meta title, meta description, keywords, tags, H1, H2, image ALT/title, robots and SEO URL fields.
- Safe batch generation from templates with unique slug checking, optimistic concurrency checks and a revision history table.
- OpenAI Responses API integration from the admin panel. The API key is used only on the server, `store=false` is sent, response fields are previewed before saving, and a daily quota is enforced.
- Canonical, robots, Open Graph, Twitter card, alternate hreflang and Product/Offer/Rating JSON-LD output.
- Store-aware XML sitemap for products, categories, manufacturers and information pages plus a generated `robots.txt` route.
- Redirect manager with loop/chain validation, hit counts and a privacy-safe 404 report.
- Internal-link and tooltip rules that operate only on text nodes and skip existing links, headings, scripts and code.
- Persian and English admin language files and a responsive RTL-friendly control center.

## Install

1. Download `websky_seo.ocmod.zip` from a GitHub release.
2. Upload it through **Extensions > Installer**.
3. Open **Extensions > Extensions > Modules**, install **Websky SEO Pro**, then grant the module route to the intended user group.
4. Open the module, select a store/language, configure the settings and save.
5. Add an OpenAI API key only if AI generation is required. The key is never rendered into the storefront or browser JavaScript.

The release archive contains `admin/`, `catalog/`, `system/` and `install.json`; OpenCart's installer places them under `extension/websky_seo/`.

## OpenAI setup

The module sends a server-side `POST /v1/responses` request with the configured model, a strict JSON schema for SEO fields, the selected entity context and `store=false`. Network/TLS errors, API errors, invalid JSON and quota exhaustion are shown in the admin UI. No API key, prompt body or product description is written to the module log.

## Validation

The repository workflow runs PHP syntax checks and builds/tests `websky_seo.ocmod.zip`. The module requires OpenCart 4.0.2+ and PHP 8.1+; production acceptance still requires installing the archive on the target store and checking the actual admin route, saved settings, storefront HTML and sitemap response.

## License

GPL-3.0-or-later.
