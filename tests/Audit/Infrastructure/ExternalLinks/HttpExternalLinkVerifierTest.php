<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\ExternalLinks;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Link;
use SeoSpider\Audit\Domain\Model\Page\LinkRelation;
use SeoSpider\Audit\Domain\Model\Page\LinkType;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Infrastructure\ExternalLinks\HttpExternalLinkVerifier;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryExternalLinkRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\StubHttpClient;

final class HttpExternalLinkVerifierTest extends TestCase
{
    private InMemoryPageRepository $pages;
    private InMemoryExternalLinkRepository $external;
    private StubHttpClient $http;
    private HttpExternalLinkVerifier $verifier;
    private AuditId $auditId;

    protected function setUp(): void
    {
        $this->pages = new InMemoryPageRepository();
        $this->external = new InMemoryExternalLinkRepository();
        $this->http = new StubHttpClient();
        $this->verifier = new HttpExternalLinkVerifier(
            pageRepository: $this->pages,
            externalLinkRepository: $this->external,
            httpClient: $this->http,
        );
        $this->auditId = AuditId::generate();
    }

    public function test_returns_zero_when_audit_has_no_pages(): void
    {
        $this->assertSame(0, $this->verifier->verify($this->auditId));
    }

    public function test_returns_zero_when_no_page_has_external_anchor_links(): void
    {
        $this->saveWithLinks('https://example.com/a', []);
        $this->saveWithLinks('https://example.com/b', [
            $this->internalAnchor('https://example.com/c'),
            $this->externalImage('https://cdn.example.org/img.png'),
        ]);

        $this->assertSame(0, $this->verifier->verify($this->auditId));
    }

    public function test_probes_each_unique_external_url_once_across_all_pages(): void
    {
        $this->saveWithLinks('https://example.com/a', [
            $this->externalAnchor('https://twitter.com/user'),
            $this->externalAnchor('https://github.com/user'),
        ]);
        $this->saveWithLinks('https://example.com/b', [
            $this->externalAnchor('https://twitter.com/user'), // same as above
            $this->externalAnchor('https://linkedin.com/in/user'),
        ]);

        $probed = $this->verifier->verify($this->auditId);

        $this->assertSame(3, $probed, 'twitter should be probed once even though two pages link to it');
        $this->assertTrue($this->external->exists($this->auditId, Url::fromString('https://twitter.com/user')));
        $this->assertTrue($this->external->exists($this->auditId, Url::fromString('https://github.com/user')));
        $this->assertTrue($this->external->exists($this->auditId, Url::fromString('https://linkedin.com/in/user')));
    }

    public function test_skips_urls_that_already_exist_in_the_repository(): void
    {
        $this->saveWithLinks('https://example.com/a', [
            $this->externalAnchor('https://twitter.com/user'),
            $this->externalAnchor('https://github.com/user'),
        ]);

        $this->external->save(
            $this->auditId,
            Url::fromString('https://twitter.com/user'),
            statusCode: 200,
            responseTime: 50.0,
            error: null,
            sourcePageId: PageId::generate(),
            anchorText: 'prior',
        );

        $this->assertSame(1, $this->verifier->verify($this->auditId), 'twitter is cached, only github is probed');
    }

    public function test_records_transport_failures_with_the_error_message(): void
    {
        $this->saveWithLinks('https://example.com/a', [
            $this->externalAnchor('https://unreachable.test/'),
        ]);
        $this->http->failWith('https://unreachable.test/', 'connection refused');

        $this->verifier->verify($this->auditId);

        $this->assertTrue($this->external->exists($this->auditId, Url::fromString('https://unreachable.test/')));
    }

    /** @param Link[] $links */
    private function saveWithLinks(string $url, array $links): Page
    {
        $page = Page::fromCrawl(
            id: $this->pages->nextId(),
            auditId: $this->auditId,
            url: Url::fromString($url),
            response: new PageResponse(
                statusCode: new HttpStatusCode(200),
                headers: [],
                body: '',
                contentType: 'text/html',
                bodySize: 0,
                responseTime: 50.0,
                finalUrl: null,
            ),
            redirectChain: null,
            crawlDepth: 0,
        );

        if ($links !== []) {
            $page->enrichWithLinks($links);
        }

        $this->pages->save($page);

        return $page;
    }

    private function externalAnchor(string $url): Link
    {
        return new Link(
            targetUrl: Url::fromString($url),
            type: LinkType::ANCHOR,
            anchorText: null,
            relation: LinkRelation::FOLLOW,
            isInternal: false,
        );
    }

    private function internalAnchor(string $url): Link
    {
        return new Link(
            targetUrl: Url::fromString($url),
            type: LinkType::ANCHOR,
            anchorText: null,
            relation: LinkRelation::FOLLOW,
            isInternal: true,
        );
    }

    private function externalImage(string $url): Link
    {
        return new Link(
            targetUrl: Url::fromString($url),
            type: LinkType::IMAGE,
            anchorText: null,
            relation: LinkRelation::FOLLOW,
            isInternal: false,
        );
    }
}
