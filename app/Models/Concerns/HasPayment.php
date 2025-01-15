<?php

namespace App\Models\Concerns;

use App\Models\Conference;
use App\Models\PaymentCompleted;
use App\Models\PaymentQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\App;

trait HasPayment
{
    public function paymentCompleted(): MorphOne
    {
        return $this->morphOne(PaymentCompleted::class, 'model');
    }

    public function paymentQueue(): MorphOne
    {
        return $this->morphOne(PaymentQueue::class, 'model');
    }
}
