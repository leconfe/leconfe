<?php

namespace App\Forms\Components;

use Closure;
use Filament\Forms\Components\BaseFileUpload;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Contracts\Support\Arrayable;

class CssFileUpload extends SpatieMediaLibraryFileUpload
{
    /**
     * @param  array<string> | Arrayable | Closure  $types
     */
    public function acceptedFileTypes(array|Arrayable|Closure $types): static
    {
        $this->acceptedFileTypes = $types;

        // $this->rule(static function (BaseFileUpload $component) {
        //     $types = implode(',', ($component->getAcceptedFileTypes() ?? []));

        //     return "mimetypes:{$types}";
        // });

        return $this;
    }
}
