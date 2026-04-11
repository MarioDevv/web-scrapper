<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class SecurityHeaderAnalyzer implements Analyzer
{
    private const array REQUIRED_HEADERS = [
        'csp_missing' => [
            'header' => 'Content-Security-Policy',
            'message' => 'Missing Content-Security-Policy header (XSS protection).',
        ],
        'x_frame_missing' => [
            'header' => 'X-Frame-Options',
            'message' => 'Missing X-Frame-Options header (clickjacking protection).',
        ],
        'x_content_type_missing' => [
            'header' => 'X-Content-Type-Options',
            'message' => 'Missing X-Content-Type-Options header (prevents MIME sniffing).',
        ],
    ];

    private const array SECURE_REFERRER_POLICIES = [
        'no-referrer',
        'no-referrer-when-downgrade',
        'strict-origin',
        'strict-origin-when-cross-origin',
    ];

    public function analyze(Page $page): void
    {
        if (!$page->isHtml() || !$page->response()->statusCode()->isSuccessful()) {
            return;
        }

        foreach (self::REQUIRED_HEADERS as $code => $config) {
            $value = $page->response()->header($config['header']);
            if ($value === null || trim($value) === '') {
                $page->addIssue(new Issue(
                    id: IssueId::generate(),
                    category: IssueCategory::DIRECTIVES,
                    severity: IssueSeverity::NOTICE,
                    code: $code,
                    message: $config['message'],
                ));
            }
        }

        $this->checkReferrerPolicy($page);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::DIRECTIVES;
    }

    private function checkReferrerPolicy(Page $page): void
    {
        $value = $page->response()->header('Referrer-Policy');

        if ($value === null || trim($value) === '') {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::NOTICE,
                code: 'referrer_policy_missing',
                message: 'Missing Referrer-Policy header (navigation data protection).',
            ));
            return;
        }

        $policy = strtolower(trim($value));
        if (!in_array($policy, self::SECURE_REFERRER_POLICIES, true)) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::NOTICE,
                code: 'referrer_policy_insecure',
                message: sprintf('Referrer-Policy "%s" may leak data across origins.', $policy),
                context: 'Recommended: strict-origin-when-cross-origin',
            ));
        }
    }
}
