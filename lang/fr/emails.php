<?php

// E-mails transactionnels — français
return [
    'common' => [
        'greeting'        => 'Assalamou alaykoum :name,',
        'signature'       => 'L\'équipe Darul Fatwa',
        'footer'          => 'Vous recevez cet e-mail parce qu\'un compte Darul Fatwa est associé à cette adresse.',
        'button_fallback' => 'Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :',
        'ignore'          => 'Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement cet e-mail.',
        'visit'           => 'Visiter le site',
    ],
    'verify_email' => [
        'subject' => 'Vérifiez votre adresse e-mail — Darul Fatwa',
        'title'   => 'Bienvenue sur Darul Fatwa',
        'body'    => 'Merci d\'avoir créé votre compte. Confirmez votre adresse e-mail pour recevoir les réponses à vos questions.',
        'button'  => 'Vérifier mon adresse e-mail',
        'expires' => 'Ce lien est valable :hours heures.',
    ],
    'reset_password' => [
        'subject' => 'Réinitialisation de votre mot de passe — Darul Fatwa',
        'title'   => 'Nouveau mot de passe',
        'body'    => 'Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le bouton ci-dessous pour en choisir un nouveau.',
        'button'  => 'Choisir un nouveau mot de passe',
        'expires' => 'Ce lien est valable :minutes minutes et ne peut être utilisé qu\'une fois.',
    ],
    'password_changed' => [
        'subject' => 'Votre mot de passe a été modifié — Darul Fatwa',
        'title'   => 'Mot de passe modifié',
        'body'    => 'Le mot de passe de votre compte vient d\'être modifié. Si vous n\'êtes pas à l\'origine de ce changement, réinitialisez immédiatement votre mot de passe et contactez-nous.',
    ],
    'account_deleted' => [
        'subject' => 'Votre compte a été supprimé — Darul Fatwa',
        'title'   => 'Compte supprimé',
        'body'    => 'Votre compte Darul Fatwa et vos questions privées ont été supprimés, conformément à votre demande. Qu\'Allah vous facilite.',
    ],
    'contact_received' => [
        'subject' => 'Nouveau message de contact : :subject_label',
        'title'   => 'Nouveau message de contact',
        'body'    => 'Un message vient d\'être envoyé depuis le formulaire de contact du site.',
        'from'    => 'De',
        'reply'   => 'Répondre',
    ],
    'contact_ack' => [
        'subject' => 'Nous avons bien reçu votre message — Darul Fatwa',
        'title'   => 'Message bien reçu',
        'body'    => 'Merci de nous avoir écrit. Votre message a bien été reçu ; nous vous répondrons dès que possible, in shâ Allah.',
        'copy'    => 'Copie de votre message :',
    ],
    'newsletter_confirm' => [
        'subject' => 'Confirmez votre abonnement — Darul Fatwa',
        'title'   => 'Confirmez votre abonnement',
        'body'    => 'Vous avez demandé à recevoir les nouvelles de Darul Fatwa par e-mail. Confirmez votre abonnement en cliquant sur le bouton ci-dessous.',
        'button'  => 'Confirmer mon abonnement',
        'unsubscribe' => 'Se désabonner',
    ],
    'notification' => [
        'subject' => ':title — Darul Fatwa',
        'button'  => 'Voir sur Darul Fatwa',
    ],
    'question_received' => [
        'subject' => 'Votre question :reference a bien été reçue — Darul Fatwa',
        'title'   => 'Question bien reçue',
        'body'    => 'Votre question a bien été transmise à nos professeurs sous la référence :reference. Délai indicatif de réponse : :late_hours heures. Vous serez prévenu dès qu\'une réponse sera disponible.',
        'button'  => 'Suivre ma question',
    ],
    'late_digest' => ['subject' => ':count question(s) en retard — Darul Fatwa'],
];
