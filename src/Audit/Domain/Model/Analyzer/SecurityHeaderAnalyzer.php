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
            'message' => 'Falta el encabezado Content-Security-Policy (protección contra XSS).',
        ],
        'x_frame_missing' => [
            'header' => 'X-Frame-Options',
            'message' => 'Falta el encabezado X-Frame-Options (protección contra clickjacking).',
        ],
        'x_content_type_missing' => [
            'header' => 'X-Content-Type-Options',
            'message' => 'Falta el encabezado X-Content-Type-Options (previene MIME sniffing).',
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
                message: 'Falta el encabezado Referrer-Policy (protección de datos en navegación).',
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
                message: sprintf('Referrer-Policy "%s" puede filtrar datos entre orígenes.', $policy),
                context: 'Recomendado: strict-origin-when-cross-origin',
            ));
        }
    }
}
