-- 2026_10_17_phase2_contenus_initiaux.sql
-- Phase 2 : bannières, versets du jour et nouveaux paramètres pour une base déjà installée (bin/migrate.php).
-- Les mêmes données sont dans database/seeds/ pour une installation neuve. INSERT IGNORE : sans doublon.
SET NAMES utf8mb4;

INSERT IGNORE INTO banners (id, image_desktop, image_mobile, link_url, link2_url, sort_order, is_active) VALUES
 (1,'/assets/img/placeholders/hero-1.svg',NULL,'route:questions.create','route:rubriques.index',1,1),
 (2,'/assets/img/placeholders/hero-2.svg',NULL,'route:questions.create',NULL,2,1),
 (3,'/assets/img/placeholders/hero-3.svg',NULL,'route:blog.index','route:forum.index',3,1);

INSERT IGNORE INTO banner_translations (banner_id, locale, title, subtitle, button_label, button2_label, image_alt) VALUES
 (1,'fr','Posez vos questions sur l''Islam, en toute confiance','Des réponses authentiques, fondées sur le Coran et la Sunna, données par des professeurs qualifiés.','Poser une question','Découvrir les rubriques',''),
 (1,'ar','اطرح أسئلتك عن الإسلام بكل ثقة','إجابات موثوقة مستندة إلى القرآن والسنة يقدّمها أساتذة مؤهَّلون.','اطرح سؤالك','اكتشف الأقسام',''),
 (1,'en','Ask your questions about Islam with confidence','Authentic answers grounded in the Quran and the Sunnah, given by qualified scholars.','Ask a question','Explore topics',''),
 (2,'fr','Votre voix suffit','Enregistrez votre question en vocal, dans votre langue. Nous vous répondons sur la plateforme ou sur WhatsApp.','Enregistrer ma question',NULL,''),
 (2,'ar','صوتك يكفي','سجّل سؤالك صوتيًا بلغتك، ونجيبك على المنصة أو عبر واتساب.','سجّل سؤالي',NULL,''),
 (2,'en','Your voice is enough','Record your question by voice, in your own language. We answer on the platform or on WhatsApp.','Record my question',NULL,''),
 (3,'fr','Apprendre, partager, grandir ensemble','Articles, questions-réponses et forum pour approfondir votre savoir.','Lire le blog','Rejoindre le forum',''),
 (3,'ar','نتعلّم ونتشارك وننمو معًا','مقالات وأسئلة وأجوبة ومنتدى لتعميق معرفتك.','اقرأ المدونة','انضم إلى المنتدى',''),
 (3,'en','Learn, share and grow together','Articles, Q&A and a forum to deepen your knowledge.','Read the blog','Join the forum','');

INSERT IGNORE INTO daily_quotes (id, type, arabic_text, reference, display_date, is_active) VALUES
 (1,'ayah','﴿ وَقُل رَّبِّ زِدْنِي عِلْمًا ﴾','20:114',NULL,1),
 (2,'ayah','﴿ فَإِنَّ مَعَ الْعُسْرِ يُسْرًا ﴾','94:5',NULL,1),
 (3,'ayah','﴿ وَمَن يَتَّقِ اللَّهَ يَجْعَل لَّهُ مَخْرَجًا ﴾','65:2',NULL,1),
 (4,'ayah','﴿ إِنَّ اللَّهَ مَعَ الصَّابِرِينَ ﴾','2:153',NULL,1),
 (5,'hadith','« إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ »','Bukhari 1',NULL,1),
 (6,'hadith','« الدِّينُ النَّصِيحَةُ »','Muslim 55',NULL,1),
 (7,'hadith','« خَيْرُكُمْ مَنْ تَعَلَّمَ الْقُرْآنَ وَعَلَّمَهُ »','Bukhari 5027',NULL,1),
 (8,'hadith','« مَنْ سَلَكَ طَرِيقًا يَلْتَمِسُ فِيهِ عِلْمًا سَهَّلَ اللَّهُ لَهُ بِهِ طَرِيقًا إِلَى الْجَنَّةِ »','Muslim 2699',NULL,1);

INSERT IGNORE INTO daily_quote_translations (quote_id, locale, translation) VALUES
 (1,'fr','« Et dis : Ô mon Seigneur, accroît mes connaissances. » — Sourate Tâ-Hâ (20), verset 114'),
 (1,'ar',''),
 (1,'en','"And say: My Lord, increase me in knowledge." — Surah Ta-Ha (20), verse 114'),
 (2,'fr','« Certes, à côté de la difficulté est, certes, une facilité. » — Sourate Ash-Sharh (94), verset 5'),
 (2,'ar',''),
 (2,'en','"Indeed, with hardship comes ease." — Surah Ash-Sharh (94), verse 5'),
 (3,'fr','« Et quiconque craint Allah, Il lui donnera une issue favorable. » — Sourate At-Talâq (65), verset 2'),
 (3,'ar',''),
 (3,'en','"And whoever fears Allah, He will make for him a way out." — Surah At-Talaq (65), verse 2'),
 (4,'fr','« Certes, Allah est avec ceux qui sont endurants. » — Sourate Al-Baqara (2), verset 153'),
 (4,'ar',''),
 (4,'en','"Indeed, Allah is with the patient." — Surah Al-Baqarah (2), verse 153'),
 (5,'fr','« Les actes ne valent que par les intentions. » — Rapporté par Al-Bukhari (n° 1)'),
 (5,'ar',''),
 (5,'en','"Actions are judged by intentions." — Reported by Al-Bukhari (no. 1)'),
 (6,'fr','« La religion, c''est le bon conseil. » — Rapporté par Muslim (n° 55)'),
 (6,'ar',''),
 (6,'en','"Religion is sincere advice." — Reported by Muslim (no. 55)'),
 (7,'fr','« Le meilleur d''entre vous est celui qui apprend le Coran et l''enseigne. » — Rapporté par Al-Bukhari (n° 5027)'),
 (7,'ar',''),
 (7,'en','"The best of you are those who learn the Quran and teach it." — Reported by Al-Bukhari (no. 5027)'),
 (8,'fr','« Quiconque emprunte un chemin à la recherche du savoir, Allah lui facilite par cela un chemin vers le Paradis. » — Rapporté par Muslim (n° 2699)'),
 (8,'ar',''),
 (8,'en','"Whoever follows a path in pursuit of knowledge, Allah makes easy for him a path to Paradise." — Reported by Muslim (no. 2699)');

INSERT IGNORE INTO settings (setting_key, setting_value, setting_group) VALUES
 ('opening_hours','','contact'), ('social_facebook','','social'), ('social_youtube','','social'), ('social_instagram','','social'),
 ('social_telegram','','social'), ('social_x','','social'), ('social_tiktok','','social'),
 ('stats_answered','','home'), ('stats_members','','home'), ('stats_articles','','home'), ('stats_scholars','','home');
