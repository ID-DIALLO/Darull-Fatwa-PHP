-- 03_pages.sql
-- Données initiales Darul Fatwa (cahier des charges §10.4). Exécuté par bin/install.php.
-- Contenus provisoires : à rédiger avec les professeurs (Annexe D.2 et D.3), puis à modifier dans l'administration.
SET NAMES utf8mb4;

INSERT INTO pages (id, code, show_in_footer, sort_order) VALUES
 (1,'about',1,1), (2,'terms',1,2), (3,'privacy',1,3), (4,'faq',1,4), (5,'forum-rules',1,5);

INSERT INTO page_translations (page_id, locale, title, slug, content) VALUES
 (1,'fr','À propos','a-propos','<p>Contenu à rédiger avec les professeurs : notre mission, notre démarche, nos professeurs, comment poser une question, nous contacter.</p>'),
 (1,'ar','من نحن','من-نحن','<p>محتوى سيُكتب بالتعاون مع الأساتذة: رسالتنا، منهجنا، أساتذتنا، كيفية طرح سؤال، التواصل معنا.</p>'),
 (1,'en','About us','about','<p>Content to be written with the scholars: our mission, our approach, our scholars, how to ask a question, contact us.</p>'),
 (2,'fr','Conditions générales d''utilisation','conditions-generales','<p>Conditions générales d''utilisation à rédiger.</p>'),
 (2,'ar','الشروط العامة للاستخدام','الشروط-العامة','<p>الشروط العامة للاستخدام قيد الإعداد.</p>'),
 (2,'en','Terms of use','terms','<p>Terms of use to be written.</p>'),
 (3,'fr','Politique de confidentialité','politique-de-confidentialite','<p>Politique de confidentialité à rédiger : finalités, données collectées, durées de conservation, droits, contact.</p>'),
 (3,'ar','سياسة الخصوصية','سياسة-الخصوصية','<p>سياسة الخصوصية قيد الإعداد: الأغراض، البيانات المجمَّعة، مدة الحفظ، الحقوق، التواصل.</p>'),
 (3,'en','Privacy policy','privacy','<p>Privacy policy to be written: purposes, data collected, retention periods, rights, contact.</p>'),
 (4,'fr','Questions fréquentes','faq','<p>FAQ à rédiger : Comment poser une question vocale ? Combien de temps pour une réponse ? Ma question est-elle confidentielle ? Puis-je recevoir la réponse sur WhatsApp ? Comment supprimer mon compte ?</p>'),
 (4,'ar','الأسئلة الشائعة','الأسئلة-الشائعة','<p>الأسئلة الشائعة قيد الإعداد.</p>'),
 (4,'en','Frequently asked questions','faq','<p>FAQ to be written.</p>'),
 (5,'fr','Charte du forum','charte-du-forum','<p>Charte du forum à rédiger.</p>'),
 (5,'ar','ميثاق المنتدى','ميثاق-المنتدى','<p>ميثاق المنتدى قيد الإعداد.</p>'),
 (5,'en','Forum rules','forum-rules','<p>Forum rules to be written.</p>');
