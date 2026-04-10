# SEO Spider

Free, open-source desktop app for technical SEO auditing. Crawl any website and get a full breakdown of issues, just like Screaming Frog — but free and with no crawl limits.

Available for **Linux**. Windows and macOS coming soon.

## What it does

SEO Spider crawls your website and analyzes every page for technical SEO problems. It finds broken links, missing meta tags, duplicate content, slow pages, security header gaps, and much more.

### 10 built-in analyzers

- **Broken Links** — HTTP errors, redirect chains and loops, mixed protocols
- **Meta Data** — Missing or bad titles, descriptions, H1 tags, viewport
- **Directives** — noindex, nofollow, canonical conflicts
- **Headings** — Hierarchy issues, missing H2s, heading order
- **Content** — Thin pages, empty pages
- **Images** — Missing alt text, overly long alt attributes
- **Performance** — Slow responses, oversized pages
- **Security Headers** — CSP, X-Frame-Options, HSTS, Referrer-Policy
- **Hreflang** — Invalid language/region codes, missing self-references
- **Duplicates** — Exact duplicates (SHA-256) and near-duplicates (SimHash)

### Dashboard and tools

- Overview with charts for status codes, response times, crawl depth, and issues
- Sortable table with 14 columns across 9 filterable tabs
- Detail panel with SEO info, SERP snippet preview, and link analysis
- External link verification via HEAD requests
- CSV export per tab
- Folders to organize your audits
- Pause and resume crawling at any time
- Light and dark mode
- Configurable crawl limits and robots.txt policy

## Download

Grab the latest release from the [Releases page](https://github.com/MarioDevv/web-scrapper/releases).

| Platform | Format |
|----------|--------|
| Linux | `.AppImage`, `.deb` |
| Windows | Coming soon |
| macOS | Coming soon |

## Development

```bash
# Requirements: PHP 8.5+, Node 22+, Composer

git clone https://github.com/MarioDevv/web-scrapper.git
cd web-scrapper
composer install
npm install
composer run native:dev
```

## License

AGPL-3.0 — Free to use, modify, and distribute. Any derivative work must also be open source under AGPL-3.0.

Commercial licensing available for proprietary use or SaaS integration. Contact perezmario.info@gmail.com.
