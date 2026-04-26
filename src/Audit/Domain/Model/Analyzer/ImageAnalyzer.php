<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Link;
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
        $missingDimensions = [];

        foreach ($images as $image) {
            $alt = $image->anchorText();

            if ($alt === null || trim($alt) === '') {
                $missingAlt++;
            } elseif (mb_strlen($alt) > self::ALT_MAX_LENGTH) {
                $page->addIssue(new Issue(
                    id: IssueId::generate(),
                    category: IssueCategory::CONTENT,
                    severity: IssueSeverity::INFO,
                    code: 'img_alt_too_long',
                    message: sprintf('Image alt is %d chars. Screen readers may truncate past ~%d; tighten for accessibility (not a direct SEO factor).', mb_strlen($alt), self::ALT_MAX_LENGTH),
                    context: $image->targetUrl()->toString(),
                ));
            }

            if (!$image->hasDimensions()) {
                $missingDimensions[] = $image;
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

        if ($missingDimensions !== []) {
            $sample = array_map(
                static fn(Link $img) => $img->targetUrl()->toString(),
                array_slice($missingDimensions, 0, 5),
            );
            $count = count($missingDimensions);

            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::NOTICE,
                code: 'image_missing_dimensions',
                message: sprintf('%d image(s) missing width/height attributes (causes layout shift, hurts CLS).', $count),
                context: implode(', ', $sample) . ($count > 5 ? sprintf(' (+%d more)', $count - 5) : ''),
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::CONTENT;
    }
}
