<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class ResponseOptionsRepeater extends Repeater
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Response Options')
            ->schema([
                Hidden::make('position'),
                MultilanguageComponent::make([
                    TextInput::make('label')
                        ->label(__('scheduled_conference.option'))
                        ->required(),
                ]),
            ])
            ->defaultItems(1)
            ->reorderable()
            ->required()
            ->validationMessages([
                'required' => 'The :attribute field is required when Item Type is Checkbox, Radio Button, or Drop down',
            ]);
    }
}
