<?php
namespace Kuabound\FeignPHP;

class ResponseResult
{
    public ?int $status;
    public $body;
    public array $headers;
    public ?string $error;

    public function __construct(?int $status, $body, array $headers, ?string $error = null)
    {
        $this->status = $status;
        $this->body = $body;
        $this->headers = $headers;
        $this->error = $error;
    }

    public function isSuccess(): bool
    {
        if ($this->status === null || $this->body === null) {
            return false;
        }
        $data = json_decode($this->body, true);
        return is_array($data) && isset($data['code']) && $data['code'] === 0;
    }

    public function getBody(): ?array
    {
        if ($this->body === null) {
            return null;
        }
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : null;
    }

    public function getData()
    {
        $body = $this->getBody();
        if (!is_array($body) || !isset($body['code'])) {
            throw new \RuntimeException('RPC返回数据结构异常，缺少code: ' . $this->body);
        }
        if ($body['code'] !== 0) {
            $msg = "[{$body['code']}] " . $body['message'] ?? 'Unknown error';
            if (isset($body['data']) && is_array($body['data'])) {
                $msg .= ' | data: ' . $this->body;
            }
            throw new \RuntimeException($msg, $body['code']);
        }
        if (!array_key_exists('data', $body)) {
            throw new \RuntimeException('RPC返回数据结构异常，缺少data: '  . $this->body);
        }
        return $body['data'];
    }
} 