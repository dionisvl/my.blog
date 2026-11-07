<?php

declare(strict_types=1);

namespace App\Article\Validation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Which URLs article content may carry: absolute http(s) URLs and site-relative paths only,
 * plus the allow-list of affiliate hosts used by sponsored-link validation.
 */
final readonly class UrlPolicy
{
    public const string CODE_INVALID = 'invalid_url';

    public const string CODE_SCHEME_NOT_ALLOWED = 'url_scheme_not_allowed';

    private const int MAX_LENGTH = 2048;

    /**
     * Mirrors the ASCII controls, backslash and the Unicode whitespace that JavaScript `\s` matches,
     * as used by contracts/article-blocks/v1/schema.json. Invalid UTF-8 makes preg_match() return false.
     */
    private const string FORBIDDEN_CHARS_PATTERN = '~[\x00-\x20\x7F\\\\\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]~u';

    /**
     * Scheme is case-sensitive, as in schema.json. Inside the authority (host and port only, not path/query)
     * percent signs and non-ASCII bytes are rejected: encoded or fullwidth hosts (e.g. `by%62it.com`,
     * fullwidth dots) would otherwise slip past the affiliate host match, because browsers and DNS normalise them.
     */
    private const string ABSOLUTE_PATTERN = '~^https?://(?:\[[^\]\s/?#@%\x80-\xFF]+\]|[^\s/?#@:\[\]%\x80-\xFF]+)(?::[0-9]{1,5})?(?:[/?#]\S*)?$~';

    /** @var list<string> */
    private array $affiliateHosts;

    /**
     * @param list<string> $affiliateHosts
     */
    public function __construct(
        #[Autowire(param: 'app.article.affiliate_hosts')]
        array $affiliateHosts = [],
    ) {
        $this->affiliateHosts = array_values(array_map(
            static fn (string $host): string => strtolower(ltrim(trim($host), '.')),
            $affiliateHosts,
        ));
    }

    /**
     * @return string|null null when the URL is acceptable, otherwise a stable error code
     */
    public function check(string $url): ?string
    {
        if ('' === $url || \strlen($url) > self::MAX_LENGTH) {
            return self::CODE_INVALID;
        }

        if (0 !== preg_match(self::FORBIDDEN_CHARS_PATTERN, $url)) {
            return self::CODE_INVALID;
        }

        if ('/' === $url[0]) {
            return isset($url[1]) && '/' === $url[1] ? self::CODE_INVALID : null;
        }

        if (1 === preg_match(self::ABSOLUTE_PATTERN, $url)) {
            return null;
        }

        if (1 === preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $scheme) && !\in_array(strtolower($scheme[1]), ['http', 'https'], true)) {
            return self::CODE_SCHEME_NOT_ALLOWED;
        }

        return self::CODE_INVALID;
    }

    /**
     * Lower-cased host of an absolute URL, or null for relative/invalid URLs.
     */
    public function host(string $url): ?string
    {
        if (null !== $this->check($url) || '/' === $url[0]) {
            return null;
        }

        $host = parse_url($url, \PHP_URL_HOST);
        // A trailing dot is the same FQDN, so `bybit.com.` must match the `bybit.com` affiliate host.
        $host = \is_string($host) ? rtrim(strtolower($host), '.') : '';

        return '' !== $host ? $host : null;
    }

    public function isAffiliateHost(?string $host): bool
    {
        if (null === $host) {
            return false;
        }

        foreach ($this->affiliateHosts as $affiliate) {
            if ('' === $affiliate) {
                continue;
            }

            if ($host === $affiliate || str_ends_with($host, '.'.$affiliate)) {
                return true;
            }
        }

        return false;
    }
}
