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
            throw new \RuntimeException('Invalid response structure');
        }
        if ($body['code'] !== 0) {
            $msg = isset($body['message']) ? $body['message'] : 'Unknown error';
            if (isset($body['data']) && is_array($body['data'])) {
                $msg .= ' | data: ' . json_encode($body['data'], JSON_UNESCAPED_UNICODE);
            }
            throw new \RuntimeException($msg, $body['code']);
        }
        if (!array_key_exists('data', $body) || !is_array($body['data'])) {
            throw new \RuntimeException('Invalid response structure');
        }
        return $body['data'];
    }
} 