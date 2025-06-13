<?php

namespace App\Classes;

class Setting
{
    protected string $prefix = 'settings_';

    public function all(): array
    {
        return $this->getAllData();
    }

    public function update($data): void
    {
        $this->setAllData($data);
    }

    public function get($key, $default = null): mixed
    {
        return $this->getData($key) ?? $default;
    }

    public function set($key, $value): void
    {
        $this->setData($key, $value);
    }

    protected function getData($key): mixed
    {
        $prefixedKey = $this->prefix.$key;

        return app()->getSite()->getMeta($prefixedKey);
    }

    protected function setData($key, $value): void
    {
        $prefixedKey = $this->prefix.$key;

        app()->getSite()->setMeta($prefixedKey, $value);
    }

    protected function getAllData(): array
    {
        $data = app()->getSite()->getAllMeta();
        $settings = [];

        foreach ($data as $key => $value) {
            if (strpos($key, $this->prefix) === 0) {
                $settings[str_replace($this->prefix, '', $key)] = $value;
            }
        }

        return $settings;
    }

    protected function setAllData(array $data): void
    {
        $prefixedData = [];

        foreach ($data as $key => $value) {
            $prefixedData[$this->prefix.$key] = $value;
        }

        app()->getSite()->setManyMeta($prefixedData);
    }
}
