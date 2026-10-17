<?php

// مراجع الإجابات (القرآن، الحديث، الكتب) — العربية
return [
    'quran'  => 'القرآن الكريم، سورة :surah (:number)، الآية/الآيات :verses',
    'hadith' => 'حديث رواه :collection، رقم :number',
    'page'   => 'ص :page',
    'collections' => [
        'bukhari'  => 'البخاري',
        'muslim'   => 'مسلم',
        'abudawud' => 'أبو داود',
        'tirmidhi' => 'الترمذي',
        'nasai'    => 'النسائي',
        'ibnmajah' => 'ابن ماجه',
        'muwatta'  => 'الموطأ (مالك)',
        'ahmad'    => 'أحمد (المسند)',
        'other'    => 'مصدر آخر',
    ],
    'types' => ['quran' => 'القرآن', 'hadith' => 'حديث', 'book' => 'كتاب'],
];
