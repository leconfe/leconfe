<?php

namespace App\Models\Concerns;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait InteractsWithPayment
{
    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'model');
    }
}
