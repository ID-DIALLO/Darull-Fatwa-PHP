-- 08_daily_quotes.sql — versets et hadiths du jour (rotation automatique). Exécuté par bin/install.php.
-- Textes arabes courts et connus ; à faire relire par les professeurs avant la mise en ligne (§14.4).
-- La « traduction » arabe est vide : la version arabe affiche le texte original seul.
SET NAMES utf8mb4;

INSERT INTO daily_quotes (id, type, arabic_text, reference, display_date, is_active) VALUES
 (1,'ayah','﴿ وَقُل رَّبِّ زِدْنِي عِلْمًا ﴾','20:114',NULL,1),
 (2,'ayah','﴿ فَإِنَّ مَعَ الْعُسْرِ يُسْرًا ﴾','94:5',NULL,1),
 (3,'ayah','﴿ وَمَن يَتَّقِ اللَّهَ يَجْعَل لَّهُ مَخْرَجًا ﴾','65:2',NULL,1),
 (4,'ayah','﴿ إِنَّ اللَّهَ مَعَ الصَّابِرِينَ ﴾','2:153',NULL,1),
 (5,'hadith','« إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ »','Bukhari 1',NULL,1),
 (6,'hadith','« الدِّينُ النَّصِيحَةُ »','Muslim 55',NULL,1),
 (7,'hadith','« خَيْرُكُمْ مَنْ تَعَلَّمَ الْقُرْآنَ وَعَلَّمَهُ »','Bukhari 5027',NULL,1),
 (8,'hadith','« مَنْ سَلَكَ طَرِيقًا يَلْتَمِسُ فِيهِ عِلْمًا سَهَّلَ اللَّهُ لَهُ بِهِ طَرِيقًا إِلَى الْجَنَّةِ »','Muslim 2699',NULL,1);

INSERT INTO daily_quote_translations (quote_id, locale, translation) VALUES
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
