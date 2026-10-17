<?php

declare(strict_types=1);

// Matrice des permissions (§2.1) : rôle => liste des permissions.
// '*' = toutes les permissions. Vérifiées par can('...') et RoleMiddleware.
$member = [
    'questions.create', 'questions.view_own', 'comments.create', 'forum.post',
    'ideas.create', 'ideas.vote', 'favorites.manage', 'likes.toggle',
];

$scholar = array_merge($member, [
    'questions.view_assigned', 'questions.self_assign', 'questions.answer',
    'questions.request_info', 'questions.internal_note', 'questions.propose_publication',
    'questions.request_reassign', 'questions.whatsapp_reply', 'articles.create', 'forum.validate_answer', 'scholar.dashboard',
]);

$moderator = array_merge($member, [
    'questions.view_all', 'questions.assign', 'questions.change_status',
    'forum.moderate', 'comments.moderate', 'ideas.moderate', 'reports.handle', 'admin.access',
]);

$admin = array_values(array_unique(array_merge($scholar, $moderator, [
    'questions.publish', 'questions.review', 'questions.export', 'questions.delete',
    'articles.publish', 'users.manage', 'scholars.manage', 'rubriques.manage', 'qa.manage',
    'banners.manage', 'pages.manage', 'home.manage', 'contact.manage', 'newsletter.manage',
    'settings.manage', 'activity_log.view', 'stats.view',
])));

return [
    'member'     => $member,
    'scholar'    => $scholar,
    'moderator'  => $moderator,
    'admin'      => $admin,
    'superadmin' => ['*'],
];
