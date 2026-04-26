<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class SocialMetadataAnalyzer implements Analyzer
{
    public function analyze(Page $page): void
    {
        if (!$page->isHtml() || !$page->response()->statusCode()->isSuccessful()) {
            return;
        }

        $metadata = $page->metadata();
        if ($metadata === null) {
            return;
        }

        $ogMissing = [];
        if (!self::isPresent($metadata->ogTitle())) {
            $ogMissing[] = 'og:title';
        }
        if (!self::isPresent($metadata->ogDescription())) {
            $ogMissing[] = 'og:description';
        }
        if (!self::isPresent($metadata->ogImage())) {
            $ogMissing[] = 'og:image';
        }

        if ($ogMissing !== []) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                code: 'open_graph_incomplete',
                message: sprintf('Open Graph metadata is incomplete: missing %s.', implode(', ', $ogMissing)),
            ));
        }

        $twitterMissing = [];
        if (!self::isPresent($metadata->twitterCard())) {
            $twitterMissing[] = 'twitter:card';
        }
        if (!self::isPresent($metadata->twitterTitle())) {
            $twitterMissing[] = 'twitter:title';
        }
        if (!self::isPresent($metadata->twitterDescription())) {
            $twitterMissing[] = 'twitter:description';
        }
        if (!self::isPresent($metadata->twitterImage())) {
            $twitterMissing[] = 'twitter:image';
        }

        if ($twitterMissing !== []) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                code: 'twitter_card_incomplete',
                message: sprintf('Twitter Card metadata is incomplete: missing %s.', implode(', ', $twitterMissing)),
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::METADATA;
    }

    private static function isPresent(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
