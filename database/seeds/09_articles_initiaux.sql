-- 09_articles_initiaux.sql
-- Trois articles de départ (cahier des charges §17, Phase 9), en BROUILLON : ils doivent être relus et validés
-- par un professeur puis publiés depuis l'administration (charte éditoriale §14.9). Aucune image, aucun auteur.
SET NAMES utf8mb4;

INSERT INTO articles (id, author_id, rubrique_id, status, is_featured, allow_comments, reading_minutes) VALUES
 (1, NULL, 3, 'draft', 1, 1, 4),
 (2, NULL, 4, 'draft', 0, 1, 3),
 (3, NULL, 2, 'draft', 0, 1, 3);

INSERT INTO article_translations (article_id, locale, title, slug, excerpt, content, meta_title, meta_description) VALUES
 (1, 'fr', 'Comment bien lire le Coran : adab et recueillement', 'comment-bien-lire-le-coran-adab-et-recueillement',
  'Les règles de bienséance de la lecture du Coran et quelques conseils pratiques pour lire avec un cœur présent.',
  '<h2>La pureté</h2><p>Le lecteur veille à la pureté de son corps, de son vêtement et du lieu où il s''installe. Il se tourne, autant que possible, vers la qibla.</p><h2>L''intention et la présence du cœur</h2><p>La lecture commence par la demande de protection (<span lang="ar" dir="rtl">أعوذ بالله من الشيطان الرجيم</span>) puis la basmala. On lit lentement, avec <em>tartîl</em>, en réfléchissant au sens.</p><blockquote lang="ar" dir="rtl">إِنَّ هَٰذَا الْقُرْآنَ يَهْدِي لِلَّتِي هِيَ أَقْوَمُ</blockquote><p>« Ce Coran guide vers ce qu''il y a de plus droit » (Coran 17 : 9).</p><h2>La régularité</h2><p>Mieux vaut une page chaque jour qu''une longue lecture occasionnelle. Un programme simple : une partie (<em>juz''</em>) par mois, puis par semaine.</p><p><em>Article à relire et à compléter par un professeur avant publication.</em></p>',
  NULL, 'Adab de la lecture du Coran : pureté, intention, tartîl et régularité.'),
 (2, 'fr', 'Les actes ne valent que par les intentions', 'les-actes-ne-valent-que-par-les-intentions',
  'Le premier hadith du recueil de l''imam An-Nawawî rappelle que chaque acte est jugé selon l''intention qui l''anime.',
  '<p>D''après ''Umar ibn al-Khattâb (qu''Allah l''agrée), le Prophète <span lang="ar">ﷺ</span> a dit :</p><blockquote lang="ar" dir="rtl">إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ، وَإِنَّمَا لِكُلِّ امْرِئٍ مَا نَوَى</blockquote><p>« Les actes ne valent que par les intentions, et chacun n''aura que ce qu''il a eu l''intention de faire. » (Rapporté par Al-Bukhârî, n° 1, et Muslim, n° 1907.)</p><h2>Ce que le hadith enseigne</h2><ul><li>L''intention distingue l''adoration de l''habitude.</li><li>Un même acte peut être récompensé ou non selon ce que l''on vise.</li><li>L''intention se forme dans le cœur ; elle n''a pas besoin d''être prononcée.</li></ul><p><em>Article à relire et à compléter par un professeur avant publication.</em></p>',
  NULL, 'Explication du hadith « Les actes ne valent que par les intentions » (An-Nawawî, hadith n° 1).'),
 (3, 'fr', 'Les cinq piliers de l''Islam, expliqués simplement', 'les-cinq-piliers-de-l-islam-expliques-simplement',
  'L''attestation de foi, la prière, l''aumône légale, le jeûne du Ramadan et le pèlerinage : ce que chaque musulman doit connaître.',
  '<p>Le Prophète <span lang="ar">ﷺ</span> a dit : « L''Islam est bâti sur cinq piliers… » (Al-Bukhârî, n° 8 ; Muslim, n° 16).</p><h2>1. L''attestation de foi (<em>shahâda</em>)</h2><p>Témoigner qu''il n''y a de divinité qu''Allah et que Mohammad <span lang="ar">ﷺ</span> est Son messager.</p><h2>2. La prière (<em>salât</em>)</h2><p>Cinq prières quotidiennes, à leurs heures.</p><h2>3. L''aumône légale (<em>zakât</em>)</h2><p>Une part des biens, versée chaque année à ceux qui y ont droit.</p><h2>4. Le jeûne (<em>sawm</em>)</h2><p>Le mois de Ramadan, de l''aube au coucher du soleil.</p><h2>5. Le pèlerinage (<em>hajj</em>)</h2><p>Une fois dans la vie, pour qui en a la capacité.</p><p><em>Article à relire et à compléter par un professeur avant publication.</em></p>',
  NULL, 'Présentation claire des cinq piliers de l''Islam avec leurs sources.');

INSERT INTO tags (id, locale, name, slug) VALUES
 (1, 'fr', 'Coran', 'coran'), (2, 'fr', 'Adab', 'adab'), (3, 'fr', 'Hadith', 'hadith'), (4, 'fr', 'Intention', 'intention'), (5, 'fr', 'Piliers', 'piliers');

INSERT INTO article_tag (article_id, tag_id) VALUES (1, 1), (1, 2), (2, 3), (2, 4), (3, 5);
