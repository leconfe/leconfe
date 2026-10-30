<?php

return [
    'accept_paper' => [
        'subject' => 'Votre soumission {{ Submission Title }} à {{ Conference Title }} est acceptée.',
        'body' => <<<'HTML'
            <p>Bonjour {{ Submission Author }},</p>
            <p>Nous avons pris une décision concernant votre soumission intitulée « {{ Submission Title }} » à {{ Conference Title }}.</p>
            <p>Notre décision est d’<b>accepter la soumission</b>.</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a></p>
            HTML,
    ],
    'decline_abstract' => [
        'subject' => 'Votre soumission {{ Submission Title }} à {{ Conference Title }} est refusée.',
        'body' => <<<'HTML'
            <p>Bonjour {{ Submission Author }},</p>
            <p>Nous avons pris une décision concernant votre soumission intitulée « {{ Submission Title }} » à {{ Conference Title }}.</p>
            <p>Notre décision est de <b>refuser la soumission</b>.</p>
            <p>Merci d’avoir pensé à {{ Conference Title }} pour présenter vos travaux.</p>
            HTML,
    ],
    'decline_paper' => [
        'subject' => 'Votre soumission {{ Submission Title }} à {{ Conference Title }} est refusée.',
        'body' => <<<'HTML'
            <p>Bonjour {{ Submission Author }},</p>
            <p>Nous avons pris une décision concernant votre soumission intitulée « {{ Submission Title }} » à {{ Conference Title }}.</p>
            <p>Notre décision est de <b>refuser la soumission</b>.</p>
            <p>Merci d’avoir pensé à {{ Conference Title }} pour présenter vos travaux.</p>
            HTML,
    ],
    'new_announcement' => [
        'subject' => '{{ Announcement Title }}',
        'body' => <<<'HTML'
            {{{  Announcement Summary  }}}
            <p>Cliquez <a href="{{ Announcement URL }}">ici</a> pour lire l’annonce complète.</p>
            HTML,
    ],
    'new_discussion_topic' => [
        'subject' => 'Nouveau sujet de discussion',
        'body' => <<<'HTML'
             <p>Vous avez été ajouté à une nouvelle discussion. En voici les détails :</p>
             <table>
                    <tr>
                        <td style="width:100px;">Soumission</td>
                        <td>: {{ Submission Title }}</td>
                    </tr>
                    <tr>
                        <td style="width:100px;">Sujet</td>
                        <td>: {{ Topic Name }}</td>
                    </tr>
                </table>
                <p><a href="{{ Submission URL }}" target="_blank">Cliquez ici</a> pour accéder à la discussion.</p>
            HTML,
    ],
    'new_review_file_uploaded' => [
        'subject' => 'Nouveau fichier d’évaluation déposé pour {{ Submission Title }}',
        'body' => <<<'HTML'
            <p>Chers éditeurs,</p>
            <p>Un fichier d’évaluation a été déposé pour la soumission « {{ Submission Title }} » à {{ Conference Title }}.</p>
            <table>
                <tr>
                    <td style="width:140px;">ID de la soumission</td>
                    <td>: {{ Submission ID }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Auteur</td>
                    <td>: {{ Submission Author }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Déposé par</td>
                    <td>: {{ Uploaded By }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Fichier</td>
                    <td>: {{ File Name }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Type de fichier</td>
                    <td>: {{ File Type }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Tour d’évaluation</td>
                    <td>: {{ Review Round Name }}</td>
                </tr>
            </table>
            <p>Veuillez examiner le fichier déposé et poursuivre le processus éditorial depuis la page de la soumission.</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a>.</p>
            HTML,
    ],
    'new_revision_uploaded' => [
        'subject' => 'Nouvelle révision déposée pour {{ Submission Title }}',
        'body' => <<<'HTML'
            <p>Chers éditeurs,</p>
            <p>Un fichier de révision a été déposé pour la soumission « {{ Submission Title }} » à {{ Conference Title }}.</p>
            <table>
                <tr>
                    <td style="width:140px;">ID de la soumission</td>
                    <td>: {{ Submission ID }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Auteur</td>
                    <td>: {{ Submission Author }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Déposé par</td>
                    <td>: {{ Uploaded By }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Fichier</td>
                    <td>: {{ File Name }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Type de fichier</td>
                    <td>: {{ File Type }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Tour d’évaluation</td>
                    <td>: {{ Review Round Name }}</td>
                </tr>
            </table>
            <p>Veuillez examiner la révision et poursuivre le processus éditorial depuis la page de la soumission.</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a>.</p>
            HTML,
    ],
    'new_submission' => [
        'subject' => 'Nouvelle soumission : {{ Submission Title }} pour {{ Conference Title }}.',
        'body' => <<<'HTML'
            <p>Un nouvel article a été soumis et doit être attribué à un éditeur.</p>
            <table>
                <tr>
                    <td style="width:100px;">Titre</td>
                    <td>:</td>
                    <td>{{ Submission Title }}</td>
                </tr>
                <tr>
                    <td style="width:100px;">Auteur</td>
                    <td>:</td>
                    <td>{{ Submission Author }}</td>
                </tr>
            </table>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a></p>
            HTML,
    ],
    'participant_assigned' => [
        'subject' => 'Vous avez été désigné comme participant',
        'body' => <<<'HTML'
            <p>Bonjour {{ Participant Name }},</p>
            <p>Vous avez été désigné comme {{ Participant Role }} pour « {{ Submission Title }} » à {{ Conference Title }}.</p>
            <p>Merci d’accompagner la soumission tout au long du processus éditorial.</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a></p>
            HTML,
    ],
    'participant_payment' => [
        'subject' => 'Paiement de participation pour : {{ Payment Fee Name }} à {{ Conference Title }}',
        'body' => <<<'HTML'
            <p>Bonjour {{ Participant Name }},</p>
            <p>Nous vous informons qu’un paiement a été ajouté à votre inscription comme participant à {{ Conference Title }}.</p>
            <p>Détails du paiement de participation :</p>
            <ul>
                <li>Type d’inscription : <b>{{ Payment Fee Name }}</b></li>
                <li>Montant dû : <b>{{ Payment Amount }}</b></li>
            </ul>
            <p>Pour continuer, veuillez effectuer votre paiement en suivant le lien ci-dessous :</p>
            <a href="{{ Payment Link }}">{{ Payment Link }}</a>
            <p>
                Si vous avez déjà effectué le paiement, merci de ne pas tenir compte de ce message.
            </p>
            <p>Merci de votre participation, nous nous réjouissons de votre contribution à {{ Conference Title }}.</p>
            HTML,
    ],
    'participant_registered' => [
        'subject' => 'Nouveau participant inscrit à {{ Conference Title }}.',
        'body' => <<<'HTML'
            <p>Un nouveau participant s’est inscrit.</p>
            <table>
                <tr>
                    <td style="">Nom</td>
                    <td>:</td>
                    <td>{{ Participant Name }}</td>
                </tr>
                <tr>
                    <td style="">E-mail</td>
                    <td>:</td>
                    <td>{{ Participant Email }}</td>
                </tr>
                <tr>
                    <td style="">Frais</td>
                    <td>:</td>
                    <td>{{ Payment Fee Name }}</td>
                </tr>
                <tr>
                    <td style="">Montant</td>
                    <td>:</td>
                    <td>{{ Payment Amount }}</td>
                </tr>
            </table>
            <p>Cliquez ici pour <a href="{{ Payment Link }}">voir l’inscription du participant</a></p>
            HTML,
    ],
    'payment_confirmed' => [
        'subject' => 'Paiement confirmé',
        'body' => <<<'HTML'
            <p>Bonjour {{ Payer Name }},</p>
            <p>Votre paiement n° {{ Payment ID }} a bien été confirmé par l’éditeur.</p>
            <p>Merci d’avoir effectué le paiement. Vous pouvez maintenant poursuivre les étapes suivantes depuis votre compte Leconfe.</p>
            <p>Pour toute question, n’hésitez pas à contacter le comité de la conférence.</p>
            <p>Cliquez ici pour <a href="{{ Payment Link }}">voir le détail du paiement</a></p>
            HTML,
    ],
    'publish_submission' => [
        'subject' => 'Soumission publiée',
        'body' => <<<'HTML'
            <p>Bonjour {{ Submission Author }},</p>
            <p>Votre soumission « {{ Submission Title }} » a été publiée sur {{ Conference Title }}</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a></p>
            HTML,
    ],
    'reset_password' => [
        'subject' => 'Réinitialisation du mot de passe',
        'body' => <<<'HTML'
            <p>Bonjour,</p>
            <p>Nous avons reçu une demande de réinitialisation de votre mot de passe pour le site {{ siteName }}. Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>
            <a href="{{ verificationUrl }}">Réinitialiser le mot de passe</a>
            <p>Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail.</p>
            HTML,
    ],
    'review_complete' => [
        'subject' => 'L’évaluateur {{ Reviewer Name }} a terminé une évaluation de la soumission {{ Submission Title }}',
        'body' => <<<'HTML'
            <p>L’évaluateur {{ Reviewer Name }} a terminé une évaluation de la soumission {{ Submission Title }}</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a></p>
            HTML,
    ],
    'review_round_started' => [
        'subject' => 'Votre soumission {{ Submission Title }} a été envoyée pour un nouveau tour d’évaluation',
        'body' => <<<'HTML'
            <p>Bonjour {{ Submission Author }},</p>
            <p>Votre soumission « {{ Submission Title }} » a été envoyée pour {{ Review Round Name }}.</p>
            <p>L’équipe éditoriale vous informera de l’avancement de ce tour d’évaluation.</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a>.</p>
            HTML,
    ],
    'reviewer_accepted_invitation' => [
        'subject' => 'Invitation acceptée par l’évaluateur',
        'body' => <<<'HTML'
            <p>L’évaluateur {{ Reviewer Name }} a accepté l’invitation à évaluer la soumission {{ Submission Title }}</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a></p>
            HTML,
    ],
    'reviewer_cancelation' => [
        'subject' => 'Votre mission d’évaluation a été annulée',
        'body' => <<<'HTML'
            <p>Bonjour {{ Reviewer Name }},</p>
            <p>Nous avons décidé de retirer notre demande d’évaluation de la soumission « {{ Submission Title }} » pour {{ Conference Title }}.</p>
            <p>Nous vous prions de nous excuser pour ce désagrément et espérons avoir l’occasion de vous solliciter pour l’évaluation de prochaines conférences.</p>
            HTML,
    ],
    'reviewer_declined_invitation' => [
        'subject' => 'Invitation déclinée par l’évaluateur',
        'body' => <<<'HTML'
            <p>L’évaluateur {{ Reviewer Name }} a <b>décliné</b> l’invitation à évaluer la soumission {{ Submission Title }}</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a></p>
            HTML,
    ],
    'reviewer_invitation' => [
        'subject' => 'Vous avez été désigné comme évaluateur',
        'body' => <<<'HTML'
            <p>Bonjour {{ Reviewer Name }},</p>

            <p>Nous souhaitons vous inviter à évaluer l’article « {{ Submission Title }} », soumis à {{ Conference Title }}.</p>

            <p>Veuillez vous connecter au site de la conférence avant le {{ Response Due Date }} pour indiquer si vous acceptez cette évaluation. Depuis le site, vous pouvez consulter la soumission, saisir votre évaluation et donner votre recommandation : <a href="{{ Submission Invitation URL }}">{{ Submission Invitation URL }}</a></p>

            <p>L’évaluation est attendue avant le {{ Review Due Date }}.</p>

            <p>Merci beaucoup de l’attention que vous porterez à cette demande.</p>
            HTML,
    ],
    'revision_request' => [
        'subject' => 'Révision demandée pour {{ Submission Title }}',
        'body' => <<<'HTML'
            <p>Bonjour {{ Submission Author }},</p>
            <p>Une <b>révision</b> de votre soumission intitulée « {{ Submission Title }} » à {{ Conference Title }} a été demandée.</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a> et déposer la version révisée.</p>
            HTML,
    ],
    'send_for_review' => [
        'subject' => 'Votre soumission {{ Submission Title }} a été envoyée en évaluation',
        'body' => <<<'HTML'
            <p>Bonjour {{ Submission Author }},</p>
            <p>Votre soumission « {{ Submission Title }} » à {{ Conference Title }} est passée à l’étape d’évaluation.</p>
            <p>L’équipe éditoriale vous informera de l’avancement de l’évaluation.</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a></p>
            HTML,
    ],
    'submission_payment' => [
        'subject' => 'Paiement de la soumission : {{ Submission Title }} à {{ Conference Title }}',
        'body' => <<<'HTML'
            <p>Bonjour {{ Submission Author }},</p>
            <p>Nous vous informons qu’un paiement a été ajouté à votre soumission pour {{ Conference Title }}.</p>
            <p>Détails de la soumission :</p>
            <ul>
                <li>Titre : <b>{{ Submission Title }}</b></li>
                <li>Référence : <b>{{ Submission ID }}</b></li>
                <li>Montant dû : <b>{{ Payment Amount }}</b></li>
            </ul>
            <p>Pour continuer, veuillez effectuer votre paiement en suivant le lien ci-dessous :</p>
            <a href="{{ Payment Link }}">{{ Payment Link }}</a>
            <p>
                Si vous avez déjà effectué le paiement, merci de ne pas tenir compte de ce message.
            </p>
            <p>Merci de votre participation, nous nous réjouissons de votre contribution à {{ Conference Title }}.</p>
            HTML,
    ],
    'submission_withdrawn' => [
        'subject' => 'Soumission retirée',
        'body' => <<<'HTML'
            <p>Bonjour {{ Submission Author }},</p>

            <p>Nous vous informons que votre soumission intitulée « {{ Submission Title }} » a été retirée. Merci pour le temps et les efforts consacrés à ce travail, et d’avoir pensé à {{ Conference Title }} pour présenter vos recherches.</p>
            HTML,
    ],
    'submission_withdrawn_request' => [
        'subject' => 'Demande de retrait de soumission',
        'body' => <<<'HTML'
            <p>Nous vous informons que le retrait de la soumission « {{ Submission Title }} » a été demandé.</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a></p>
            HTML,
    ],
    'test' => [
        'subject' => 'E-mail de test',
        'body' => <<<'HTML'
            Ceci est un e-mail de test envoyé par Leconfe
            HTML,
    ],
    'thank_author' => [
        'subject' => 'Merci pour votre soumission à {{ Conference Title }}',
        'body' => <<<'HTML'
            <p>Bonjour {{ Submission Author }},</p>
            <p>Merci pour votre récente soumission intitulée « {{ Submission Title }} » à {{ Conference Title }}. Nous vous remercions de l’intérêt que vous portez à notre conférence.</p>
            <p>Cliquez ici pour <a href="{{ Submission URL }}">voir la soumission</a></p>
            <p>Nous avons bien reçu votre soumission, qui sera examinée par notre équipe. Vous serez informé du résultat de l’évaluation en temps voulu.</p>
            <p>Pour toute question ou information complémentaire, n’hésitez pas à nous contacter.</p>
            HTML,
    ],
    'user_pay_payment' => [
        'subject' => 'Justificatif de paiement envoyé par {{ Payer Name }}',
        'body' => <<<'HTML'
            <p>Bonjour,</p>
            <p>Nous vous informons que {{ Payer Name }} a envoyé un justificatif de paiement pour <b>{{ Conference Title }}</b></p>
            <p>Vous pouvez consulter le détail du paiement via le lien ci-dessous :</p>
            <a href="{{ Payment Link }}">{{ Payment Link }}</a>
            HTML,
    ],
    'user_role_invitation' => [
        'subject' => 'Vous êtes invité à rejoindre la conférence en tant que {{ Role Name }}',
        'body' => <<<'HTML'
            <p>Bonjour,</p>

            <p>Vous êtes invité à rejoindre <b>{{ Conference Title }}</b> en tant que <b>{{ Role Name }}</b>.</p>

            <p>
                Utilisez ce lien pour accepter l’invitation :
                <a href="{{ Invitation URL }}" target="_blank" rel="noopener noreferrer">{{ Invitation URL }}</a>
            </p>

            <p>Cette invitation expire le {{ Invitation Expiration Date }}.</p>
            HTML,
    ],
    'verify_user_email' => [
        'subject' => 'Vérifiez votre adresse e-mail',
        'body' => <<<'HTML'
            <p>Veuillez cliquer sur le lien ci-dessous pour vérifier votre adresse e-mail.</p>
            <p><a href="{{ verificationUrl }}">Vérifier l’adresse e-mail</a>.</p>
            <p>Si vous n’avez pas créé de compte, aucune action n’est requise.</p>
            HTML,
    ],
    'footer' => <<<'HTML'
        <p>________________________________________________________________________</p>
        <pre>{{ conferenceName }}</pre>
        <p style="font-size: 10px;color: gray;">Gérez facilement votre conférence avec <a href="https://leconfe.com">Leconfe</a></p>
        HTML,
];
