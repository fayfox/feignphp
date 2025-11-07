<?php
namespace Kuabound\FeignPHP;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class NacosClient
{
    private string $nacosAddr;
    private string $namespaceId;
    private Client $httpClient;
    private int $cacheTtl;

    public function __construct()
    {
        $this->nacosAddr = getenv('NACOS_ADDR') ?: '127.0.0.1:8848';
        $this->namespaceId = getenv('NACOS_NAMESPACE_ID') ?: 'public';
        $this->httpClient = new Client();
        $this->cacheTtl = 600; // 默认缓存秒数
    }

    /**
     * 获取服务实例（随机一个）
     * @param string $serviceName
     * @return array|null
     */
    public function getServiceInstance(string $serviceName): ?array
    {
        $cacheKey = 'nacos_service_' . md5($serviceName . $this->namespaceId);
        $hosts = Cache::get($cacheKey);
        if (empty($hosts)) {
            // 缓存无数据，实时查询 nacos
            $url = sprintf(
                'http://%s/nacos/v3/client/ns/instance/list?serviceName=%s&namespaceId=%s',
                $this->nacosAddr,
                urlencode($serviceName),
                urlencode($this->namespaceId)
            );
            $resp = $this->httpClient->get($url, ['http_errors' => false]);
            $data = json_decode($resp->getBody()->getContents(), true);
            if (!isset($data['data']) || !is_array($data['data']) || count($data['data']) === 0) {
                $hosts = [];
            } else {
                // 只缓存 ip+port
                $hosts = array_values(array_filter(array_map(function($item) {
                    return (isset($item['ip']) && isset($item['port']) && !empty($item['weight'])) ? [
                        'ip' => $item['ip'],
                        'port' => $item['port'],
                        'weight' => $item['weight'],
                    ] : null;
                }, $data['data'])));
                Cache::put($cacheKey, $hosts, $this->cacheTtl);
            }
        }
        if (empty($hosts)) {
            return null;
        }
        $instance = $this->weightedRandomSelect($hosts);
        if (!isset($instance['ip']) || !isset($instance['port'])) {
            return null;
        }

        return [
            'ip' => $instance['ip'],
            'port' => $instance['port'],
        ];
    }

    /**
     * 根据权重加权随机选择实例
     * @param array $hosts
     * @return array|null
     */
    private function weightedRandomSelect(array $hosts): ?array
    {
        if (empty($hosts)) {
            return null;
        }

        $totalWeight = array_sum(array_column($hosts, 'weight'));
        if ($totalWeight < 1) {
            // 无可用实例
            return null;
        }
        $randomNumber = mt_rand(1, $totalWeight);

        $currentWeight = 0;
        foreach ($hosts as $host) {
            $currentWeight += $host['weight'];
            if ($randomNumber <= $currentWeight) {
                return $host;
            }
        }

        // fallback to the first element
        return $hosts[0];
    }


    /**
     * 清除指定服务的缓存
     * @param string $serviceName
     */
    public function clearServiceCache(string $serviceName): void
    {
        $cacheKey = 'nacos_service_' . md5($serviceName . $this->namespaceId);
        Cache::forget($cacheKey);
    }
} 