<?php

namespace Tests\Unit;

use App\Models\SubmissionFormItem;
use Filament\Forms\Components\TextInput;
use Tests\TestCase;

class SubmissionFormItemTest extends TestCase
{
    public function test_type_is_cast_to_integer_from_raw_database_value(): void
    {
        $item = new SubmissionFormItem;
        $item->setRawAttributes([
            'type' => '1',
        ]);

        $this->assertSame(SubmissionFormItem::TYPE_TEXT, $item->type);
    }

    public function test_get_form_field_handles_raw_string_type(): void
    {
        $item = new class extends SubmissionFormItem
        {
            protected function fieldText(): TextInput
            {
                return TextInput::make('test');
            }
        };
        $item->setRawAttributes([
            'id' => 1,
            'type' => '1',
            'required' => false,
        ]);

        $this->assertInstanceOf(TextInput::class, $item->getFormField());
    }
}
