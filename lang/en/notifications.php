<?php

// Notification labels (bell and emails) — English. Parameters: :reference, :reason…
return [
    'question' => [
        'received' => [
            'title'   => 'Question :reference received',
            'message' => 'Your question has been forwarded to our scholars. You will be notified as soon as an answer is available.',
            'icon'    => 'check2-circle',
        ],
        'assigned' => [
            'title'   => 'New question to handle: :reference',
            'message' => 'A question has been assigned to you.',
            'icon'    => 'inbox',
        ],
        'needs_info' => [
            'title'   => 'Clarification requested for :reference',
            'message' => 'The scholar needs a clarification to answer your question.',
            'icon'    => 'question-circle',
        ],
        'info_received' => [
            'title'   => 'Clarification received for :reference',
            'message' => 'The member has completed their question.',
            'icon'    => 'chat-left-dots',
        ],
        'follow_up' => [
            'title'   => 'Follow-up question for :reference',
            'message' => 'The member asked a follow-up question after your answer.',
            'icon'    => 'arrow-return-right',
        ],
        'answered' => [
            'title'   => 'Answer available for :reference',
            'message' => 'A scholar has answered your question.',
            'icon'    => 'envelope-open-heart',
        ],
        'rejected' => [
            'title'   => 'Question :reference not accepted',
            'message' => 'Your question could not be accepted. Reason: :reason',
            'icon'    => 'x-circle',
        ],
        'new_for_staff' => [
            'title'   => 'New question :reference',
            'message' => 'A new question is waiting to be assigned to a scholar.',
            'icon'    => 'bell',
        ],
        'review_pending' => [
            'title'   => 'Answer to review for :reference',
            'message' => 'An answer is waiting for your review before being sent to the member.',
            'icon'    => 'eye',
        ],
        'late' => [
            'title'   => 'Overdue question: :reference',
            'message' => 'This question has been waiting for an answer for more than :hours hours.',
            'icon'    => 'alarm',
        ],
        'reassign_requested' => [
            'title'   => 'Reassignment request: :reference',
            'message' => 'The scholar asks to be relieved of this question. Reason: :reason',
            'icon'    => 'arrow-left-right',
        ],
        'publication_proposed' => [
            'title'   => 'Publication proposal: :reference',
            'message' => 'A scholar proposes an anonymised version of this Q&A.',
            'icon'    => 'megaphone',
        ],
        'review_approved' => [
            'title'   => 'Answer validated: :reference',
            'message' => 'Your answer has been validated and sent to the member.',
            'icon'    => 'patch-check',
        ],
        'review_rejected' => [
            'title'   => 'Answer to revise: :reference',
            'message' => 'The review asks for changes: :reason',
            'icon'    => 'pencil-square',
        ],
    ],
    'contact' => [
        'received' => [
            'title'   => 'New contact message',
            'message' => 'A message has just arrived from the contact form.',
            'icon'    => 'envelope',
        ],
    ],
    'forum' => [
        'reply' => [
            'title'   => 'New reply in ":topic"',
            'message' => ':name replied in a topic you follow.',
            'icon'    => 'chat-square-text',
        ],
    ],
    'idea' => [
        'status' => [
            'title'   => 'Your idea ":title" changed status',
            'message' => 'New status: :status',
            'icon'    => 'lightbulb',
        ],
    ],
    'comment' => [
        'pending' => ['title' => 'Comment to moderate', 'message' => 'A member posted a comment awaiting review.', 'icon' => 'chat-left-text'],
    ],
    'article' => [
        'submitted' => ['title' => 'Article submitted by :author', 'message' => 'A scholar submitted an article for approval.', 'icon' => 'journal-text'],
        'published' => ['title' => 'Your article has been published', 'message' => 'The administration approved and published your article.', 'icon' => 'journal-check'],
    ],
    'forum' => [
        'reply' => ['title' => 'New reply: :title', 'message' => ':author replied in a topic you follow.', 'icon' => 'chat-square-text'],
        'topic_approved' => ['title' => 'Topic published: :title', 'message' => 'Your topic has been approved by the moderators.', 'icon' => 'check2-circle'],
        'post_approved' => ['title' => 'Reply published in ":title"', 'message' => 'Your reply has been approved by the moderators.', 'icon' => 'check2-circle'],
        'answer_validated' => ['title' => 'Answer validated by a scholar', 'message' => 'Your reply in ":title" now carries the "validated by a scholar" badge.', 'icon' => 'patch-check'],
        'pending' => ['title' => 'Forum: content to moderate', 'message' => 'A post is awaiting a moderator\'s review (:title).', 'icon' => 'shield'],
        'report' => ['title' => 'New report', 'message' => 'A member reported some content (:reason).', 'icon' => 'flag'],
    ],
    'idea' => [
        'pending' => ['title' => 'New idea to moderate', 'message' => '":title" is awaiting the team\'s review.', 'icon' => 'lightbulb'],
        'status' => ['title' => 'Your idea ":title"', 'message' => 'New status: :status.', 'icon' => 'lightbulb'],
        'response' => ['title' => 'Official response to your idea', 'message' => 'The team answered ":title" (:status).', 'icon' => 'patch-check'],
        'comment' => ['title' => 'New comment on your idea', 'message' => 'A member commented on ":title".', 'icon' => 'chat-left'],
    ],
];
