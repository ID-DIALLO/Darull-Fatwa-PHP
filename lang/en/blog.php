<?php

// Blog (§4.7) — English
return [
    'index' => [
        'title' => 'Blog', 'meta' => 'In-depth articles, reminders and teachings written by the Darul Fatwa scholars.',
        'intro' => 'Articles, reminders and teachings written by our scholars.', 'featured' => 'Featured', 'search' => 'Search an article',
        'empty' => 'No article published yet.', 'empty_filtered' => 'No article matches your search.', 'popular' => 'Most read', 'tags' => 'Tags',
        'tag_filter' => 'Tag:', 'missing_translation' => 'This article is not available in this language yet. Here are the translated articles.',
    ],
    'show' => ['reading' => ':min min read', 'listen' => 'Listen to the article', 'author' => 'Written by', 'related' => 'Related articles'],
    'status' => ['draft' => 'Draft', 'submitted' => 'Submitted', 'scheduled' => 'Scheduled', 'published' => 'Published', 'archived' => 'Archived'],
    'comments' => [
        'count' => ':count comment|:count comments', 'empty' => 'Be the first to comment.', 'write' => 'Your comment', 'submit' => 'Post my comment',
        'moderation_notice' => 'Comments are reviewed before publication.', 'pending_notice' => 'Thank you! Your comment will be published after review by the team.',
        'your_pending' => 'Your comment is awaiting review:', 'login' => 'Sign in to comment', 'reply' => 'Reply', 'replying' => 'You are replying to a comment.',
    ],
    'manage' => [
        'my_articles' => 'My articles', 'my_intro' => 'Write your articles, save them as drafts, then submit them to the administration for approval.', 'new' => 'New article', 'edit' => 'Edit article',
        'empty' => 'You have not written any article yet.', 'untitled' => 'Untitled', 'scholar_intro' => 'At least one language is required. The administration publishes after review.',
        'locked' => 'This article has been published: contact the administration to edit it.', 'save_draft' => 'Save draft', 'submit_review' => 'Submit for approval',
        'field' => [
            'title' => 'Title', 'excerpt' => 'Excerpt', 'excerpt_help' => 'Short summary shown in lists (generated from the content if empty).', 'content' => 'Content', 'content_help' => 'Use the "quote" block for verses and hadiths in Arabic.',
            'tags' => 'Tags', 'tags_placeholder' => 'prayer, ramadan, family', 'published_at' => 'Publication date', 'published_at_help' => 'Future date + "published" or "scheduled" status: automatic publication at that time.',
            'featured' => 'Feature on the blog', 'allow_comments' => 'Allow comments', 'cover' => 'Cover image', 'cover_help' => 'JPG, PNG or WebP, 5 MB max. Never a figurative depiction of the prophets or the Companions.',
            'audio' => 'Audio version', 'audio_current' => 'Current audio: :name', 'audio_remove' => 'Remove the audio', 'audio_help' => 'Record or attach a reading of the article (MP3, M4A, OGG).',
        ],
        'flash' => ['saved' => 'Draft saved.', 'submitted' => 'Article submitted for approval. The administration has been notified.', 'title_required' => 'Enter a title in at least one language.'],
    ],
];
