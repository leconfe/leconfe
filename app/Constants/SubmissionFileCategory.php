<?php

namespace App\Constants;

final class SubmissionFileCategory
{
    public const ABSTRACT_FILES = 'abstract-files';

    public const SUPPLEMENTARY_FILES = 'supplementary-files';

    public const REVIEW_FILES = 'review-files';

    /**
     * @deprecated Use REVIEW_FILES for files uploaded to the peer-review stage.
     */
    public const PAPER_FILES = self::REVIEW_FILES;

    public const REVIEWER_FILES = 'reviewer-files';

    public const PRESENTATION_FILES = 'presentation-files';

    public const REVISION_FILES = 'revision-files';

    public const REVIEWER_ASSIGNED_FILES = 'reviewer-assigned-files';

    public const EDITING_DRAFT_FILES = 'editing-draft-files';

    public const EDITED_FILES = 'edited-files';

    public const GALLEY_FILES = 'galley-files';
}
