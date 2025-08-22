<?php

namespace App\Managers;

use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class MetaTagManager
{
    protected array $metas = [];

    /**
     * Add a meta tag.
     * You can specify either 'name' or 'property' (or both).
     */
    public function add(?string $name = null, ?string $content = null, ?string $property = null): self
    {
        $meta = [
            'content' => e($content),
        ];

        if ($name) {
            $meta['name'] = $name;
        }

        if ($property) {
            $meta['property'] = $property;
        }

        $this->metas[] = $meta;

        return $this;
    }

    public function all(): Collection
    {
        return collect($this->metas);
    }

    public function render(): HtmlString
    {
        return new HtmlString(
            $this->all()
                ->map(function ($meta) {
                    $attributes = [];
                    if (isset($meta['name'])) {
                        $attributes[] = 'name="' . $meta['name'] . '"';
                    }
                    if (isset($meta['property'])) {
                        $attributes[] = 'property="' . $meta['property'] . '"';
                    }
                    $attributes[] = 'content="' . $meta['content'] . '"';

                    return '<meta ' . implode(' ', $attributes) . '>';
                })
                ->implode("\n")
        );
    }
}
