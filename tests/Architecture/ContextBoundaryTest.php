<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ContextBoundaryTest extends TestCase
{
    private const string SRC = __DIR__ . '/../../src';

    /** @return array<string, string> absolute path => file contents */
    private function phpFilesUnder(string $relativeDir): array
    {
        $root = self::SRC . '/' . $relativeDir;
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        return $files;
    }

    /**
     * @param string[] $forbiddenPrefixes
     * @param string[] $allowedSubpaths Relative-to-src paths where the rule
     *                                  is relaxed (e.g. ACL adapters that
     *                                  legitimately bridge two contexts).
     */
    private function assertNoImports(string $dir, array $forbiddenPrefixes, array $allowedSubpaths = []): void
    {
        $violations = [];
        $allowedAbsolute = array_map(
            static fn (string $sub): string => self::SRC . '/' . $sub,
            $allowedSubpaths,
        );

        foreach ($this->phpFilesUnder($dir) as $path => $code) {
            foreach ($allowedAbsolute as $allowed) {
                if (str_starts_with($path, $allowed)) {
                    continue 2;
                }
            }
            if (preg_match_all('/^use\s+([^;]+);/m', $code, $m) === false) {
                continue;
            }
            foreach ($m[1] as $imported) {
                $imported = ltrim(trim($imported), '\\');
                foreach ($forbiddenPrefixes as $prefix) {
                    if (str_starts_with($imported, $prefix)) {
                        $violations[] = sprintf(
                            '%s imports %s (rule: nothing under src/%s may import %s*)',
                            $path,
                            $imported,
                            $dir,
                            $prefix,
                        );
                    }
                }
            }
        }

        // Always asserts (project runs failOnRisky="true"): an empty
        // violation list is the green state regardless of file count.
        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_crawling_never_imports_auditing(): void
    {
        $this->assertNoImports('Crawling', ['SeoSpider\\Auditing\\']);
    }

    public function test_auditing_never_imports_crawling(): void
    {
        // Auditing/Infrastructure/Acl/* is the legitimate translation seam
        // where Auditing-side ports are implemented by wrapping Crawling
        // collaborators (Vernon, DDD Distilled p56 — Anticorruption Layer).
        // Auditing/Infrastructure/Delivery/* may dispatch Crawling jobs or
        // integration events as part of UI orchestration (e.g. Livewire
        // starting a crawl run); this is the only place where the
        // framework cross-wires the two contexts.
        $this->assertNoImports(
            'Auditing',
            ['SeoSpider\\Crawling\\'],
            ['Auditing/Infrastructure/Acl/', 'Auditing/Infrastructure/Delivery/'],
        );
    }

    public function test_crawling_domain_is_hexagonal(): void
    {
        $this->assertNoImports('Crawling/Domain', [
            'SeoSpider\\Crawling\\Application\\',
            'SeoSpider\\Crawling\\Infrastructure\\',
        ]);
    }

    public function test_auditing_domain_is_hexagonal(): void
    {
        $this->assertNoImports('Auditing/Domain', [
            'SeoSpider\\Auditing\\Application\\',
            'SeoSpider\\Auditing\\Infrastructure\\',
        ]);
    }
}
