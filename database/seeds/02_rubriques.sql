-- 02_rubriques.sql — descriptions provisoires, à valider par les professeurs
-- Données initiales Darul Fatwa (cahier des charges §10.4). Exécuté par bin/install.php.
SET NAMES utf8mb4;

INSERT INTO rubriques (id, code, icon, color, sort_order) VALUES
 (1,'tawhid','/assets/img/rubriques/tawhid.svg','#0B5D45',1),
 (2,'islam','/assets/img/rubriques/islam.svg','#0E7356',2),
 (3,'coran','/assets/img/rubriques/coran.svg','#C9A24B',3),
 (4,'hadiths','/assets/img/rubriques/hadiths.svg','#8C6D2C',4),
 (5,'prophete','/assets/img/rubriques/prophete.svg','#0B5D45',5),
 (6,'baya','/assets/img/rubriques/baya.svg','#073D2E',6);

INSERT INTO rubrique_translations (rubrique_id, locale, name, slug, short_description) VALUES
 (1,'fr','L''Unicité de Dieu','unicite-de-dieu','Questions sur le Tawhid, la foi en Dieu Unique et Ses attributs.'),
 (1,'ar','التوحيد','التوحيد','أسئلة حول توحيد الله تعالى والإيمان به وصفاته.'),
 (1,'en','The Oneness of God','oneness-of-god','Questions about Tawhid, faith in the One God and His attributes.'),
 (2,'fr','L''Islam','islam','Les piliers, la pratique et la vie du musulman au quotidien.'),
 (2,'ar','الإسلام','الإسلام','أركان الإسلام والعبادات وحياة المسلم اليومية.'),
 (2,'en','Islam','islam','The pillars, practice and daily life of the Muslim.'),
 (3,'fr','Le Coran','coran','Lecture, compréhension, exégèse et règles liées au Saint Coran.'),
 (3,'ar','القرآن الكريم','القرآن-الكريم','تلاوة القرآن الكريم وفهمه وتفسيره وأحكامه.'),
 (3,'en','The Holy Quran','holy-quran','Recitation, understanding, exegesis and rulings of the Holy Quran.'),
 (4,'fr','Les Hadiths','hadiths','Les paroles et enseignements prophétiques, leurs sources et leur authenticité.'),
 (4,'ar','الحديث النبوي الشريف','الحديث-النبوي','أقوال النبي ﷺ وتعاليمه ومصادرها ودرجات صحتها.'),
 (4,'en','Hadith','hadith','Prophetic sayings and teachings, their sources and authenticity.'),
 (5,'fr','Le Prophète Mohammad (ﷺ)','prophete-mohammad','La vie (Sira), le caractère et la tradition du Prophète ﷺ.'),
 (5,'ar','النبي محمد ﷺ','النبي-محمد','السيرة النبوية وأخلاق النبي ﷺ وسنته.'),
 (5,'en','Prophet Muhammad (ﷺ)','prophet-muhammad','The life (Sira), character and tradition of the Prophet ﷺ.'),
 (6,'fr','La Baya (les six serments)','baya-six-serments','Questions sur la Baya et les six serments.'),
 (6,'ar','البيعة','البيعة','أسئلة حول البيعة والعهود الستة.'),
 (6,'en','Bay''ah (the six oaths)','bayah-six-oaths','Questions about the Bay''ah and the six oaths.');

