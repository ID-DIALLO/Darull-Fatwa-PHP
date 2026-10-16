-- 04_forum.sql
-- Données initiales Darul Fatwa (cahier des charges §4.9, §10.4). Exécuté par bin/install.php.
-- Une catégorie par rubrique + « Discussions générales » + « Vie de la communauté ».
SET NAMES utf8mb4;

INSERT INTO forum_categories (id, rubrique_id, icon, sort_order) VALUES
 (1,1,'/assets/img/rubriques/tawhid.svg',1),
 (2,2,'/assets/img/rubriques/islam.svg',2),
 (3,3,'/assets/img/rubriques/coran.svg',3),
 (4,4,'/assets/img/rubriques/hadiths.svg',4),
 (5,5,'/assets/img/rubriques/prophete.svg',5),
 (6,6,'/assets/img/rubriques/baya.svg',6),
 (7,NULL,'bi-chat-dots',7),
 (8,NULL,'bi-people',8);

INSERT INTO forum_category_translations (category_id, locale, name, slug, description) VALUES
 (1,'fr','L''Unicité de Dieu','unicite-de-dieu','Échanges autour du Tawhid et de la foi en Dieu Unique.'),
 (1,'ar','التوحيد','التوحيد','نقاشات حول توحيد الله تعالى والإيمان به.'),
 (1,'en','The Oneness of God','oneness-of-god','Discussions about Tawhid and faith in the One God.'),
 (2,'fr','L''Islam','islam','Les piliers, la pratique et la vie du musulman au quotidien.'),
 (2,'ar','الإسلام','الإسلام','أركان الإسلام والعبادات وحياة المسلم اليومية.'),
 (2,'en','Islam','islam','The pillars, practice and daily life of the Muslim.'),
 (3,'fr','Le Coran','coran','Lecture, compréhension et méditation du Saint Coran.'),
 (3,'ar','القرآن الكريم','القرآن-الكريم','تلاوة القرآن الكريم وفهمه وتدبّره.'),
 (3,'en','The Holy Quran','holy-quran','Recitation, understanding and reflection on the Holy Quran.'),
 (4,'fr','Les Hadiths','hadiths','Les enseignements prophétiques et leurs sources.'),
 (4,'ar','الحديث النبوي الشريف','الحديث-النبوي','التعاليم النبوية ومصادرها.'),
 (4,'en','Hadith','hadith','Prophetic teachings and their sources.'),
 (5,'fr','Le Prophète Mohammad (ﷺ)','prophete-mohammad','La Sira, le caractère et la tradition du Prophète ﷺ.'),
 (5,'ar','النبي محمد ﷺ','النبي-محمد','السيرة النبوية وأخلاق النبي ﷺ وسنته.'),
 (5,'en','Prophet Muhammad (ﷺ)','prophet-muhammad','The Sira, character and tradition of the Prophet ﷺ.'),
 (6,'fr','La Baya (les six serments)','baya-six-serments','Échanges sur la Baya et les six serments.'),
 (6,'ar','البيعة','البيعة','نقاشات حول البيعة والعهود الستة.'),
 (6,'en','Bay''ah (the six oaths)','bayah-six-oaths','Discussions about the Bay''ah and the six oaths.'),
 (7,'fr','Discussions générales','discussions-generales','Questions et échanges de portée générale.'),
 (7,'ar','نقاشات عامة','نقاشات-عامة','أسئلة ونقاشات عامة.'),
 (7,'en','General discussions','general-discussions','General questions and discussions.'),
 (8,'fr','Vie de la communauté','vie-de-la-communaute','Annonces, entraide et vie de la communauté Darul Fatwa.'),
 (8,'ar','حياة المجتمع','حياة-المجتمع','إعلانات وتعاون وحياة مجتمع دار الفتوى.'),
 (8,'en','Community life','community-life','Announcements, mutual help and Darul Fatwa community life.');
