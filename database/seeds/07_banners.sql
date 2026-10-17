-- 07_banners.sql — bannières du Hero (cahier des charges, Annexe D.1). Exécuté par bin/install.php.
-- Images par défaut : fonds géométriques vectoriels (à remplacer par des photographies d'architecture dans l'administration).
-- Liens « route:nom » : résolus dans la langue courante ; un bouton dont la route n'existe pas encore est masqué.
SET NAMES utf8mb4;

INSERT INTO banners (id, image_desktop, image_mobile, link_url, link2_url, sort_order, is_active) VALUES
 (1,'/assets/img/placeholders/hero-1.svg',NULL,'route:questions.create','route:rubriques.index',1,1),
 (2,'/assets/img/placeholders/hero-2.svg',NULL,'route:questions.create',NULL,2,1),
 (3,'/assets/img/placeholders/hero-3.svg',NULL,'route:blog.index','route:forum.index',3,1);

INSERT INTO banner_translations (banner_id, locale, title, subtitle, button_label, button2_label, image_alt) VALUES
 (1,'fr','Posez vos questions sur l''Islam, en toute confiance','Des réponses authentiques, fondées sur le Coran et la Sunna, données par des professeurs qualifiés.','Poser une question','Découvrir les rubriques',''),
 (1,'ar','اطرح أسئلتك عن الإسلام بكل ثقة','إجابات موثوقة مستندة إلى القرآن والسنة يقدّمها أساتذة مؤهَّلون.','اطرح سؤالك','اكتشف الأقسام',''),
 (1,'en','Ask your questions about Islam with confidence','Authentic answers grounded in the Quran and the Sunnah, given by qualified scholars.','Ask a question','Explore topics',''),
 (2,'fr','Votre voix suffit','Enregistrez votre question en vocal, dans votre langue. Nous vous répondons sur la plateforme ou sur WhatsApp.','Enregistrer ma question',NULL,''),
 (2,'ar','صوتك يكفي','سجّل سؤالك صوتيًا بلغتك، ونجيبك على المنصة أو عبر واتساب.','سجّل سؤالي',NULL,''),
 (2,'en','Your voice is enough','Record your question by voice, in your own language. We answer on the platform or on WhatsApp.','Record my question',NULL,''),
 (3,'fr','Apprendre, partager, grandir ensemble','Articles, questions-réponses et forum pour approfondir votre savoir.','Lire le blog','Rejoindre le forum',''),
 (3,'ar','نتعلّم ونتشارك وننمو معًا','مقالات وأسئلة وأجوبة ومنتدى لتعميق معرفتك.','اقرأ المدونة','انضم إلى المنتدى',''),
 (3,'en','Learn, share and grow together','Articles, Q&A and a forum to deepen your knowledge.','Read the blog','Join the forum','');
