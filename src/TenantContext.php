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

    public static function get(): ?int
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return null;
        }

        $cacheKey = 'tenant_domain_' . md5($host);
        $tenantId = CacheFactory::get()->get($cacheKey);
        if ($tenantId !== null) {
            return (int) $tenantId;
        }

        $tenantId = TenantRpcService::domainToTenantId($host);
        if ($tenantId) {
            CacheFactory::get()->put($cacheKey, $tenantId, self::CACHE_TTL);
        }
        return $tenantId === null ? null : $tenantId;
    }
}
