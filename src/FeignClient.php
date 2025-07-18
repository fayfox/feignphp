<?php
namespace Kuabound\FeignPHP;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Kuabound\FeignPHP\NacosClient;
use Kuabound\FeignPHP\ResponseResult;
use Kuabound\FeignPHP\FeignClientException;

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

    public function get(string $path, array $params = [], array $headers = [])
    {
        return $this->request($path, $params, null, 'GET', $headers)->getData();
    }

    public function post(string $path, $body = null, array $params = [], array $headers = [])
    {
        return $this->request($path, $params, $body, 'POST', $headers)->getData();
    }

    public function put(string $path, $body = null, array $params = [], array $headers = [])
    {
        return $this->request($path, $params, $body, 'PUT', $headers)->getData();
    }

    public function delete(string $path, array $params = [], array $headers = [])
    {
        return $this->request($path, $params, null, 'DELETE', $headers)->getData();
    }
} 