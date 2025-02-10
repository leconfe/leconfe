<?php

namespace App\Models\States\Interfaces;

interface SubmissionStateInterface
{
    public function fulfill(): void;

    public function acceptAbstract(): void;

    public function approvePayment(): void;

    public function declinePayment(): void;

    public function sendToEditing(): void;

    public function sendToPresentation(): void;

    public function publish(): void;

    public function unpublish(): void;

    public function decline(): void;

    public function withdraw(): void;

    public function acceptAndSkipReview(): void;

    public function requestRevision(): void;
}
