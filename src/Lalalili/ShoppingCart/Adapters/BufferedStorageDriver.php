<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Adapters;

use Lalalili\ShoppingCart\Contracts\StorageDriverInterface;

/**
 * 批次寫入緩衝:寫入先落在記憶體 overlay,讀取優先讀 overlay、未命中
 * 才穿透到內層 driver;flush() 時每個 key 只對內層寫一次。
 *
 * 供 {@see \Lalalili\ShoppingCart\Cart::batch()} 使用,把「迴圈內多次
 * remove/add 各自觸發整包序列化寫入」壓縮為批次結束的一次寫入。
 */
final class BufferedStorageDriver implements StorageDriverInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $buffer = [];

    public function __construct(private readonly StorageDriverInterface $inner)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->buffer)) {
            return $this->buffer[$key];
        }

        return $this->inner->get($key, $default);
    }

    public function put(string $key, mixed $value): void
    {
        $this->buffer[$key] = $value;
    }

    /**
     * @param list<string> $toleratedKeys 寫入失敗可容忍的 key(例:cart version;
     *                                    legacy storage adapter 只接受 cart payload,
     *                                    與非批次時 touchCartVersion 的 try/catch 語意一致)
     */
    public function flush(array $toleratedKeys = []): void
    {
        foreach ($this->buffer as $key => $value) {
            try {
                $this->inner->put($key, $value);
            } catch (\Throwable $exception) {
                if (! in_array($key, $toleratedKeys, true)) {
                    throw $exception;
                }
            }
        }

        $this->buffer = [];
    }
}
