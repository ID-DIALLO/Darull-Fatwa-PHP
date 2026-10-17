-- 2026_10_17_phase5_administration.sql
-- Phase 5 : paramètres supplémentaires de l'administration (WhatsApp API Cloud, SMTP, Turnstile).
-- Les valeurs secrètes (is_secret = 1) sont chiffrées avec APP_KEY par l'application ; elles restent
-- vides ici et se renseignent dans Administration → Paramètres. INSERT IGNORE : sans doublon.
SET NAMES utf8mb4;

INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, is_secret) VALUES
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
