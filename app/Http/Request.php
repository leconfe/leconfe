<?php

namespace App\Http;

class Request extends \Illuminate\Http\Request
{
    public function initialize(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null): void
    {
        parent::initialize($query, $request, $attributes, $cookies, $files, $server, $content);
        if (LECONFE_SUBDIR) {
            $this->baseUrl = '/'.LECONFE_SUBDIR;
        }
    }

    public function duplicate(?array $query = null, ?array $request = null, ?array $attributes = null, ?array $cookies = null, ?array $files = null, ?array $server = null): static
    {
        $dup = parent::duplicate($query, $request, $attributes, $cookies, $files, $server);
        if (LECONFE_SUBDIR) {
            $dup->baseUrl = $this->baseUrl;
        }

        return $dup;
    }
}
