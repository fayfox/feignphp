<?php
namespace Kuabound\FeignPHP;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class FeignClient
{
    private NacosClient $nacosClient;
    private string $serviceName;
    private static ?NacosClient $sharedNacosClient = null;

    public function __construct(string $serviceName)
    {
        if (!self::$sharedNacosClient) {
            self::$sharedNacosClient = new NacosClient();
        }
        $this->nacosClient = self::$sharedNacosClient;
        $this->serviceName = $serviceName;
    }

    /**
     * 获取 FeignClient 实例的工厂方法
     */
    public static function make(string $serviceName): self
    {
        return new self($serviceName);
    }

    /**
     * 发起请求
     */
    public function request(string $path, array $params = [], $body = null, string $method = 'GET', array $headers = []): ResponseResult
    {
        $serviceName = $this->serviceName;
        $instance = $this->nacosClient->getServiceInstance($serviceName);
        if (!$instance || empty($instance['ip']) || empty($instance['port'])) {
            throw new FeignClientException('No available instance for service: ' . $serviceName);
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        $url = 'http://' . $instance['ip'] . ':' . $instance['port'] . $path;

        $http_traceparent = $_SERVER['HTTP_TRACEPARENT'] ?? '';
        // 添加request_id
        if($http_traceparent){
            $headers['traceparent'] = $http_traceparent;
        }else{
            // 添加traceparent
            if (empty($headers['traceparent'])) {
                // 检查是否存在 request_id
                $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
                if (!empty($requestId)) {
                    // 确保 requestId 至少有16个字符，否则用0补齐
                    if (strlen($requestId) >= 16) {
                        $suffix = substr($requestId, -16);
                    } else {
                        $suffix = str_pad($requestId, 16, '0', STR_PAD_LEFT);
                    }
                    $headers['traceparent'] = "00-{$requestId}-{$suffix}-00";
                } else {
                    // 随机生成traceparent: version(2)-trace-id(32)-parent-id(16)-flags(2)
                    try {
                        $hex32 = bin2hex(random_bytes(16));
                        $hex16 = bin2hex(random_bytes(8));
                        $headers['traceparent'] = "00-{$hex32}-{$hex16}-00";
                    } catch (\Exception $e) {
                        // fallback机制：使用uniqid确保不会因随机数生成失败而中断
                        $fallbackTraceId = str_pad(dechex(crc32(uniqid())), 32, '0', STR_PAD_LEFT);
                        $fallbackParentId = str_pad(dechex(mt_rand()), 16, '0', STR_PAD_LEFT);
                        $headers['traceparent'] = "00-{$fallbackTraceId}-{$fallbackParentId}-00";
                    } catch (\Error $e) {
                        $fallbackTraceId = str_pad(dechex(crc32(uniqid())), 32, '0', STR_PAD_LEFT);
                        $fallbackParentId = str_pad(dechex(mt_rand()), 16, '0', STR_PAD_LEFT);
                        $headers['traceparent'] = "00-{$fallbackTraceId}-{$fallbackParentId}-00";
                    }
                }
            }
        }


        //租户id
        $kb_tenant_id = env('KB_TENANT_ID') ?? 0;
        if($kb_tenant_id){
            $headers['Kb-Tenant-Id'] = $kb_tenant_id;
        }

        $client = new Client();
        $options = [
            'headers' => $headers,
            'http_errors' => false,
            'query' => $params,
        ];
        if ($body !== null) {
            if (is_array($body)) {
                $options['json'] = $body;
            } else {
                $options['body'] = $body;
            }
        }
        try {
            $response = $client->request(strtoupper($method), $url, $options);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                // 有 HTTP 返回但非 2xx，直接报错
                throw new FeignClientException("HTTP request failed with status $status");
            }
            return new ResponseResult($status, $response->getBody()->getContents(), $response->getHeaders());
        } catch (GuzzleException $e) {
            // 网络异常等才清缓存重试
            $this->nacosClient->clearServiceCache($serviceName);
            $instance = $this->nacosClient->getServiceInstance($serviceName);
            if (!$instance || empty($instance['ip']) || empty($instance['port'])) {
                throw new FeignClientException('No available instance for service: ' . $serviceName);
            }
            $url = 'http://' . $instance['ip'] . ':' . $instance['port'] . $path;
            try {
                $response = $client->request(strtoupper($method), $url, $options);
                $status = $response->getStatusCode();
                if ($status < 200 || $status >= 300) {
                    throw new FeignClientException("HTTP request failed with status $status");
                }
                return new ResponseResult($status, $response->getBody()->getContents(), $response->getHeaders());
            } catch (GuzzleException $e) {
                return new ResponseResult(null, null, [], $e->getMessage());
            }
        }
    }

    public function get(string $path, array $params = [], array $headers = []): ResponseResult
    {
        return $this->request($path, $params, null, 'GET', $headers)->getData();
    }

    public function post(string $path, $body = null, array $params = [], array $headers = []): ResponseResult
    {
        return $this->request($path, $params, $body, 'POST', $headers)->getData();
    }

    public function put(string $path, $body = null, array $params = [], array $headers = []): ResponseResult
    {
        return $this->request($path, $params, $body, 'PUT', $headers)->getData();
    }

    public function delete(string $path, array $params = [], array $headers = []): ResponseResult
    {
        return $this->request($path, $params, null, 'DELETE', $headers)->getData();
    }
} 