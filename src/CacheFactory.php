<?php

namespace Kuabound\FeignPHP;

use Illuminate\Cache\CacheManager;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;

/**
 * 统一获取缓存仓库：Laravel 环境走 Facade，否则使用文件缓存
 */
class CacheFactory
{
    private static ?Repository $repository = null;

    public static function get(): Repository
    {
        if (self::$repository === null) {
            self::$repository = self::resolve();
        }
        return self::$repository;
    }

    private static function resolve(): Repository
    {
        if (self::isFacadeAvailable()) {
            return Cache::store();
        }

        $container = new Container();
        $container['config'] = [
            'cache.default' => 'file',
            'cache.stores.file' => [
                'driver' => 'file',
                'path' => defined('BASE_PATH') ? BASE_PATH . '/runtimes/cache/project' : __DIR__ . '/storage/cache',
            ],
            'cache.prefix' => 'app_',
        ];
        $container['files'] = new Filesystem();

        return (new CacheManager($container))->store();
    }

    /**
     * 检查 Facade 是否已初始化
     */
    private static function isFacadeAvailable(): bool
    {
        try {
            $instance = Cache::getFacadeRoot();
            return $instance !== null;
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'facade root has not been set')) {
                return false;
            }
            throw $e;
        }
    }
}
