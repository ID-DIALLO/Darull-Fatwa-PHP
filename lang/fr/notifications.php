<?php

// Libellés des notifications (cloche et e-mails) — français. Paramètres : :reference, :reason…
return [
    'question' => [
        'received' => [
            'title'   => 'Question :reference bien reçue',
            'message' => 'Votre question a été transmise à nos professeurs. Vous serez prévenu dès qu\'une réponse sera disponible.',
            'icon'    => 'check2-circle',
        ],
        'assigned' => [
            'title'   => 'Nouvelle question à traiter : :reference',
            'message' => 'Une question vous a été assignée.',
            'icon'    => 'inbox',
        ],
        'needs_info' => [
            'title'   => 'Précision demandée pour :reference',
            'message' => 'Le professeur a besoin d\'une précision pour répondre à votre question.',
            'icon'    => 'question-circle',
        ],
        'info_received' => [
            'title'   => 'Précision reçue pour :reference',
            'message' => 'Le membre a complété sa question.',
            'icon'    => 'chat-left-dots',
        ],
        'follow_up' => [
            'title'   => 'Question de suivi pour :reference',
            'message' => 'Le membre a posé une question de suivi après votre réponse.',
            'icon'    => 'arrow-return-right',
        ],
        'answered' => [
            'title'   => 'Réponse disponible pour :reference',
            'message' => 'Un professeur a répondu à votre question.',
            'icon'    => 'envelope-open-heart',
        ],
        'rejected' => [
            'title'   => 'Question :reference non retenue',
            'message' => 'Votre question n\'a pas pu être retenue. Motif : :reason',
            'icon'    => 'x-circle',
        ],
        'new_for_staff' => [
            'title'   => 'Nouvelle question :reference',
            'message' => 'Une nouvelle question attend d\'être assignée à un professeur.',
            'icon'    => 'bell',
        ],
        'review_pending' => [
            'title'   => 'Réponse à valider pour :reference',
            'message' => 'Une réponse attend votre relecture avant envoi au membre.',
            'icon'    => 'eye',
        ],
        'late' => [
            'title'   => 'Question en retard : :reference',
            'message' => 'Cette question attend une réponse depuis plus de :hours heures.',
            'icon'    => 'alarm',
        ],
        'reassign_requested' => [
            'title'   => 'Demande de réassignation : :reference',
            'message' => 'Le professeur demande à être déchargé de cette question. Motif : :reason',
            'icon'    => 'arrow-left-right',
        ],
        'publication_proposed' => [
            'title'   => 'Proposition de publication : :reference',
            'message' => 'Un professeur propose une version anonymisée de cette question-réponse.',
            'icon'    => 'megaphone',
        ],
        'review_approved' => [
            'title'   => 'Réponse validée : :reference',
            'message' => 'Votre réponse a été validée et envoyée au membre.',
            'icon'    => 'patch-check',
        ],
        'review_rejected' => [
            'title'   => 'Réponse à revoir : :reference',
            'message' => 'La relecture demande des modifications : :reason',
            'icon'    => 'pencil-square',
        ],
    ],
    'contact' => [
        'received' => [
            'title'   => 'Nouveau message de contact',
            'message' => 'Un message vient d\'arriver depuis le formulaire de contact.',
            'icon'    => 'envelope',
        ],
    ],
    'forum' => [
        'reply' => [
            'title'   => 'Nouvelle réponse dans « :topic »',
            'message' => ':name a répondu dans un sujet que vous suivez.',
            'icon'    => 'chat-square-text',
        ],
    ],
    'idea' => [
        'status' => [
            'title'   => 'Votre idée « :title » a changé de statut',
            'message' => 'Nouveau statut : :status',
            'icon'    => 'lightbulb',
        ],
    ],
    'comment' => [
        'pending' => ['title' => 'Commentaire à modérer', 'message' => 'Un membre a déposé un commentaire en attente de relecture.', 'icon' => 'chat-left-text'],
    ],
    'article' => [
        'submitted' => ['title' => 'Article soumis par :author', 'message' => 'Un professeur a soumis un article à la validation.', 'icon' => 'journal-text'],
        'published' => ['title' => 'Votre article a été publié', 'message' => 'L\'administration a validé et publié votre article.', 'icon' => 'journal-check'],
    ],
];
