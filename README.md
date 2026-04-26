# SEO Spider

[![Latest Release](https://img.shields.io/github/v/release/MarioDevv/web-scrapper?color=blue)](https://github.com/MarioDevv/web-scrapper/releases)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.5-8892BF)](composer.json)
[![PHPStan](https://img.shields.io/badge/phpstan-level%208-brightgreen)](phpstan.neon)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Platform](https://img.shields.io/badge/platform-Linux-orange)]()

Free and open-source desktop app for technical SEO audits. Crawl any website and get a full breakdown of issues — like Screaming Frog, but free and with no crawl limits.

> Built with [NativePHP](https://nativephp.com) + [Laravel](https://laravel.com) + [Livewire](https://livewire.laravel.com) + [Tailwind CSS](https://tailwindcss.com). No paid external dependencies.

## Why SEO Spider?

| | **SEO Spider** | Screaming Frog |
|---|---|---|
| Price | Free and open-source | $259/year |
| Crawl limit | No limits | 500 URLs (free) |
| Code | MIT | Proprietary |
| Platform | Linux (Windows/macOS coming soon) | Windows, macOS, Linux |
| Technology | PHP 8.5 + NativePHP + Livewire | Java |

## Download

Download the latest version from the [releases page](https://github.com/MarioDevv/web-scrapper/releases).

| Platform | Format |
|----------|--------|
| Linux | `.AppImage`, `.deb` |
| Windows | Coming soon |
| macOS | Coming soon |

## 53 SEO rules across 14 analyzers

Per-page analyzers run as each URL is fetched:

```
Broken Links       — HTTP errors, redirect chains, redirect loops,
                     mixed protocols, non-permanent redirects,
                     internal nofollow links
Meta Data          — Missing/incorrect title, description, H1,
                     viewport, html lang
Directives         — noindex, nofollow, canonical conflicts
Headings           — Hierarchy, missing H2s, heading order
Content            — Thin pages, empty pages
Images             — Missing alt, overly long alt, missing dimensions
Performance        — Slow responses, oversized pages
Security Headers   — CSP, X-Frame-Options, X-Content-Type-Options,
                     Referrer-Policy
Transport Security — Pages served over HTTP, mixed-content resources
Social Metadata    — Open Graph completeness, Twitter Card completeness
Structured Data    — JSON-LD / Microdata presence
Hreflang           — Invalid language/region codes, missing self-ref
Duplicates         — Exact (SHA-256) and near (SimHash) duplicates
```

Site-wide analyzers run once after the crawl finishes, when they need to
see the audit graph as a whole:

```
Hreflang Return     — Reciprocal hreflang clusters across pages
Canonical Target    — Canonicals pointing at 4xx/5xx, redirected,
                      or noindexed pages
Robots Indexable    — Indexable pages disallowed by robots.txt
Sitemap Coverage    — Pages crawled but absent from the sitemap,
                      and URLs declared in the sitemap that the
                      crawler never reached
```

## Site Score

Every audit gets a **0–100 site score** computed Lighthouse-style: each
rule has a weight (0–10), each page contributes a 0–100 score based on
its weighted issues, the audit score is the average across crawled
pages. Page-level severity badges, group-level priorities, and the
overall score all reflect the same weighting.

## Dashboard and tools

- Overview with status code, response time, crawl depth and issue charts
- Sortable, paginated page table across 10 filterable tabs
- Detail panel with SEO info, SERP preview and link analysis
- External link verification via HEAD requests
- CSV export per tab
- Folders to organize your audits
- Pause and resume crawling at any time
- Light and dark mode
- Configurable crawl limits and robots.txt policy

## Performance

Optimised for audits of hundreds to thousands of pages:

- SQL read model and materialised columns avoid hydrating Page
  aggregates for the table view; opening a 250-page audit takes ~2 ms.
- Snapshot post-crawl: when an audit completes, the dashboard overview
  is computed once and stored as JSON; subsequent opens read a single
  row instead of recomputing aggregations.
- Delta-fetch during live crawls: the polling UI only loads pages
  crawled since the last tick instead of refetching the whole audit.
- Server-side pagination, search and sort.
- Issue-report payload capped per group with click-to-expand details.

## Features

- [x] Full website crawling with no limits
- [x] 53 SEO rules across 14 per-page + 4 site-wide analyzers
- [x] Lighthouse-style 0–100 site score
- [x] Dashboard with charts, metrics and snapshot caching
- [x] Exact (SHA-256) and near (SimHash) duplicate detection
- [x] External link verification
- [x] SERP preview in detail panel
- [x] CSV export
- [x] Light and dark mode
- [x] Folders to organize audits
- [x] Pause / resume crawling
- [x] Built-in auto-updater
- [ ] Windows support
- [ ] macOS support
- [ ] JavaScript rendering crawl (SPA)
- [ ] Core Web Vitals (CrUX API integration)
- [ ] Localised rule catalog (Spanish first)
- [ ] Exportable PDF reports

## Development

```bash
# Requirements: PHP 8.5+, Node 22+, Composer

git clone https://github.com/MarioDevv/web-scrapper.git
cd web-scrapper
composer install
npm install
composer run native:dev
```

## Contributing

Found a bug? [Open an issue](https://github.com/MarioDevv/web-scrapper/issues).
Want to contribute code? PRs are welcome.

```bash
vendor/bin/phpunit          # 326 tests / 587 assertions
vendor/bin/phpstan analyse  # level 8, 0 errors
vendor/bin/pint
```

## License

[MIT](LICENSE) © Mario Perez
