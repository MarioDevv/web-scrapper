# SEO Spider

[![Latest Release](https://img.shields.io/github/v/release/MarioDevv/web-scrapper?color=blue)](https://github.com/MarioDevv/web-scrapper/releases)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.5-8892BF)](composer.json)
[![PHPStan](https://img.shields.io/badge/phpstan-level%202-brightgreen)](phpstan.neon)
[![License](https://img.shields.io/badge/license-AGPL--3.0-green)](LICENSE)
[![Platform](https://img.shields.io/badge/platform-Linux-orange)]()

Free and open-source desktop app for technical SEO audits. Crawl any website and get a full breakdown of issues — like Screaming Frog, but free and with no crawl limits.

> Built with [NativePHP](https://nativephp.com) + [Laravel](https://laravel.com) + [Livewire](https://livewire.laravel.com) + [Tailwind CSS](https://tailwindcss.com). No paid external dependencies.

## Why SEO Spider?

| | **SEO Spider** | Screaming Frog |
|---|---|---|
| Price | Free and open-source | $259/year |
| Crawl limit | No limits | 500 URLs (free) |
| Code | AGPL-3.0 | Proprietary |
| Platform | Linux (Windows/macOS coming soon) | Windows, macOS, Linux |
| Technology | PHP 8.5 + NativePHP + Livewire | Java |

## Download

Download the latest version from the [releases page](https://github.com/MarioDevv/web-scrapper/releases).

| Platform | Format |
|----------|--------|
| Linux | `.AppImage`, `.deb` |
| Windows | Coming soon |
| macOS | Coming soon |

## 10 built-in analyzers

```
Broken Links     — HTTP errors, redirect chains, mixed protocols
Meta Data        — Missing or incorrect titles, descriptions, H1, viewport
Directives       — noindex, nofollow, canonical conflicts
Headings         — Hierarchy, missing H2s, heading order
Content          — Thin pages, empty pages
Images           — Missing alt text, overly long alt attributes
Performance      — Slow responses, oversized pages
Security Headers — CSP, X-Frame-Options, HSTS, Referrer-Policy
Hreflang         — Invalid language/region codes, missing self-references
Duplicates       — Exact (SHA-256) and near (SimHash) duplicates
```

## Dashboard and tools

- Overview with status code, response time, crawl depth, and issue charts
- Sortable table with 14 columns across 9 filterable tabs
- Detail panel with SEO info, SERP preview, and link analysis
- External link verification via HEAD requests
- CSV export per tab
- Folders to organize your audits
- Pause and resume crawling at any time
- Light and dark mode
- Configurable crawl limits and robots.txt policy

## Features

- [x] Full website crawling with no limits
- [x] 10 built-in SEO analyzers
- [x] Dashboard with charts and metrics
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
vendor/bin/phpunit
vendor/bin/phpstan analyse src
vendor/bin/pint
```

## License

[AGPL-3.0](LICENSE) © Mario Perez

Commercial license available for proprietary use or SaaS integration. Contact: perezmario.info@gmail.com.
