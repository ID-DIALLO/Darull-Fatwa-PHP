-- 06_settings.sql
-- Données initiales Darul Fatwa (cahier des charges §10.4). Exécuté par bin/install.php.
SET NAMES utf8mb4;

INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
 ('site_name','Darul Fatwa','general'),
 ('site_slogan_fr','Réponses islamiques authentiques','general'),
 ('site_slogan_ar','أجوبة إسلامية موثوقة','general'),
 ('site_slogan_en','Authentic Islamic Answers','general'),
 ('contact_email','contact@darulfatwa.com','contact'),
 ('contact_phone','+223XXXXXXXX','contact'),
 ('contact_address','Bamako, Mali','contact'),
 ('whatsapp_number','+223XXXXXXXX','whatsapp'),
 ('whatsapp_mode','link','whatsapp'),
 ('voice_max_seconds','300','questions'),
 ('audio_max_mb','10','questions'),
 ('questions_per_day','5','questions'),
 ('question_late_hours','72','questions'),
 ('auto_assign','0','questions'),
 ('auto_close_days','30','questions'),
 ('prophet_honorific_fr','ﷺ','content'),
 ('maintenance_mode','0','general'),
 ('opening_hours','','contact'),
 ('social_facebook','','social'),
 ('social_youtube','','social'),
 ('social_instagram','','social'),
 ('social_telegram','','social'),
 ('social_x','','social'),
 ('social_tiktok','','social'),
 ('stats_answered','','home'),
 ('stats_members','','home'),
 ('stats_articles','','home'),
 ('stats_scholars','','home');

-- Phase 5 : paramètres de l'administration (secrets chiffrés par l'application, vides à l'installation)
INSERT INTO settings (setting_key, setting_value, setting_group, is_secret) VALUES
 ('whatsapp_phone_number_id','','whatsapp',0),
 ('whatsapp_access_token',NULL,'whatsapp',1),
 ('whatsapp_app_secret',NULL,'whatsapp',1),
 ('whatsapp_verify_token',NULL,'whatsapp',1),
 ('mail_host','','mail',0),
 ('mail_port','','mail',0),
 ('mail_encryption','tls','mail',0),
 ('mail_username','','mail',0),
 ('mail_password',NULL,'mail',1),
 ('mail_from_address','','mail',0),
 ('mail_from_name','','mail',0),
 ('turnstile_site_key','','security',0),
 ('turnstile_secret_key',NULL,'security',1);
