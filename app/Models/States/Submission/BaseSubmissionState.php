<?php

namespace App\Models\States\Submission;

use App\Models\States\Interfaces\SubmissionStateInterface;
use App\Models\Submission;

class BaseSubmissionState implements SubmissionStateInterface
{
    public function __construct(public Submission $submission) {}

    public function fulfill(): void
    {
        throw new \Exception('Cannot fulfill submission');
    }

    public function sendForReview(): void
    {
        throw new \Exception('Cannot send submission for review');
    }

    public function approvePayment(): void
    {
        throw new \Exception('Cannot approve payment');
    }

    public function declinePayment(): void
    {
        throw new \Exception('Cannot decline payment');
    }

    public function sendToEditing(): void
    {
        throw new \Exception('Cannot accept');
    }

    public function sendToPresentation(): void
    {
        throw new \Exception('Cannot send to presentation');
    }

    public function publish(): void
    {
        throw new \Exception('Cannot publish');
    }

    public function unpublish(): void
    {
        throw new \Exception('Cannot unpublish');
    }

    public function decline(): void
    {
        throw new \Exception('Cannot decline');
    }

    public function acceptAndSkipReview(): void
    {
        throw new \Exception('Cannot skip review');
    }

    public function requestRevision(): void
    {
        throw new \Exception('Cannot request revision');
    }

    public function withdraw(): void
    {
        throw new \Exception('Cannot withdraw');
    }
}
