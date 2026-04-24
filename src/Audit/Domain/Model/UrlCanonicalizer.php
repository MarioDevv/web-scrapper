<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

/**
 * Canonicalizes URLs to a form that represents the same logical resource
 * across superficial variations (tracking parameters, default ports, fragments,
 * trailing slash on the root path).
 *
 * Lives in the domain because "two variants identify the same resource" is a
 * policy of the SEO audit domain, not a property of the Url value object itself:
 * a Url only knows RFC 3986 semantics, while which query parameters count as
 * noise is knowledge about the web marketing world that evolves over time.
 */
final readonly class UrlCanonicalizer
{
    private const array DEFAULT_TRACKING_PARAMS = [
        'fbclid',
        'gclid',
        'gclsrc',
        'dclid',
        'msclkid',
        'yclid',
        'mc_cid',
        'mc_eid',
        '_ga',
        '_gl',
        'igshid',
        'mkt_tok',
    ];

    private const array DEFAULT_TRACKING_PARAM_PREFIXES = ['utm_'];

    /** @var list<string> */
    private array $trackingParams;

    /** @var list<string> */
    private array $trackingParamPrefixes;

    /**
     * @param list<string>|null $trackingParams     lowercase parameter names to strip outright
     * @param list<string>|null $trackingParamPrefixes lowercase prefixes (e.g. "utm_") whose matches are stripped
     */
    public function __construct(
        ?array $trackingParams = null,
        ?array $trackingParamPrefixes = null,
    ) {
        $this->trackingParams = $trackingParams ?? self::DEFAULT_TRACKING_PARAMS;
        $this->trackingParamPrefixes = $trackingParamPrefixes ?? self::DEFAULT_TRACKING_PARAM_PREFIXES;
    }

    public function canonicalize(Url $url): Url
    {
        $result = $url->withoutFragment();

        $scheme = strtolower($result->scheme());
        $port = $result->port();
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $result = $result->withPort(null);
        }

        if ($result->path() === '' && $result->host() !== '') {
            $result = $result->withPath('/');
        }

        $query = $result->query();
        if ($query !== null && $query !== '') {
            $filtered = $this->stripTrackingParams($query);
            $result = $result->withQuery($filtered === '' ? null : $filtered);
        }

        return $result;
    }

    private function stripTrackingParams(string $query): string
    {
        $kept = [];

        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            $eq = strpos($pair, '=');
            $rawKey = $eq === false ? $pair : substr($pair, 0, $eq);
            $key = strtolower(rawurldecode($rawKey));

            if (in_array($key, $this->trackingParams, true)) {
                continue;
            }

            foreach ($this->trackingParamPrefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    continue 2;
                }
            }

            $kept[] = $pair;
        }

        return implode('&', $kept);
    }
}
