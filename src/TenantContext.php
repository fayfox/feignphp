<?php

namespace Kuabound\FeignPHP;

/**
 * 租户上下文
 */
class TenantContext
{
    /**
     * 域名与租户绑定变化不频繁，缓存久一点，避免每次请求都打账户服务
     */
    private const CACHE_TTL = 300;

    /**
     * 解析域名租户时会再发 Feign 请求，请求里又会读租户，这里挡住重入
     */
    private static bool $resolving = false;

    public static function get(?int $default = null): ?int
    {
        if (self::$resolving) {
            return null;
        }
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return $default;
        }

        $cacheKey = 'tenant_domain_' . md5($host);
        $tenantId = CacheFactory::get()->get($cacheKey);
        if ($tenantId !== null) {
            return (int) $tenantId;
        }

        self::$resolving = true;
        try {
            $tenantId = TenantRpcService::domainToTenantId($host);
        } catch (\Throwable $e) {
            return $default;
        } finally {
            self::$resolving = false;
        }
        if ($tenantId) {
            CacheFactory::get()->put($cacheKey, $tenantId, self::CACHE_TTL);
        }
        return $tenantId === null ? $default : $tenantId;
    }
}
