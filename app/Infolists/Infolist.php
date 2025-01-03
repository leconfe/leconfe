<?php

namespace App\Infolists;

use App\Facades\Hook;
use Closure;
use Filament\Infolists\Components\Concerns\HasId;
use Illuminate\Support\Str;

class Infolist extends \Filament\Infolists\Infolist
{
    use HasId;

    /**
     * @param  array<Component> | Closure  $components
     */
    public function components(array|Closure $components): static
    {
        if ($this->getId()) {
            Hook::call('Forms::Form::components::'.Str::camel($this->getId()), [&$components, $this]);
        }

        return parent::components($components);
    }
}
