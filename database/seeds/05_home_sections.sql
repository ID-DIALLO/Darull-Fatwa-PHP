-- 05_home_sections.sql
-- Données initiales Darul Fatwa (cahier des charges §10.4). Exécuté par bin/install.php.
SET NAMES utf8mb4;

INSERT INTO home_sections (code, sort_order, is_active) VALUES
 ('hero',1,1), ('voice_cta',2,1), ('how_it_works',3,1), ('rubriques',4,1), ('latest_qa',5,1),
 ('daily_quote',6,1), ('latest_articles',7,1), ('scholars',8,1), ('stats',9,1),
 ('forum_ideas',10,1), ('cta',11,1), ('newsletter',12,1);

