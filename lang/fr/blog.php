<?php

// Blog (§4.7) — français
return [
    'index' => [
        'title' => 'Blog', 'meta' => 'Articles de fond, rappels et enseignements rédigés par les professeurs de Darul Fatwa.',
        'intro' => 'Articles, rappels et enseignements rédigés par nos professeurs.', 'featured' => 'À la une', 'search' => 'Rechercher un article',
        'empty' => 'Aucun article publié pour le moment.', 'empty_filtered' => 'Aucun article ne correspond à votre recherche.', 'popular' => 'Les plus lus', 'tags' => 'Mots-clés',
        'tag_filter' => 'Mot-clé :', 'missing_translation' => 'Cet article n\'est pas encore disponible dans cette langue. Voici les articles traduits.',
    ],
    'show' => ['reading' => ':min min de lecture', 'listen' => 'Écouter l\'article', 'author' => 'Écrit par', 'related' => 'Articles liés'],
    'status' => ['draft' => 'Brouillon', 'submitted' => 'Soumis', 'scheduled' => 'Planifié', 'published' => 'Publié', 'archived' => 'Archivé'],
    'comments' => [
        'count' => ':count commentaire|:count commentaires', 'empty' => 'Soyez la première personne à commenter.', 'write' => 'Votre commentaire', 'submit' => 'Publier mon commentaire',
        'moderation_notice' => 'Les commentaires sont relus avant publication.', 'pending_notice' => 'Merci ! Votre commentaire sera publié après relecture par l\'équipe.',
        'your_pending' => 'Votre commentaire est en attente de relecture :', 'login' => 'Connectez-vous pour commenter', 'reply' => 'Répondre', 'replying' => 'Vous répondez à un commentaire.',
    ],
    'manage' => [
        'my_articles' => 'Mes articles', 'my_intro' => 'Rédigez vos articles, enregistrez-les en brouillon puis soumettez-les à la validation de l\'administration.', 'new' => 'Nouvel article', 'edit' => 'Modifier l\'article',
        'empty' => 'Vous n\'avez pas encore écrit d\'article.', 'untitled' => 'Sans titre', 'scholar_intro' => 'Au moins une langue est requise. L\'administration publie après relecture.',
        'locked' => 'Cet article a été publié : contactez l\'administration pour le modifier.', 'save_draft' => 'Enregistrer le brouillon', 'submit_review' => 'Soumettre à la validation',
        'field' => [
            'title' => 'Titre', 'excerpt' => 'Extrait', 'excerpt_help' => 'Court résumé affiché dans les listes (généré depuis le contenu si vide).', 'content' => 'Contenu', 'content_help' => 'Utilisez le bloc « citation » pour les versets et hadiths en arabe.',
            'tags' => 'Mots-clés', 'tags_placeholder' => 'prière, ramadan, famille', 'published_at' => 'Date de publication', 'published_at_help' => 'Date future + statut « publié » ou « planifié » : publication automatique à l\'heure dite.',
            'featured' => 'Mettre à la une', 'allow_comments' => 'Autoriser les commentaires', 'cover' => 'Image à la une', 'cover_help' => 'JPG, PNG ou WebP, 5 Mo max. Jamais de représentation figurative des prophètes ou des Compagnons.',
            'audio' => 'Version audio', 'audio_current' => 'Audio actuel : :name', 'audio_remove' => 'Retirer l\'audio', 'audio_help' => 'Enregistrez ou joignez la lecture de l\'article (MP3, M4A, OGG).',
        ],
        'flash' => ['saved' => 'Brouillon enregistré.', 'submitted' => 'Article soumis à la validation. L\'administration a été prévenue.', 'title_required' => 'Indiquez un titre dans au moins une langue.'],
    ],
];
