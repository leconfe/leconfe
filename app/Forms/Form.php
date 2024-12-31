<?php

namespace App\Forms;

use App\Facades\Hook;
use Closure;
use Filament\Forms\Components\Concerns\HasId;

class Form extends \Filament\Forms\Form 
{
    use HasId;

    /**
     * @param  array<Component> | Closure  $components
     */
    public function components(array | Closure $components): static
    {
        if($this->getId()){
            Hook::call('Forms::Form::components::' . Str::$this->getId(), [&$components, $this]);
        }

        return parent::components($components);
    }

    public function toHtml(): string
    {
        return $this->render()->render();
    }
}