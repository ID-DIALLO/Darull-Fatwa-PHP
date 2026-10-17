<?php

// نصوص الإشعارات (الجرس والبريد) — العربية. المعاملات: :reference، :reason…
return [
    'question' => [
        'received' => [
            'title'   => 'تم استلام السؤال :reference',
            'message' => 'أُحيل سؤالك إلى أساتذتنا. سيصلك إشعار فور توفر الإجابة.',
            'icon'    => 'check2-circle',
        ],
        'assigned' => [
            'title'   => 'سؤال جديد للمعالجة: :reference',
            'message' => 'أُسند إليك سؤال.',
            'icon'    => 'inbox',
        ],
        'needs_info' => [
            'title'   => 'مطلوب توضيح للسؤال :reference',
            'message' => 'يحتاج الأستاذ إلى توضيح للإجابة عن سؤالك.',
            'icon'    => 'question-circle',
        ],
        'info_received' => [
            'title'   => 'وصل توضيح للسؤال :reference',
            'message' => 'أكمل العضو سؤاله.',
            'icon'    => 'chat-left-dots',
        ],
        'follow_up' => [
            'title'   => 'سؤال متابعة بشأن :reference',
            'message' => 'طرح العضو سؤال متابعة بعد إجابتك.',
            'icon'    => 'arrow-return-right',
        ],
        'answered' => [
            'title'   => 'الإجابة متاحة للسؤال :reference',
            'message' => 'أجاب أحد الأساتذة عن سؤالك.',
            'icon'    => 'envelope-open-heart',
        ],
        'rejected' => [
            'title'   => 'السؤال :reference غير مقبول',
            'message' => 'تعذّر قبول سؤالك. السبب: :reason',
            'icon'    => 'x-circle',
        ],
        'new_for_staff' => [
            'title'   => 'سؤال جديد :reference',
            'message' => 'سؤال جديد بانتظار إسناده إلى أستاذ.',
            'icon'    => 'bell',
        ],
        'review_pending' => [
            'title'   => 'إجابة بانتظار المراجعة للسؤال :reference',
            'message' => 'إجابة بانتظار مراجعتك قبل إرسالها إلى العضو.',
            'icon'    => 'eye',
        ],
        'late' => [
            'title'   => 'سؤال متأخر: :reference',
            'message' => 'هذا السؤال بانتظار الإجابة منذ أكثر من :hours ساعة.',
            'icon'    => 'alarm',
        ],
        'reassign_requested' => [
            'title'   => 'طلب إعادة إسناد: :reference',
            'message' => 'يطلب الأستاذ إعفاءه من هذا السؤال. السبب: :reason',
            'icon'    => 'arrow-left-right',
        ],
        'publication_proposed' => [
            'title'   => 'اقتراح نشر: :reference',
            'message' => 'يقترح أستاذ نسخة مجهولة الهوية من هذا السؤال والجواب.',
            'icon'    => 'megaphone',
        ],
        'review_approved' => [
            'title'   => 'تم اعتماد الإجابة: :reference',
            'message' => 'اعتُمدت إجابتك وأُرسلت إلى العضو.',
            'icon'    => 'patch-check',
        ],
        'review_rejected' => [
            'title'   => 'إجابة تحتاج مراجعة: :reference',
            'message' => 'تطلب المراجعة تعديلات: :reason',
            'icon'    => 'pencil-square',
        ],
    ],
    'contact' => [
        'received' => [
            'title'   => 'رسالة تواصل جديدة',
            'message' => 'وصلت رسالة جديدة من نموذج الاتصال.',
            'icon'    => 'envelope',
        ],
    ],
    'forum' => [
        'reply' => [
            'title'   => 'رد جديد في «:topic»',
            'message' => 'ردّ :name في موضوع تتابعه.',
            'icon'    => 'chat-square-text',
        ],
    ],
    'idea' => [
        'status' => [
            'title'   => 'تغيّرت حالة فكرتك «:title»',
            'message' => 'الحالة الجديدة: :status',
            'icon'    => 'lightbulb',
        ],
    ],
];
