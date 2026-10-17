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
];
