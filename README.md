# Kuabound\FeignPHP\FeignClient 用法示例

```php
use Kuabound\FeignPHP\FeignClient;
use Kuabound\FeignPHP\ResponseResult;

// 假设已设置好 NACOS_ADDR、NACOS_NAMESPACE_ID 环境变量

$client = FeignClient::make('your-service-name');

// 获取业务数据
try {
    // GET 请求
    $data = $client->get('/api/path', ['foo' => 'bar']);
    
    // POST 请求
    $data = $client->post('/api/path', ['key' => 'value']);
    // 处理 $data
    // ...
} catch (\Kuabound\FeignPHP\FeignClientException $e) {
    // 自定义异常处理，或直接不套try catch，往外抛，看业务场景
}

```
