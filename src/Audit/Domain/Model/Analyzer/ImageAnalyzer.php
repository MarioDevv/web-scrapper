<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\LinkType;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class ImageAnalyzer implements Analyzer
{
    private const int ALT_MAX_LENGTH = 125;

    public function analyze(Page $page): void
    {
        if (!$page->isHtml()) {
            return;
        }

        $images = array_filter(
            $page->links(),
            static fn($link) => $link->type() === LinkType::IMAGE,
        );

        if (count($images) === 0) {
            return;
        }

        $missingAlt = 0;

        foreach ($images as $image) {
            $alt = $image->anchorText();

            if ($alt === null || trim($alt) === '') {
                $missingAlt++;
            } elseif (mb_strlen($alt) > self::ALT_MAX_LENGTH) {
                $page->addIssue(new Issue(
                    id: IssueId::generate(),
                    category: IssueCategory::CONTENT,
                    severity: IssueSeverity::NOTICE,
                    code: 'img_alt_too_long',
                    message: sprintf('Image alt attribute too long (%d characters, max %d).', mb_strlen($alt), self::ALT_MAX_LENGTH),
                    context: $image->targetUrl()->toString(),
                ));
            }
        }

        if ($missingAlt > 0) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::WARNING,
                code: 'img_alt_missing',
                message: sprintf('%d image(s) missing alt attribute.', $missingAlt),
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::CONTENT;
    }
}
