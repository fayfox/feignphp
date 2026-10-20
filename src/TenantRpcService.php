<?php

namespace Kuabound\FeignPHP;

/**
 * 租户RPC
 */
class TenantRpcService
{
    public static function domainToTenantId(string $domain): ?int
    {
        return self::getClient()->get("tenant/domain/{$domain}/toTenantId");
    }

    private static function getClient(): FeignClient
    {
        return FeignClient::make('kb-base-account');
    }
}