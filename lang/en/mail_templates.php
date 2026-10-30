<?php

return [
    'accept_paper' => [
        'subject' => 'Your submission {{ Submission Title }} on {{ Conference Title }} is accepted.',
        'body' => <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>We have reached a decision regarding your submission with title "{{ Submission Title }}"  to {{ Conference Title }}</p>
            <p>Our decision is to <b>accept the submission</b>.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
            HTML,
    ],
    'decline_abstract' => [
        'subject' => 'Your submission {{ Submission Title }} on {{ Conference Title }} is declined.',
        'body' => <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>We have reached a decision regarding your submission with title "{{ Submission Title }}"  to {{ Conference Title }}</p>
            <p>Our decision is to <b>decline the submission</b>.</p>
            <p>Thank you for considering {{ Conference Title }} as a venue for your work.</p>
            HTML,
    ],
    'decline_paper' => [
        'subject' => 'Your submission {{ Submission Title }} on {{ Conference Title }} is declined.',
        'body' => <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>We have reached a decision regarding your submission with title "{{ Submission Title }}"  to {{ Conference Title }}</p>
            <p>Our decision is to <b>decline the submission</b>.</p>
            <p>Thank you for considering {{ Conference Title }} as a venue for your work.</p>
            HTML,
    ],
    'new_announcement' => [
        'subject' => '{{ Announcement Title }}',
        'body' => <<<'HTML'
            {{{  Announcement Summary  }}}
            <p>Click <a href="{{ Announcement URL }}">here</a> to read the full announcement.</p>
            HTML,
    ],
    'new_discussion_topic' => [
        'subject' => 'New discussion topic',
        'body' => <<<'HTML'
            <p> You've been added to a new discussion. Here are the details:</p>
            <table>
                   <tr>
                       <td style="width:100px;">Submission</td>
                       <td>: {{ Submission Title }}</td>
                   </tr>
                   <tr>
                       <td style="width:100px;">Topic</td>
                       <td>: {{ Topic Name }}</td>
                   </tr>
               </table>
               <p><a href="{{ Submission URL }}" target="_blank">Click here</a> to access the discussion.</p>
            HTML,
    ],
    'new_review_file_uploaded' => [
        'subject' => 'New review file uploaded for {{ Submission Title }}',
        'body' => <<<'HTML'
            <p>Dear Editors,</p>
            <p>A review file has been uploaded for the submission "{{ Submission Title }}" in {{ Conference Title }}.</p>
            <table>
                <tr>
                    <td style="width:140px;">Submission ID</td>
                    <td>: {{ Submission ID }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Author</td>
                    <td>: {{ Submission Author }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Uploaded by</td>
                    <td>: {{ Uploaded By }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">File</td>
                    <td>: {{ File Name }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">File type</td>
                    <td>: {{ File Type }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Review round</td>
                    <td>: {{ Review Round Name }}</td>
                </tr>
            </table>
            <p>Please review the uploaded file and continue the editorial workflow from the submission page.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a>.</p>
            HTML,
    ],
    'new_revision_uploaded' => [
        'subject' => 'New revision uploaded for {{ Submission Title }}',
        'body' => <<<'HTML'
            <p>Dear Editors,</p>
            <p>A revision file has been uploaded for the submission "{{ Submission Title }}" in {{ Conference Title }}.</p>
            <table>
                <tr>
                    <td style="width:140px;">Submission ID</td>
                    <td>: {{ Submission ID }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Author</td>
                    <td>: {{ Submission Author }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Uploaded by</td>
                    <td>: {{ Uploaded By }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">File</td>
                    <td>: {{ File Name }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">File type</td>
                    <td>: {{ File Type }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Review round</td>
                    <td>: {{ Review Round Name }}</td>
                </tr>
            </table>
            <p>Please review the revision and continue the editorial workflow from the submission page.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a>.</p>
            HTML,
    ],
    'new_submission' => [
        'subject' => 'New Submission: {{ Submission Title }} from {{ Conference Title }}.',
        'body' => <<<'HTML'
            <p> A new article has been submitted and requires an editor assignment.</p>
            <table>
                <tr>
                    <td style="width:100px;">Title</td>
                    <td>:</td>
                    <td>{{ Submission Title }}</td>
                </tr>
                <tr>
                    <td style="width:100px;">Author</td>
                    <td>:</td>
                    <td>{{ Submission Author }}</td>
                </tr>
            </table>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
            HTML,
    ],
    'participant_assigned' => [
        'subject' => 'You have been assigned as a participant',
        'body' => <<<'HTML'
            <p>Dear {{ Participant Name }},</p>
            <p>You have been assigned as {{ Participant Role }} for "{{ Submission Title }}" on {{ Conference Title }}.</p>
            <p>Please guide the submission through the editorial process.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
            HTML,
    ],
    'participant_payment' => [
        'subject' => 'Participant Payment for: {{ Payment Fee Name }} on {{ Conference Title }}',
        'body' => <<<'HTML'
            <p>Dear {{ Participant Name }},</p>
            <p>We would like to inform you that a payment requirement has been added to your participant registration for {{ Conference Title }}.</p>
            <p>Participant Payment Details:</p>
            <ul>
                <li>Registration Type : <b>{{ Payment Fee Name }}</b></li>
                <li>Amount Due : <b>{{ Payment Amount }}</b></li>
            </ul>
            <p>To proceed, please complete your payment by visiting the link below:</p>
            <a href="{{ Payment Link }}">{{ Payment Link }}</a>
            <p>
                If you have already made the payment, kindly disregard this notice.
            </p>
            <p>Thank you for your participation, and we look forward to your contribution to {{ Conference Title }}.</p>
            HTML,
    ],
    'participant_registered' => [
        'subject' => 'New Participant Registered for {{ Conference Title }}.',
        'body' => <<<'HTML'
            <p>A new participant has been registered.</p>
            <table>
                <tr>
                    <td style="">Name</td>
                    <td>:</td>
                    <td>{{ Participant Name }}</td>
                </tr>
                <tr>
                    <td style="">Email</td>
                    <td>:</td>
                    <td>{{ Participant Email }}</td>
                </tr>
                <tr>
                    <td style="">Payment Fee Name</td>
                    <td>:</td>
                    <td>{{ Payment Fee Name }}</td>
                </tr>
                <tr>
                    <td style="">Payment Amount</td>
                    <td>:</td>
                    <td>{{ Payment Amount }}</td>
                </tr>
            </table>
            <p>Click here to <a href="{{ Payment Link }}">View Participant Registration</a></p>
            HTML,
    ],
    'payment_confirmed' => [
        'subject' => 'Payment Confirmed',
        'body' => <<<'HTML'
            <p>Dear {{ Payer Name }},</p>
            <p>Your payment with id {{ Payment ID }} has been successfully confirmed by the editor.</p>
            <p>Thank you for completing the payment. You may now proceed with the next steps process through your Leconfe account.</p>
            <p>If you have any questions, please feel free to contact the conference committee.</p>
            <p>Click here to <a href="{{ Payment Link }}">View Payment Detail</a></p>
            HTML,
    ],
    'publish_submission' => [
        'subject' => 'Submission Published',
        'body' => <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>Your submission "{{ Submission Title }}" has been published on {{ Conference Title }}</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
            HTML,
    ],
    'reset_password' => [
        'subject' => 'Password Reset Confirmation',
        'body' => <<<'HTML'
            <p>Dear User,</p>
            <p>We have received a request to reset your password for {{ siteName }} website. Please click the link below to reset your password:</p>
            <a href="{{ verificationUrl }}">Reset Password</a>
            <p>If you did not request a password reset, please ignore this email.</p>
            HTML,
    ],
    'review_complete' => [
        'subject' => 'Reviewer {{ Reviewer Name }} has completed a review on submission {{ Submission Title }}',
        'body' => <<<'HTML'
            <p>Reviewer {{ Reviewer Name }} has completed a review on submission {{ Submission Title }}</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
            HTML,
    ],
    'review_round_started' => [
        'subject' => 'Your submission {{ Submission Title }} has been sent for another review round',
        'body' => <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>Your submission "{{ Submission Title }}" has been sent for {{ Review Round Name }}.</p>
            <p>The editorial team will notify you when this review round progresses.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a>.</p>
            HTML,
    ],
    'reviewer_accepted_invitation' => [
        'subject' => 'Reviewer Accepted Invitation',
        'body' => <<<'HTML'
            <p>Reviewer {{ Reviewer Name }} has accepted the invitation to review the submission {{ Submission Title }}</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
            HTML,
    ],
    'reviewer_cancelation' => [
        'subject' => 'You have been cancelled as a reviewer',
        'body' => <<<'HTML'
            <p>Dear {{ Reviewer Name }},</p>
            <p>We have decided to withdraw our request for you to review the submission, "{{ Submission Title }}," for {{ Conference Title }}.</p>
            <p>We apologize for any inconvenience and truly hope to have the opportunity to invite you to assist with the review process of future conferences.</p>
            HTML,
    ],
    'reviewer_declined_invitation' => [
        'subject' => 'Reviewer Declined Invitation',
        'body' => <<<'HTML'
            <p>Reviewer {{ Reviewer Name }} has <b>declined</b> the invitation to review the submission {{ Submission Title }}</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
            HTML,
    ],
    'reviewer_invitation' => [
        'subject' => 'You have been assigned as a reviewer',
        'body' => <<<'HTML'
            <p>Dear {{ Reviewer Name }},</p>

            <p>We would like to invite you to review the paper "{{ Submission Title }}," which has been submitted to {{ Conference Title }}.</p>

            <p>Please log into the conference website by {{ Response Due Date }} to indicate whether you are able to undertake the review. Through the site, you can access the submission, record your review, and provide your recommendation: <a href="{{ Submission Invitation URL }}">{{ Submission Invitation URL }}</a></p>

            <p>The completed review is due by {{ Review Due Date }}.</p>

            <p>Thank you very much for considering this request.</p>
            HTML,
    ],
    'revision_request' => [
        'subject' => 'Revision Requested for {{ Submission Title }}',
        'body' => <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>Your submission with title "{{ Submission Title }}" on {{ Conference Title }} has been requested to be <b>Revised</b></p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a> and upload your submission revision.</p>
            HTML,
    ],
    'send_for_review' => [
        'subject' => 'Your submission {{ Submission Title }} has been sent for review',
        'body' => <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>Your submission "{{ Submission Title }}" to {{ Conference Title }} has been sent to the review stage.</p>
            <p>The editorial team will notify you when the review process progresses.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
            HTML,
    ],
    'submission_payment' => [
        'subject' => 'Submission Payment for: {{ Submission Title }} on {{ Conference Title }}',
        'body' => <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>We would like to inform you that a payment requirement has been added to your submission for {{ Conference Title }}.</p>
            <p>Submission Details:</p>
            <ul>
                <li>Title : <b>{{ Submission Title }}</b></li>
                <li>Reference ID : <b>{{ Submission ID }}</b></li>
                <li>Amount Due : <b>{{ Payment Amount }}</b></li>
            </ul>
            <p>To proceed, please complete your payment by visiting the link below:</p>
            <a href="{{ Payment Link }}">{{ Payment Link }}</a>
            <p>
                If you have already made the payment, kindly disregard this notice.
            </p>
            <p>Thank you for your participation, and we look forward to your contribution to {{ Conference Title }}.</p>
            HTML,
    ],
    'submission_withdrawn' => [
        'subject' => 'Submission Withdrawn',
        'body' => <<<'HTML'
            <p>Dear {{ Submission Author }},</p>

            <p>We would like to let you know that your submission titled "{{ Submission Title }}" has been withdrawn. Thank you for the time and effort you put into this work, and for considering {{ Conference Title }} as a venue for your research.</p>
            HTML,
    ],
    'submission_withdrawn_request' => [
        'subject' => 'Submission Withdraw Request',
        'body' => <<<'HTML'
            <p>We wanted to inform you that the submission "{{ Submission Title }}" has been requested to be withdrawn.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
            HTML,
    ],
    'test' => [
        'subject' => 'Test Mail',
        'body' => <<<'HTML'
            This is a test mail from Leconfe
            HTML,
    ],
    'thank_author' => [
        'subject' => 'Thank you for your submission to {{ Conference Title }}',
        'body' => <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>Thank you for your recent submission with title "{{ Submission Title }}" to {{ Conference Title }}. We appreciate your interest in participating in our conference.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
            <p>We have received your submission and it will be reviewed by our team. You will be notified of the outcome of the review process in due course.</p>
            <p>If you have any questions or need further information, please do not hesitate to contact us.</p>
            HTML,
    ],
    'user_pay_payment' => [
        'subject' => 'Payment Proof Submitted by {{ Payer Name }}',
        'body' => <<<'HTML'
            <p>Dear Editor,</p>
            <p>We would like to inform you that {{ Payer Name }} has submitted a payment proof for the <b>{{ Conference Title }}</b></p>
            <p>You can review the payment details at the link below:</p>
            <a href="{{ Payment Link }}">{{ Payment Link }}</a>
            HTML,
    ],
    'user_role_invitation' => [
        'subject' => 'You are invited to join as {{ Role Name }}',
        'body' => <<<'HTML'
            <p>Hello,</p>

            <p>You have been invited to join <b>{{ Conference Title }}</b> as <b>{{ Role Name }}</b>.</p>

            <p>
                Use this link to accept the invitation:
                <a href="{{ Invitation URL }}" target="_blank" rel="noopener noreferrer">{{ Invitation URL }}</a>
            </p>

            <p>This invitation expires on {{ Invitation Expiration Date }}.</p>
            HTML,
    ],
    'verify_user_email' => [
        'subject' => 'Verify Email Address',
        'body' => <<<'HTML'
            <p>Please click the button below to verify your email address.</p>
            <p><a href="{{ verificationUrl }}">Verify Email Address</a>.</p>
            <p>If you did not create an account, no further action is required.</p>
            HTML,
    ],
    'footer' => <<<'HTML'
        <p>________________________________________________________________________</p>
        <pre>{{ conferenceName }}</pre>
        <p style="font-size: 10px;color: gray;">Easily manage conference platform using <a href="https://leconfe.com">Leconfe</a></p>
        HTML,
];
