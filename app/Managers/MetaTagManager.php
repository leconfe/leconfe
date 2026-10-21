<?php

namespace App\Managers;

use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class MetaTagManager
{
    protected array $metas = [];

    /**
     * Add a meta tag.
     * You can specify either 'name' or 'property' (or both), and the language of the
     * content as `xml:lang`.
     */
    public function add(?string $name = null, ?string $content = null, ?string $property = null, ?string $xmlLang = null): self
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

        if ($xmlLang) {
            $meta['xml:lang'] = e($xmlLang);
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
                        $attributes[] = 'name="'.$meta['name'].'"';
                    }
                    if (isset($meta['property'])) {
                        $attributes[] = 'property="'.$meta['property'].'"';
                    }
                    $attributes[] = 'content="'.$meta['content'].'"';
                    if (isset($meta['xml:lang'])) {
                        $attributes[] = 'xml:lang="'.$meta['xml:lang'].'"';
                    }

                    return '<meta '.implode(' ', $attributes).'>';
                })
                ->implode("\n")
        );
    }
}
