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
        return app()->getCurrentConferenceId() ? app()->getCurrentConference()->getMeta($prefixedKey) : app()->getSite()->getMeta($prefixedKey);
    }

    protected function setData($key, $value): void
    {
        $prefixedKey = $this->prefix.$key;

        if (app()->getCurrentConferenceId()) {
            app()->getCurrentConference()->setMeta($prefixedKey, $value);
        }

        app()->getSite()->setMeta($prefixedKey, $value);
    }

    protected function getAllData(): array
    {
        $data = app()->getCurrentConferenceId() ? app()->getCurrentConference()->getAllMeta() : app()->getSite()->getAllMeta();
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

        if (app()->getCurrentConferenceId()) {
            app()->getCurrentConference()->setManyMeta($prefixedData);

            return;
        }

        app()->getSite()->setManyMeta($prefixedData);
    }
}
