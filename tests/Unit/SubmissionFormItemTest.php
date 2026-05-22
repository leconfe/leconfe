<?php

namespace Tests\Unit;

use App\Models\SubmissionFormItem;
use PHPUnit\Framework\TestCase;

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
}
