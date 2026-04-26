<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

/**
 * Registry of every issue code the analyzers can emit. The catalog
 * owns the user-facing prose (title/why/how) and the canonical
 * severity + category for each code, so the UI and docs stay in
 * sync without each analyzer redefining the same copy.
 */
final class IssueRuleCatalog
{
    /** @var array<string, IssueRule>|null */
    private static ?array $cache = null;

    public static function forCode(string $code): ?IssueRule
    {
        return self::all()[$code] ?? null;
    }

    /** @return array<string, IssueRule> */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = self::build();
        }

        return self::$cache;
    }

    /** @return array<string, IssueRule> */
    private static function build(): array
    {
        $rules = [
            // ── METADATA ────────────────────────────────────────────────
            new IssueRule(
                code: 'title_missing',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::ERROR,
                title: 'Title tag missing',
                summary: 'The page does not declare a <title> element.',
                why: 'Title is the single strongest on-page signal: it appears in search results, browser tabs, and social shares. Without it search engines infer a title from the URL or headings, which rarely matches intent.',
                how: 'Add a unique <title> in <head>, 50–60 characters, that describes the page and includes the main query the page answers.',
                source: 'https://developers.google.com/search/docs/appearance/title-link',
            ),
            new IssueRule(
                code: 'title_too_long',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                title: 'Title too long',
                summary: 'The <title> exceeds the visible SERP limit (~60 characters).',
                why: 'Google truncates titles past roughly 600 pixels (~60 characters on desktop). The cut-off often loses the keyword or brand, reducing click-through.',
                how: 'Tighten the title to ≤60 characters, keeping the primary keyword at the front. Move brand to the end or drop it on long queries.',
                source: 'https://developers.google.com/search/docs/appearance/title-link',
            ),
            new IssueRule(
                code: 'title_too_short',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                title: 'Title too short',
                summary: 'The <title> has fewer than 30 characters.',
                why: 'Very short titles under-use SERP real estate and usually fail to cover the search intent — you forfeit clicks to competitors with richer titles.',
                how: 'Expand to 50–60 characters: the main keyword, a qualifier (guide, price, review, year) and optionally the brand.',
            ),
            new IssueRule(
                code: 'meta_description_missing',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                title: 'Meta description missing',
                summary: 'The page has no <meta name="description"> tag.',
                why: 'Without a meta description Google generates a snippet from page text, which is often off-topic and hurts click-through rate. The description is not a ranking factor but it is a CTR factor.',
                how: 'Add a 120–160 character description that summarises the page and implies the benefit of clicking. Unique per page.',
                source: 'https://developers.google.com/search/docs/appearance/snippet',
            ),
            new IssueRule(
                code: 'meta_description_too_long',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                title: 'Meta description too long',
                summary: 'The meta description exceeds 160 characters.',
                why: 'Beyond ~160 characters Google truncates the snippet with an ellipsis, often cutting the call-to-action. The last sentence is wasted.',
                how: 'Trim to 120–160 characters, front-loading the value proposition.',
            ),
            new IssueRule(
                code: 'h1_missing',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                title: 'H1 heading missing',
                summary: 'The page has no <h1> element.',
                why: 'The H1 gives both users and crawlers the page\'s topic at a glance. Its absence weakens content understanding and hurts accessibility (screen-reader navigation).',
                how: 'Add one descriptive H1 near the top of <main>, closely mirroring the title.',
            ),
            new IssueRule(
                code: 'h1_multiple',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                title: 'Multiple H1 headings',
                summary: 'The page contains more than one <h1>.',
                why: 'HTML5 allows multiple H1s and Google has confirmed it is not a ranking penalty. It can still dilute structural clarity for readers and screen readers.',
                how: 'Prefer one main H1 per page and demote the rest to H2/H3 where they belong in the outline.',
                source: 'https://www.youtube.com/watch?v=zyqJJXWk0gk',
            ),
            new IssueRule(
                code: 'viewport_missing',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                title: 'Viewport meta tag missing',
                summary: 'No <meta name="viewport"> declared.',
                why: 'Without a viewport declaration mobile browsers render at desktop width and zoom out, producing a poor mobile experience. Google uses mobile-first indexing, so this directly hurts mobile rankings.',
                how: 'Add <meta name="viewport" content="width=device-width, initial-scale=1"> in <head>.',
                source: 'https://developers.google.com/search/docs/crawling-indexing/mobile/mobile-sites-mobile-first-indexing',
            ),
            new IssueRule(
                code: 'html_lang_missing',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                title: 'HTML lang attribute missing',
                summary: 'The <html> element does not declare a lang attribute.',
                why: 'Without lang, screen readers fall back to the browser default and may pronounce content with the wrong phonemes. Google also uses lang as one of several signals to confirm the locale of the page.',
                how: 'Add lang to the root element using a BCP 47 code: <html lang="es"> or <html lang="en-GB">. Match the actual content language.',
                source: 'https://www.w3.org/International/questions/qa-html-language-declarations',
            ),
            new IssueRule(
                code: 'open_graph_incomplete',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                title: 'Open Graph metadata incomplete',
                summary: 'One or more of og:title, og:description, og:image is missing.',
                why: 'Open Graph drives the preview card when the URL is shared on Facebook, LinkedIn, Slack, WhatsApp and most chat apps. Missing tags fall back to a generic snippet (or no preview at all), reducing click-through.',
                how: 'Add the three core OG meta tags in <head>: og:title (≤60 chars), og:description (≤160 chars), og:image (1200×630 PNG/JPG). Include og:url and og:type=website|article for completeness.',
                source: 'https://ogp.me/',
            ),
            new IssueRule(
                code: 'twitter_card_incomplete',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                title: 'Twitter Card metadata incomplete',
                summary: 'One or more of twitter:card, twitter:title, twitter:description, twitter:image is missing.',
                why: 'Without Twitter Card tags, links shared on X/Twitter render as bare URLs instead of preview cards. X falls back to Open Graph for some fields, but explicit twitter:* tags give the most control over the preview.',
                how: 'Add twitter:card (typically "summary_large_image"), twitter:title, twitter:description and twitter:image in <head>. Reuse the OG image to keep the preview consistent across platforms.',
                source: 'https://developer.x.com/en/docs/x-for-websites/cards/overview/abouts-cards',
            ),
            new IssueRule(
                code: 'schema_org_missing',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::INFO,
                title: 'No structured data',
                summary: 'The page declares no schema.org JSON-LD or Microdata markup.',
                why: 'Structured data is what Google reads to render rich results: review stars, FAQ accordions, breadcrumbs, sitelinks, recipe panels. It is not a ranking factor on its own, but unlocks SERP features that lift CTR substantially.',
                how: 'Add a JSON-LD <script type="application/ld+json"> block in <head> with the relevant schema (Article, Product, Organization, BreadcrumbList). Validate it at https://validator.schema.org before deploying.',
                source: 'https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data',
            ),

            // ── HEADINGS ────────────────────────────────────────────────
            new IssueRule(
                code: 'h2_missing',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                title: 'No H2 headings',
                summary: 'The page declares an H1 but no H2 subheadings.',
                why: 'H2s segment long-form content for skimming and give crawlers topical sub-structure. A page with meaningful length but no H2s often reads as a wall of text.',
                how: 'Break the body into logical sections with H2s that paraphrase the question each section answers.',
            ),
            new IssueRule(
                code: 'h1_not_first',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                title: 'First heading is not H1',
                summary: 'The first heading in the document flow is not an H1.',
                why: 'Crawlers and assistive technology both weight the first heading heavily. Leading with an H2 or lower signals a missing top-level topic.',
                how: 'Re-order so the H1 appears before any H2/H3. If the leading heading is a section title, promote the page topic to H1 above it.',
            ),
            new IssueRule(
                code: 'heading_skip',
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                title: 'Heading hierarchy skip',
                summary: 'The heading outline jumps over a level (e.g. H1 → H3).',
                why: 'Skipped levels break the semantic outline, making the page harder for screen readers and crawlers to parse. It is primarily an accessibility concern.',
                how: 'Use consecutive levels: H1 → H2 → H3. If an H3 does not belong to any H2, restructure the section.',
                source: 'https://www.w3.org/WAI/tutorials/page-structure/headings/',
            ),

            // ── CONTENT ─────────────────────────────────────────────────
            new IssueRule(
                code: 'content_empty',
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::WARNING,
                title: 'Page is effectively empty',
                summary: 'The page has almost no readable text (≤50 words).',
                why: 'Empty or near-empty HTML pages are classic soft-404 candidates: Google may drop them from the index or demote them as low-value.',
                how: 'Ensure the page renders its content server-side or returns a genuine 404/410 if the URL should not exist. Check for JS-only rendering that leaves the pre-render blank.',
                source: 'https://developers.google.com/search/docs/crawling-indexing/http-network-errors#soft-404-errors',
            ),
            new IssueRule(
                code: 'content_thin',
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::NOTICE,
                title: 'Very short content',
                summary: 'The page has fewer than 80 words of body copy.',
                why: 'Google has repeatedly stated word count is not a ranking factor, but pages too short to cover search intent tend to lose to competitors that do. Review whether the content answers why someone landed here.',
                how: 'Expand only if the current copy does not cover the intent. Do not pad for length — definitions, landing pages and product pages legitimately stay short.',
            ),
            new IssueRule(
                code: 'img_alt_missing',
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::WARNING,
                title: 'Images without alt text',
                summary: 'One or more <img> tags lack an alt attribute.',
                why: 'Alt text is required by WCAG for assistive technology, and Google uses it both for image search ranking and to understand page topic when surrounding context is thin.',
                how: 'Add a descriptive alt that conveys the image\'s purpose. Use alt="" for purely decorative images.',
                source: 'https://www.w3.org/WAI/tutorials/images/',
            ),
            new IssueRule(
                code: 'img_alt_too_long',
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::INFO,
                title: 'Image alt text is long',
                summary: 'An alt attribute exceeds ~125 characters.',
                why: 'Legacy screen readers truncate alt past roughly 125 characters. This is an accessibility guideline, not a direct SEO factor.',
                how: 'Tighten to the essential description. If more context is needed, move it to adjacent body copy or figcaption.',
            ),
            new IssueRule(
                code: 'image_missing_dimensions',
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::NOTICE,
                title: 'Images without width/height',
                summary: 'One or more <img> tags are missing the width and/or height attributes.',
                why: 'Without explicit dimensions the browser cannot reserve space before the image loads, causing layout shift (CLS). CLS is a Core Web Vital and a confirmed mobile ranking factor.',
                how: 'Add width and height attributes that match the intrinsic image size. The browser computes the aspect ratio and reserves the right space, even when the rendered size is controlled by CSS.',
                source: 'https://web.dev/articles/optimize-cls',
            ),
            new IssueRule(
                code: 'exact_duplicate',
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::WARNING,
                title: 'Exact duplicate content',
                summary: 'This page has the same body content as another crawled page.',
                why: 'Identical pages compete against each other, splitting signals and often ending with Google indexing only one of them — not necessarily the one you want.',
                how: 'Consolidate to a single canonical URL with rel=canonical, or 301 one into the other. If the duplication is legitimate (print/AMP/localisation), set canonical explicitly.',
                source: 'https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls',
            ),
            new IssueRule(
                code: 'near_duplicate',
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::NOTICE,
                title: 'Near-duplicate content',
                summary: 'The page is substantially similar to another crawled page.',
                why: 'Near-duplicates (e.g. product variants, boilerplate-heavy templates) may be grouped by Google and lose individual ranking. This is the classic symptom of thin, templated pages.',
                how: 'Differentiate each URL with unique title, H1, opening paragraph and meaningful content. If differentiation is impossible, canonicalise to one.',
            ),

            // ── LINKS ───────────────────────────────────────────────────
            new IssueRule(
                code: 'client_error',
                category: IssueCategory::LINKS,
                severity: IssueSeverity::ERROR,
                title: 'Page returns 4xx',
                summary: 'The URL responded with an HTTP 4xx client error.',
                why: 'Pages returning 4xx are invisible to users and drop from the index over time. Internal links pointing here waste crawl budget and pass no equity.',
                how: 'Either restore the page, redirect (301) to the closest live equivalent, or remove any internal links pointing to this URL.',
            ),
            new IssueRule(
                code: 'server_error',
                category: IssueCategory::LINKS,
                severity: IssueSeverity::ERROR,
                title: 'Page returns 5xx',
                summary: 'The URL responded with an HTTP 5xx server error.',
                why: 'Server errors are the worst failure mode for SEO: Google may temporarily de-rank the page and, if persistent, remove it from the index entirely.',
                how: 'Investigate server logs; fix root cause. If the outage is expected/short, respond with 503 + Retry-After instead of 500.',
                source: 'https://developers.google.com/search/docs/crawling-indexing/http-network-errors',
            ),
            new IssueRule(
                code: 'redirect_chain',
                category: IssueCategory::LINKS,
                severity: IssueSeverity::WARNING,
                title: 'Redirect chain',
                summary: 'The URL goes through two or more redirect hops before landing.',
                why: 'Each hop adds latency, wastes crawl budget, and — although Google now follows long chains — progressively dilutes the signal. Users and crawlers alike prefer direct 1-hop redirects.',
                how: 'Update the origin link or the first redirect target so any resolution completes in a single hop.',
            ),
            new IssueRule(
                code: 'redirect_loop',
                category: IssueCategory::LINKS,
                severity: IssueSeverity::ERROR,
                title: 'Redirect loop',
                summary: 'The redirect chain visits the same URL twice.',
                why: 'A loop is an outright failure: browsers abort after a few hops and users see an error page. Google cannot index the target.',
                how: 'Trace the chain and break the cycle: remove the offending redirect rule or change its target.',
            ),
            new IssueRule(
                code: 'mixed_protocol_redirect',
                category: IssueCategory::LINKS,
                severity: IssueSeverity::WARNING,
                title: 'HTTP↔HTTPS in redirect chain',
                summary: 'The redirect chain alternates between HTTP and HTTPS.',
                why: 'Bouncing protocols inflates the chain and exposes the user to a cleartext hop, which can trigger HSTS warnings and browser blocks.',
                how: 'Redirect from HTTP to HTTPS once at the edge and keep all subsequent hops on HTTPS.',
            ),
            new IssueRule(
                code: 'redirect_not_permanent',
                category: IssueCategory::LINKS,
                severity: IssueSeverity::NOTICE,
                title: 'Redirect is not permanent (301/308)',
                summary: 'The redirect chain uses 302/303/307 instead of a permanent 301/308.',
                why: 'Temporary redirects tell crawlers the move may revert, so signals from the source URL are not consolidated to the destination. Long-lived moves should be 301 (or 308 to preserve method) for cleaner indexing.',
                how: 'Change the redirect rule to 301 (or 308 if the original method must be preserved). Use 302/307 only for genuinely temporary redirects (A/B tests, maintenance pages).',
                source: 'https://developers.google.com/search/docs/crawling-indexing/301-redirects',
            ),
            new IssueRule(
                code: 'internal_nofollow',
                category: IssueCategory::LINKS,
                severity: IssueSeverity::NOTICE,
                title: 'Internal links with rel="nofollow"',
                summary: 'The page has one or more internal links marked rel="nofollow".',
                why: 'Since 2019 Google treats nofollow as a hint rather than a directive, but nofollowed internal links still discourage crawling and pass weaker signals than follow links.',
                how: 'Remove rel="nofollow" from internal links unless there is a specific reason (user-generated content, sponsored, login-only destinations).',
                source: 'https://developers.google.com/search/blog/2019/09/evolving-nofollow-new-ways-to-identify',
            ),

            // ── DIRECTIVES ─────────────────────────────────────────────
            new IssueRule(
                code: 'noindex',
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::INFO,
                title: 'noindex directive present',
                summary: 'The page declares a noindex directive.',
                why: 'noindex is a deliberate choice that keeps the page out of search results. Flagging it as info so you can confirm it is intentional (staging pages, thank-you pages, filters) and not accidental.',
                how: 'If intentional, no action. If accidental (e.g. leaked from staging), remove the meta robots or X-Robots-Tag and submit the URL for reindexing.',
                source: 'https://developers.google.com/search/docs/crawling-indexing/block-indexing',
            ),
            new IssueRule(
                code: 'nofollow',
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::INFO,
                title: 'nofollow directive present',
                summary: 'The page declares a page-level nofollow.',
                why: 'Page-level nofollow tells crawlers not to follow any link on the page. Rarely the right answer; usually a leftover from earlier PageRank-sculpting practices.',
                how: 'Verify the directive is intentional. If not, remove it — internal links are how crawlers discover the rest of the site.',
            ),
            new IssueRule(
                code: 'canonical_missing',
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::NOTICE,
                title: 'No canonical tag',
                summary: 'The page does not declare a rel="canonical" link.',
                why: 'Google can infer a canonical URL on its own, but declaring one removes ambiguity when the page is reachable via multiple URLs (tracking params, case, trailing slash).',
                how: 'Add <link rel="canonical" href="..."> pointing to the preferred URL. A self-referential canonical is the safe default on every indexable page.',
                source: 'https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls',
            ),
            new IssueRule(
                code: 'canonical_non_self',
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::INFO,
                title: 'Canonical points elsewhere',
                summary: 'The rel="canonical" does not point to this URL.',
                why: 'A non-self canonical tells Google to index a different URL and consolidate signals there. Flagging as info so you can confirm the destination is correct and not a typo.',
                how: 'Verify the canonical target. If this page is the real content, change the canonical to itself. Cross-domain canonicals are valid but should be intentional.',
            ),
            new IssueRule(
                code: 'noindex_with_canonical',
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::ERROR,
                title: 'Conflict: noindex and canonical together',
                summary: 'The page declares both noindex and rel="canonical".',
                why: 'noindex and canonical send opposing instructions: "don\'t index this" vs "consolidate signals here". Google may ignore the canonical, or worse, propagate the noindex to the canonical target.',
                how: 'Pick one: if the page should not be indexed, keep noindex and remove canonical. If the page should be consolidated with another, remove noindex and keep canonical.',
                source: 'https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls#rel-canonical-link-method',
            ),

            // ── HREFLANG ───────────────────────────────────────────────
            new IssueRule(
                code: 'hreflang_invalid_language',
                category: IssueCategory::HREFLANG,
                severity: IssueSeverity::ERROR,
                title: 'Invalid hreflang language code',
                summary: 'An hreflang attribute uses a non-ISO-639-1 language code.',
                why: 'Google silently ignores hreflang entries with invalid language codes. The international targeting you think is in place is actually broken.',
                how: 'Use two-letter ISO 639-1 codes (es, en, fr, de, …). The special token x-default is also valid when used on its own.',
                source: 'https://developers.google.com/search/docs/specialty/international/localized-versions',
            ),
            new IssueRule(
                code: 'hreflang_invalid_region',
                category: IssueCategory::HREFLANG,
                severity: IssueSeverity::ERROR,
                title: 'Invalid hreflang region code',
                summary: 'An hreflang region subtag is not a valid ISO 3166-1 code.',
                why: 'Invalid region codes cause Google to drop the hreflang mapping, breaking international targeting for that locale.',
                how: 'Use two-letter ISO 3166-1 alpha-2 codes (ES, US, GB, MX, …). Region is optional; omit it if targeting a language globally.',
                source: 'https://developers.google.com/search/docs/specialty/international/localized-versions',
            ),
            new IssueRule(
                code: 'hreflang_missing_self',
                category: IssueCategory::HREFLANG,
                severity: IssueSeverity::WARNING,
                title: 'Missing hreflang self-reference',
                summary: 'The page declares hreflang for other locales but does not include itself.',
                why: 'Google requires hreflang clusters to be symmetric: each locale page must reference itself and every sibling. Missing self-reference invalidates the whole cluster.',
                how: 'Add an hreflang entry pointing from the page to itself, using the same language/region code as the page.',
                source: 'https://developers.google.com/search/docs/specialty/international/localized-versions#html',
            ),
            new IssueRule(
                code: 'hreflang_duplicate',
                category: IssueCategory::HREFLANG,
                severity: IssueSeverity::WARNING,
                title: 'Duplicate hreflang entry',
                summary: 'Two hreflang entries share the same language/region code.',
                why: 'When the same locale points to two URLs, Google cannot decide which to serve and may ignore the annotation entirely.',
                how: 'Keep one entry per language/region pair. Remove duplicates or fix the mistyped code.',
            ),

            // ── PERFORMANCE ────────────────────────────────────────────
            new IssueRule(
                code: 'response_very_slow',
                category: IssueCategory::PERFORMANCE,
                severity: IssueSeverity::ERROR,
                title: 'Very slow server response',
                summary: 'The server took 3 seconds or more to start sending the page.',
                why: 'TTFB above 3s regularly causes users to abandon and is a drag on Core Web Vitals (LCP). Crawlers also throttle their rate on slow origins, shrinking your crawl budget.',
                how: 'Profile the request: DB queries, external APIs in the critical path, cold caches. Target TTFB under 600 ms for dynamic pages and under 100 ms for cached content.',
                source: 'https://web.dev/articles/ttfb',
            ),
            new IssueRule(
                code: 'response_slow',
                category: IssueCategory::PERFORMANCE,
                severity: IssueSeverity::WARNING,
                title: 'Slow server response',
                summary: 'The server took over 1 second before the first byte.',
                why: 'TTFB above 1s directly delays Largest Contentful Paint, which is a Core Web Vital and a ranking signal on mobile.',
                how: 'Cache the response, reduce DB round-trips, or move work off the request path. Target sub-600ms TTFB.',
            ),
            new IssueRule(
                code: 'page_too_large',
                category: IssueCategory::PERFORMANCE,
                severity: IssueSeverity::WARNING,
                title: 'HTML payload too large',
                summary: 'The HTML document exceeds 512 KB.',
                why: 'Large HTML documents delay parsing, hurt LCP/INP, and waste crawl bandwidth. The size itself is not a ranking factor but the downstream metrics are.',
                how: 'Inline less; move inline CSS/JS to external files; lazy-load below-the-fold markup. Ship the same content in less HTML.',
            ),

            // ── SECURITY ───────────────────────────────────────────────
            new IssueRule(
                code: 'csp_missing',
                category: IssueCategory::SECURITY,
                severity: IssueSeverity::NOTICE,
                title: 'Content-Security-Policy header missing',
                summary: 'The response does not include a CSP header.',
                why: 'CSP is the strongest defence against XSS and asset injection. It does not affect SEO rankings, but a compromised page is an SEO disaster (malware warnings, de-indexing).',
                how: 'Start with a report-only CSP to observe violations, then enforce. Allowlist only the origins your site genuinely loads scripts/styles/images from.',
                source: 'https://developer.mozilla.org/docs/Web/HTTP/CSP',
            ),
            new IssueRule(
                code: 'x_frame_missing',
                category: IssueCategory::SECURITY,
                severity: IssueSeverity::NOTICE,
                title: 'X-Frame-Options header missing',
                summary: 'The response does not set X-Frame-Options.',
                why: 'Without this header the page can be embedded in a hostile iframe, enabling clickjacking. Not an SEO factor directly, but a security baseline.',
                how: 'Add "X-Frame-Options: SAMEORIGIN" or use the modern equivalent "Content-Security-Policy: frame-ancestors \'self\'".',
                source: 'https://developer.mozilla.org/docs/Web/HTTP/Headers/X-Frame-Options',
            ),
            new IssueRule(
                code: 'x_content_type_missing',
                category: IssueCategory::SECURITY,
                severity: IssueSeverity::NOTICE,
                title: 'X-Content-Type-Options header missing',
                summary: 'The response does not set X-Content-Type-Options.',
                why: 'Without nosniff, browsers may MIME-sniff user-uploaded content and execute it as script. Low probability, easy to set.',
                how: 'Add "X-Content-Type-Options: nosniff" to every response.',
                source: 'https://developer.mozilla.org/docs/Web/HTTP/Headers/X-Content-Type-Options',
            ),
            new IssueRule(
                code: 'referrer_policy_missing',
                category: IssueCategory::SECURITY,
                severity: IssueSeverity::NOTICE,
                title: 'Referrer-Policy header missing',
                summary: 'The response does not set Referrer-Policy.',
                why: 'Without a policy, browsers use the default (strict-origin-when-cross-origin in modern browsers, less strict in older ones). Explicit is better and prevents leaking query params in Referer.',
                how: 'Add "Referrer-Policy: strict-origin-when-cross-origin" unless you have a specific reason to send more.',
                source: 'https://developer.mozilla.org/docs/Web/HTTP/Headers/Referrer-Policy',
            ),
            new IssueRule(
                code: 'referrer_policy_insecure',
                category: IssueCategory::SECURITY,
                severity: IssueSeverity::NOTICE,
                title: 'Referrer-Policy leaks data',
                summary: 'The Referrer-Policy allows full-URL referrers across origins.',
                why: 'Policies like "unsafe-url" or "no-referrer-when-downgrade" expose full URLs (including query strings, which may carry tokens) to third-party origins.',
                how: 'Switch to "strict-origin-when-cross-origin" — the same behaviour modern browsers default to. Only relax for specific analytics use cases.',
            ),
            new IssueRule(
                code: 'http_insecure',
                category: IssueCategory::SECURITY,
                severity: IssueSeverity::ERROR,
                title: 'Page served over plain HTTP',
                summary: 'The final URL of the page resolves over http:// rather than https://.',
                why: 'Google has used HTTPS as a ranking signal since 2014, and Chrome marks plain HTTP pages as "Not secure". HTTP also exposes traffic to interception and tampering, which can inject content that flips the page into a search-engine penalty.',
                how: 'Configure the server to serve everything over HTTPS, redirect HTTP→HTTPS at the edge with 301, and set Strict-Transport-Security to lock browsers into HTTPS for the domain.',
                source: 'https://developers.google.com/search/blog/2014/08/https-as-ranking-signal',
            ),
            new IssueRule(
                code: 'mixed_content',
                category: IssueCategory::SECURITY,
                severity: IssueSeverity::WARNING,
                title: 'Mixed content (HTTP resources on HTTPS page)',
                summary: 'The page is served over HTTPS but loads scripts, stylesheets, images, or iframes over HTTP.',
                why: 'Modern browsers block active mixed content (scripts, iframes, stylesheets) outright and warn on passive mixed content (images, video). The page may render broken, lose the padlock icon, or trigger a downgrade warning that hurts trust and CTR.',
                how: 'Update each HTTP reference to https:// (or use a protocol-relative reference for assets the origin also serves over HTTPS). Add a Content-Security-Policy with upgrade-insecure-requests as a safety net.',
                source: 'https://developer.mozilla.org/docs/Web/Security/Mixed_content',
            ),
        ];

        $indexed = [];
        foreach ($rules as $rule) {
            $indexed[$rule->code] = $rule;
        }

        return $indexed;
    }
}
