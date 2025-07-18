# Kuabound\FeignPHP\FeignClient 用法示例

```php
use Kuabound\FeignPHP\FeignClient;
use Kuabound\FeignPHP\ResponseResult;

// 假设已设置好 NACOS_HOST、NACOS_PORT、NACOS_NAMESPACE_ID 环境变量

$client = FeignClient::make('your-service-name');

// GET 请求
$result = $client->get('/api/path', ['foo' => 'bar']);

// POST 请求
$result = $client->post('/api/path', ['key' => 'value']);

// 获取业务数据
try {
    $data = $result->getData();
    // 处理 $data
    // ...
} catch (\Kuabound\FeignPHP\FeignClientException e) {
    // 自定义异常处理，或直接不套try catch，往外抛，看业务场景
}

// 获取原始 HTTP 状态码、头部
$status = $result->status;
$headers = $result->headers;
```
