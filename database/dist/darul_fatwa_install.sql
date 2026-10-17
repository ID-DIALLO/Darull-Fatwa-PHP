-- Darul Fatwa – دار الفتوى — installation complète de la base de données
-- Généré par bin/export-sql.php le 2026-10-17 ; importez ce fichier dans une base VIDE (phpMyAdmin > Importer).
-- Ne contient aucun compte : créez le premier administrateur via /setup ou php bin/create-admin.php.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ---------------------------------------------------------------------
-- Schéma
-- ---------------------------------------------------------------------
-- =====================================================================
--  Darul Fatwa — schéma de base de données
--  MySQL 8.0+ / MariaDB 10.6+  ·  InnoDB  ·  utf8mb4
-- =====================================================================
--  Hébergement mutualisé : la base est créée depuis le panneau d'hébergement ;
--  bin/install.php ignore alors les deux instructions CREATE DATABASE / USE ci-dessous.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


-- ---------------------------------------------------------------------
-- 1. Utilisateurs, rôles, sécurité
-- ---------------------------------------------------------------------
CREATE TABLE roles (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(30)  NOT NULL,
  name        VARCHAR(60)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid               CHAR(36)     NOT NULL,
  role_id            TINYINT UNSIGNED NOT NULL DEFAULT 1,
  full_name          VARCHAR(120) NOT NULL,
  display_name       VARCHAR(60)  NULL,
  email              VARCHAR(190) NULL,
  phone              VARCHAR(20)  NULL,               -- format E.164 : +223XXXXXXXX
  whatsapp           VARCHAR(20)  NULL,
  password_hash      VARCHAR(255) NOT NULL,
  locale             ENUM('fr','ar','en') NOT NULL DEFAULT 'fr',
  gender             ENUM('m','f') NULL,
  country            CHAR(2)      NULL DEFAULT 'ML',
  city               VARCHAR(80)  NULL,
  avatar_path        VARCHAR(255) NULL,
  preferred_channel  ENUM('platform','whatsapp','both') NOT NULL DEFAULT 'platform',
  notify_email       TINYINT(1)   NOT NULL DEFAULT 1,
  notify_whatsapp    TINYINT(1)   NOT NULL DEFAULT 1,
  email_verified_at  DATETIME     NULL,
  phone_verified_at  DATETIME     NULL,
  forum_rules_accepted_at DATETIME NULL,
  status             ENUM('active','pending','suspended','banned') NOT NULL DEFAULT 'active',
  last_login_at      DATETIME     NULL,
  last_login_ip      VARCHAR(45)  NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_uuid (uuid),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone (phone),
  KEY idx_users_role (role_id),
  KEY idx_users_status (status),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id),
  CONSTRAINT chk_users_contact CHECK (email IS NOT NULL OR phone IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_tokens (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  type        ENUM('email_verify','password_reset','remember','phone_otp','invite') NOT NULL,
  token_hash  CHAR(64)     NOT NULL,                  -- SHA-256 du jeton (jamais le jeton en clair)
  code_hash   CHAR(64)     NULL,                      -- invitations : code à 6 chiffres haché avec le jeton
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_tokens_hash (token_hash),
  KEY idx_user_tokens_user_type (user_id, type),
  CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  identifier  VARCHAR(190) NOT NULL,
  ip_address  VARCHAR(45)  NOT NULL,
  success     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempts_identifier (identifier, created_at),
  KEY idx_login_attempts_ip (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  rl_key      VARCHAR(190) NOT NULL,                  -- ex. "contact:ip:1.2.3.4"
  hits        INT UNSIGNED NOT NULL DEFAULT 0,
  reset_at    DATETIME     NOT NULL,
  PRIMARY KEY (rl_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Rubriques et professeurs
-- ---------------------------------------------------------------------
CREATE TABLE rubriques (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id   SMALLINT UNSIGNED NULL,
  code        VARCHAR(40)  NOT NULL,                  -- identifiant stable : tawhid, islam, coran...
  icon        VARCHAR(120) NULL,                      -- chemin du SVG
  color       CHAR(7)      NULL,
  image_path  VARCHAR(255) NULL,
  sort_order  SMALLINT     NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rubriques_code (code),
  KEY idx_rubriques_parent (parent_id),
  CONSTRAINT fk_rubriques_parent FOREIGN KEY (parent_id) REFERENCES rubriques (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rubrique_translations (
  rubrique_id      SMALLINT UNSIGNED NOT NULL,
  locale           ENUM('fr','ar','en') NOT NULL,
  name             VARCHAR(120) NOT NULL,
  slug             VARCHAR(140) NOT NULL,
  short_description VARCHAR(255) NULL,
  description      TEXT NULL,
  meta_title       VARCHAR(160) NULL,
  meta_description VARCHAR(255) NULL,
  PRIMARY KEY (rubrique_id, locale),
  UNIQUE KEY uq_rubrique_tr_slug (locale, slug),
  CONSTRAINT fk_rubrique_tr FOREIGN KEY (rubrique_id) REFERENCES rubriques (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scholar_profiles (
  user_id             INT UNSIGNED NOT NULL,
  languages           SET('fr','ar','en','bm','other') NOT NULL DEFAULT 'fr',
  max_open_questions  SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  can_self_assign     TINYINT(1) NOT NULL DEFAULT 1,
  requires_review     TINYINT(1) NOT NULL DEFAULT 0,  -- relecture obligatoire par un admin
  is_public           TINYINT(1) NOT NULL DEFAULT 1,
  is_available        TINYINT(1) NOT NULL DEFAULT 1,  -- pour l'assignation automatique
  photo_path          VARCHAR(255) NULL,
  sort_order          SMALLINT NOT NULL DEFAULT 0,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_scholar_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scholar_translations (
  user_id     INT UNSIGNED NOT NULL,
  locale      ENUM('fr','ar','en') NOT NULL,
  title       VARCHAR(80)  NULL,                      -- Cheick, Oustaz, Dr...
  bio         TEXT NULL,
  PRIMARY KEY (user_id, locale),
  CONSTRAINT fk_scholar_tr FOREIGN KEY (user_id) REFERENCES scholar_profiles (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scholar_rubriques (
  user_id      INT UNSIGNED NOT NULL,
  rubrique_id  SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, rubrique_id),
  KEY idx_scholar_rubriques_rubrique (rubrique_id),
  CONSTRAINT fk_sr_user FOREIGN KEY (user_id) REFERENCES scholar_profiles (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_sr_rubrique FOREIGN KEY (rubrique_id) REFERENCES rubriques (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Fichiers (audio, images, documents)
-- ---------------------------------------------------------------------
CREATE TABLE media_files (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid               CHAR(36)     NOT NULL,
  owner_id           INT UNSIGNED NULL,
  kind               ENUM('audio','image','document') NOT NULL,
  disk               ENUM('private','public') NOT NULL DEFAULT 'private',
  path               VARCHAR(255) NOT NULL,           -- relatif à storage/ ou public/uploads/
  original_name      VARCHAR(255) NULL,
  mime_type          VARCHAR(100) NOT NULL,
  size_bytes         INT UNSIGNED NOT NULL,
  duration_seconds   SMALLINT UNSIGNED NULL,
  checksum_sha256    CHAR(64)     NULL,
  conversion_status  ENUM('none','pending','done','failed') NOT NULL DEFAULT 'none',
  converted_path     VARCHAR(255) NULL,
  transcript         TEXT NULL,                       -- transcription manuelle (ou IA en V2)
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_media_uuid (uuid),
  KEY idx_media_owner (owner_id),
  CONSTRAINT fk_media_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Questions (module central)
-- ---------------------------------------------------------------------
CREATE TABLE questions (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference              VARCHAR(20)  NULL,           -- DF-2026-000123 (renseignée juste après l'INSERT)
  user_id                INT UNSIGNED NOT NULL,
  rubrique_id            SMALLINT UNSIGNED NOT NULL,
  title                  VARCHAR(200) NULL,
  input_type             ENUM('audio','text','mixed') NOT NULL,
  source                 ENUM('web','whatsapp','admin') NOT NULL DEFAULT 'web',
  spoken_language        ENUM('fr','ar','en','bm','other') NOT NULL DEFAULT 'fr',
  answer_language        ENUM('fr','ar','en','bm') NOT NULL DEFAULT 'fr',
  answer_channel         ENUM('platform','whatsapp','both') NOT NULL DEFAULT 'platform',
  whatsapp_number        VARCHAR(20)  NULL,
  visibility             ENUM('private','publishable') NOT NULL DEFAULT 'private',
  status                 ENUM('new','assigned','in_progress','needs_info','answered','published','rejected','closed')
                         NOT NULL DEFAULT 'new',
  priority               ENUM('normal','high') NOT NULL DEFAULT 'normal',
  assigned_to            INT UNSIGNED NULL,
  requested_scholar_id   INT UNSIGNED NULL,                 -- professeur souhaité par le membre (facultatif)
  assigned_at            DATETIME NULL,
  answered_at            DATETIME NULL,
  first_response_minutes INT UNSIGNED NULL,
  answer_message_id      INT UNSIGNED NULL,           -- réponse officielle (FK ajoutée plus bas)
  rejection_reason       VARCHAR(255) NULL,
  user_found_helpful     TINYINT(1) NULL,
  closed_at              DATETIME NULL,
  ip_address             VARCHAR(45)  NULL,
  user_agent             VARCHAR(255) NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at             DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_questions_reference (reference),
  KEY idx_questions_status (status, created_at),
  KEY idx_questions_rubrique_status (rubrique_id, status),
  KEY idx_questions_assigned_status (assigned_to, status),
  KEY idx_questions_requested (requested_scholar_id),
  KEY idx_questions_user (user_id, created_at),
  CONSTRAINT fk_questions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_questions_rubrique FOREIGN KEY (rubrique_id) REFERENCES rubriques (id),
  CONSTRAINT fk_questions_assigned FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_questions_requested FOREIGN KEY (requested_scholar_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_messages (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id      INT UNSIGNED NOT NULL,
  sender_id        INT UNSIGNED NULL,
  kind             ENUM('question','answer','follow_up','info_request','internal_note') NOT NULL,
  body             MEDIUMTEXT NULL,                   -- HTML purifié (HTMLPurifier)
  media_id         INT UNSIGNED NULL,                 -- note vocale
  references_json  JSON NULL,                         -- [{"type":"quran","surah":2,"ayah_from":255,"ayah_to":255}, ...]
  language         ENUM('fr','ar','en','bm','other') NULL,
  review_status    ENUM('not_required','pending','approved','rejected') NOT NULL DEFAULT 'not_required',
  reviewed_by      INT UNSIGNED NULL,
  reviewed_at      DATETIME NULL,
  sent_via_whatsapp_at DATETIME NULL,
  read_at          DATETIME NULL,                     -- lu par le destinataire
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_qm_question (question_id, created_at),
  KEY idx_qm_review (review_status),
  CONSTRAINT fk_qm_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE,
  CONSTRAINT fk_qm_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_qm_media FOREIGN KEY (media_id) REFERENCES media_files (id) ON DELETE SET NULL,
  CONSTRAINT fk_qm_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE questions
  ADD CONSTRAINT fk_questions_answer FOREIGN KEY (answer_message_id)
      REFERENCES question_messages (id) ON DELETE SET NULL;

CREATE TABLE question_status_logs (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id  INT UNSIGNED NOT NULL,
  from_status  VARCHAR(20)  NULL,
  to_status    VARCHAR(20)  NOT NULL,
  changed_by   INT UNSIGNED NULL,
  comment      VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_qsl_question (question_id, created_at),
  CONSTRAINT fk_qsl_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE,
  CONSTRAINT fk_qsl_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_assignments (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id    INT UNSIGNED NOT NULL,
  scholar_id     INT UNSIGNED NULL,
  assigned_by    INT UNSIGNED NULL,                   -- NULL = assignation automatique
  reason         VARCHAR(255) NULL,
  assigned_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unassigned_at  DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_qa_question (question_id),
  KEY idx_qa_scholar (scholar_id, assigned_at),
  CONSTRAINT fk_qassign_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE,
  CONSTRAINT fk_qassign_scholar FOREIGN KEY (scholar_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_qassign_by FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Base publique de questions-réponses (anonymisée)
-- ---------------------------------------------------------------------
CREATE TABLE qa_publications (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id     INT UNSIGNED NULL,                  -- reste NULL si la question source est supprimée
  rubrique_id     SMALLINT UNSIGNED NOT NULL,
  scholar_id      INT UNSIGNED NULL,
  audio_media_id  INT UNSIGNED NULL,                  -- uniquement la voix du professeur
  status          ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  views           INT UNSIGNED NOT NULL DEFAULT 0,
  published_at    DATETIME NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_qa_question (question_id),
  KEY idx_qa_rubrique_status (rubrique_id, status, published_at),
  CONSTRAINT fk_qapub_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE SET NULL,
  CONSTRAINT fk_qapub_rubrique FOREIGN KEY (rubrique_id) REFERENCES rubriques (id),
  CONSTRAINT fk_qapub_scholar FOREIGN KEY (scholar_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_qapub_audio FOREIGN KEY (audio_media_id) REFERENCES media_files (id) ON DELETE SET NULL,
  CONSTRAINT fk_qapub_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qa_publication_translations (
  publication_id   INT UNSIGNED NOT NULL,
  locale           ENUM('fr','ar','en') NOT NULL,
  title            VARCHAR(200) NOT NULL,
  slug             VARCHAR(220) NOT NULL,
  question_text    TEXT NOT NULL,
  answer_html      MEDIUMTEXT NOT NULL,
  references_json  JSON NULL,
  meta_description VARCHAR(255) NULL,
  PRIMARY KEY (publication_id, locale),
  UNIQUE KEY uq_qa_tr_slug (locale, slug),
  FULLTEXT KEY ft_qa_tr (title, question_text, answer_html),
  CONSTRAINT fk_qa_tr FOREIGN KEY (publication_id) REFERENCES qa_publications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Blog
-- ---------------------------------------------------------------------
CREATE TABLE articles (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  author_id        INT UNSIGNED NULL,
  rubrique_id      SMALLINT UNSIGNED NULL,
  cover_path       VARCHAR(255) NULL,
  audio_media_id   INT UNSIGNED NULL,
  status           ENUM('draft','submitted','scheduled','published','archived') NOT NULL DEFAULT 'draft',
  is_featured      TINYINT(1) NOT NULL DEFAULT 0,
  allow_comments   TINYINT(1) NOT NULL DEFAULT 1,
  views            INT UNSIGNED NOT NULL DEFAULT 0,
  reading_minutes  TINYINT UNSIGNED NULL,
  published_at     DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at       DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_articles_status (status, published_at),
  KEY idx_articles_rubrique (rubrique_id, status),
  CONSTRAINT fk_articles_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_articles_rubrique FOREIGN KEY (rubrique_id) REFERENCES rubriques (id) ON DELETE SET NULL,
  CONSTRAINT fk_articles_audio FOREIGN KEY (audio_media_id) REFERENCES media_files (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE article_translations (
  article_id       INT UNSIGNED NOT NULL,
  locale           ENUM('fr','ar','en') NOT NULL,
  title            VARCHAR(200) NOT NULL,
  slug             VARCHAR(220) NOT NULL,
  excerpt          VARCHAR(500) NULL,
  content          MEDIUMTEXT NOT NULL,
  meta_title       VARCHAR(160) NULL,
  meta_description VARCHAR(255) NULL,
  PRIMARY KEY (article_id, locale),
  UNIQUE KEY uq_article_tr_slug (locale, slug),
  FULLTEXT KEY ft_article_tr (title, excerpt, content),
  CONSTRAINT fk_article_tr FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  locale   ENUM('fr','ar','en') NOT NULL,
  name     VARCHAR(60)  NOT NULL,
  slug     VARCHAR(80)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tags_slug (locale, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE article_tag (
  article_id  INT UNSIGNED NOT NULL,
  tag_id      INT UNSIGNED NOT NULL,
  PRIMARY KEY (article_id, tag_id),
  KEY idx_article_tag_tag (tag_id),
  CONSTRAINT fk_at_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
  CONSTRAINT fk_at_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  commentable_type  ENUM('article','qa') NOT NULL,
  commentable_id    INT UNSIGNED NOT NULL,
  user_id           INT UNSIGNED NOT NULL,
  parent_id         INT UNSIGNED NULL,
  body              TEXT NOT NULL,
  status            ENUM('pending','approved','rejected','spam') NOT NULL DEFAULT 'pending',
  moderated_by      INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comments_target (commentable_type, commentable_id, status),
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments (id) ON DELETE CASCADE,
  CONSTRAINT fk_comments_moderator FOREIGN KEY (moderated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. Forum
-- ---------------------------------------------------------------------
CREATE TABLE forum_categories (
  id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rubrique_id  SMALLINT UNSIGNED NULL,
  icon         VARCHAR(120) NULL,
  sort_order   SMALLINT NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_fc_rubrique FOREIGN KEY (rubrique_id) REFERENCES rubriques (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE forum_category_translations (
  category_id  SMALLINT UNSIGNED NOT NULL,
  locale       ENUM('fr','ar','en') NOT NULL,
  name         VARCHAR(120) NOT NULL,
  slug         VARCHAR(140) NOT NULL,
  description  VARCHAR(255) NULL,
  PRIMARY KEY (category_id, locale),
  UNIQUE KEY uq_fc_tr_slug (locale, slug),
  CONSTRAINT fk_fc_tr FOREIGN KEY (category_id) REFERENCES forum_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE forum_topics (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id        SMALLINT UNSIGNED NOT NULL,
  user_id            INT UNSIGNED NOT NULL,
  title              VARCHAR(200) NOT NULL,
  slug               VARCHAR(220) NOT NULL,           -- URL : /forum/sujet/{id}-{slug}
  body               MEDIUMTEXT NOT NULL,
  media_id           INT UNSIGNED NULL,
  language           ENUM('fr','ar','en','bm','other') NOT NULL DEFAULT 'fr',
  status             ENUM('pending','open','locked','hidden') NOT NULL DEFAULT 'open',
  is_pinned          TINYINT(1) NOT NULL DEFAULT 0,
  views              INT UNSIGNED NOT NULL DEFAULT 0,
  replies_count      INT UNSIGNED NOT NULL DEFAULT 0,
  likes_count        INT UNSIGNED NOT NULL DEFAULT 0,
  last_post_at       DATETIME NULL,
  last_post_user_id  INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_ft_category (category_id, is_pinned, last_post_at),
  FULLTEXT KEY ft_forum_topics (title, body),
  CONSTRAINT fk_ft_category FOREIGN KEY (category_id) REFERENCES forum_categories (id),
  CONSTRAINT fk_ft_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ft_media FOREIGN KEY (media_id) REFERENCES media_files (id) ON DELETE SET NULL,
  CONSTRAINT fk_ft_last_user FOREIGN KEY (last_post_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE forum_posts (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_id        INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  quoted_post_id  INT UNSIGNED NULL,
  body            MEDIUMTEXT NOT NULL,
  media_id        INT UNSIGNED NULL,
  is_validated    TINYINT(1) NOT NULL DEFAULT 0,      -- « Réponse validée par un professeur »
  validated_by    INT UNSIGNED NULL,
  status          ENUM('pending','visible','hidden') NOT NULL DEFAULT 'visible',
  likes_count     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_fp_topic (topic_id, created_at),
  CONSTRAINT fk_fp_topic FOREIGN KEY (topic_id) REFERENCES forum_topics (id) ON DELETE CASCADE,
  CONSTRAINT fk_fp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_fp_quoted FOREIGN KEY (quoted_post_id) REFERENCES forum_posts (id) ON DELETE SET NULL,
  CONSTRAINT fk_fp_media FOREIGN KEY (media_id) REFERENCES media_files (id) ON DELETE SET NULL,
  CONSTRAINT fk_fp_validator FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE forum_subscriptions (
  user_id     INT UNSIGNED NOT NULL,
  topic_id    INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, topic_id),
  KEY idx_fs_topic (topic_id),
  CONSTRAINT fk_fs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_fs_topic FOREIGN KEY (topic_id) REFERENCES forum_topics (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. Interactions : j'aime, favoris, signalements
-- ---------------------------------------------------------------------
CREATE TABLE likes (
  user_id        INT UNSIGNED NOT NULL,
  likeable_type  ENUM('forum_topic','forum_post','article','qa') NOT NULL,
  likeable_id    INT UNSIGNED NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, likeable_type, likeable_id),
  KEY idx_likes_target (likeable_type, likeable_id),
  CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorites (
  user_id            INT UNSIGNED NOT NULL,
  favoritable_type   ENUM('article','qa') NOT NULL,
  favoritable_id     INT UNSIGNED NOT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, favoritable_type, favoritable_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id      INT UNSIGNED NULL,
  reportable_type  ENUM('forum_topic','forum_post','comment','idea','idea_comment') NOT NULL,
  reportable_id    INT UNSIGNED NOT NULL,
  reason           ENUM('spam','offensive','off_topic','misinformation','other') NOT NULL,
  details          VARCHAR(500) NULL,
  status           ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  handled_by       INT UNSIGNED NULL,
  handled_at       DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reports_status (status, created_at),
  KEY idx_reports_target (reportable_type, reportable_id),
  CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_reports_handler FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. Boîte à idées
-- ---------------------------------------------------------------------
CREATE TABLE ideas (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  category        ENUM('platform','content','event','other') NOT NULL DEFAULT 'other',
  title           VARCHAR(200) NOT NULL,
  description     TEXT NOT NULL,
  status          ENUM('pending','new','under_review','accepted','done','declined') NOT NULL DEFAULT 'pending',
  votes_count     INT UNSIGNED NOT NULL DEFAULT 0,
  comments_count  INT UNSIGNED NOT NULL DEFAULT 0,
  admin_response  TEXT NULL,
  responded_by    INT UNSIGNED NULL,
  responded_at    DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_ideas_status (status, votes_count),
  CONSTRAINT fk_ideas_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ideas_responder FOREIGN KEY (responded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE idea_votes (
  idea_id     INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (idea_id, user_id),
  KEY idx_iv_user (user_id),
  CONSTRAINT fk_iv_idea FOREIGN KEY (idea_id) REFERENCES ideas (id) ON DELETE CASCADE,
  CONSTRAINT fk_iv_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE idea_comments (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  idea_id     INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  body        TEXT NOT NULL,
  status      ENUM('pending','visible','hidden') NOT NULL DEFAULT 'visible',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ic_idea (idea_id, created_at),
  CONSTRAINT fk_ic_idea FOREIGN KEY (idea_id) REFERENCES ideas (id) ON DELETE CASCADE,
  CONSTRAINT fk_ic_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. Accueil et pages (CMS)
-- ---------------------------------------------------------------------
CREATE TABLE banners (
  id             SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  image_desktop  VARCHAR(255) NOT NULL,
  image_mobile   VARCHAR(255) NULL,
  link_url       VARCHAR(255) NULL,
  link2_url      VARCHAR(255) NULL,
  sort_order     SMALLINT NOT NULL DEFAULT 0,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  starts_at      DATETIME NULL,
  ends_at        DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_banners_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE banner_translations (
  banner_id      SMALLINT UNSIGNED NOT NULL,
  locale         ENUM('fr','ar','en') NOT NULL,
  title          VARCHAR(160) NOT NULL,
  subtitle       VARCHAR(300) NULL,
  button_label   VARCHAR(60)  NULL,
  button2_label  VARCHAR(60)  NULL,
  image_alt      VARCHAR(160) NULL,
  PRIMARY KEY (banner_id, locale),
  CONSTRAINT fk_banner_tr FOREIGN KEY (banner_id) REFERENCES banners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE home_sections (
  code        VARCHAR(40) NOT NULL,                   -- hero, voice_cta, how_it_works, rubriques, latest_qa...
  sort_order  SMALLINT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  settings    JSON NULL,                              -- ex. {"limit":6}
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE daily_quotes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type          ENUM('ayah','hadith') NOT NULL,
  arabic_text   TEXT NOT NULL,
  reference     VARCHAR(160) NOT NULL,                -- ex. « Al-Baqara 2:255 », « Bukhari n° 1 »
  display_date  DATE NULL,                            -- NULL = rotation automatique
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dq_date (display_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE daily_quote_translations (
  quote_id     INT UNSIGNED NOT NULL,
  locale       ENUM('fr','ar','en') NOT NULL,
  translation  TEXT NOT NULL,
  PRIMARY KEY (quote_id, locale),
  CONSTRAINT fk_dq_tr FOREIGN KEY (quote_id) REFERENCES daily_quotes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
  id              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code            VARCHAR(40) NOT NULL,               -- about, terms, privacy, faq, forum-rules
  template        VARCHAR(40) NOT NULL DEFAULT 'default',
  show_in_footer  TINYINT(1) NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  sort_order      SMALLINT NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE page_translations (
  page_id          SMALLINT UNSIGNED NOT NULL,
  locale           ENUM('fr','ar','en') NOT NULL,
  title            VARCHAR(160) NOT NULL,
  slug             VARCHAR(180) NOT NULL,
  content          MEDIUMTEXT NOT NULL,
  meta_title       VARCHAR(160) NULL,
  meta_description VARCHAR(255) NULL,
  PRIMARY KEY (page_id, locale),
  UNIQUE KEY uq_page_tr_slug (locale, slug),
  CONSTRAINT fk_page_tr FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. Contact et newsletter
-- ---------------------------------------------------------------------
CREATE TABLE contact_messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(190) NULL,
  phone       VARCHAR(20)  NULL,
  subject     ENUM('general','partnership','technical','become_scholar','other') NOT NULL DEFAULT 'general',
  message     TEXT NOT NULL,
  locale      ENUM('fr','ar','en') NOT NULL DEFAULT 'fr',
  status      ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
  ip_address  VARCHAR(45) NULL,
  replied_by  INT UNSIGNED NULL,
  replied_at  DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contact_status (status, created_at),
  CONSTRAINT fk_contact_replier FOREIGN KEY (replied_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE newsletter_subscribers (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(190) NOT NULL,
  locale        ENUM('fr','ar','en') NOT NULL DEFAULT 'fr',
  status        ENUM('pending','active','unsubscribed') NOT NULL DEFAULT 'pending',
  token_hash    CHAR(64) NOT NULL,
  confirmed_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_newsletter_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. Notifications, files d'envoi, WhatsApp
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  type        VARCHAR(60) NOT NULL,                   -- question.answered, question.needs_info...
  data        JSON NULL,                              -- {"question_id":12,"reference":"DF-2026-000012"}
  url         VARCHAR(255) NULL,
  read_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user (user_id, read_at, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_queue (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  channel       ENUM('email','whatsapp') NOT NULL,
  recipient     VARCHAR(190) NOT NULL,
  template      VARCHAR(80)  NOT NULL,
  locale        ENUM('fr','ar','en') NOT NULL DEFAULT 'fr',
  payload       JSON NULL,
  status        ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
  attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error    TEXT NULL,
  scheduled_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at       DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mq_status (status, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE whatsapp_messages (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  direction      ENUM('in','out') NOT NULL,
  mode           ENUM('link','cloud_api') NOT NULL DEFAULT 'link',
  wa_message_id  VARCHAR(128) NULL,
  phone          VARCHAR(20)  NOT NULL,
  user_id        INT UNSIGNED NULL,
  question_id    INT UNSIGNED NULL,
  type           ENUM('text','audio','template','link','image','other') NOT NULL DEFAULT 'text',
  body           TEXT NULL,
  media_id       INT UNSIGNED NULL,
  status         VARCHAR(20) NULL,                    -- sent, delivered, read, failed (V2)
  payload        JSON NULL,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wa_message (wa_message_id),
  KEY idx_wa_phone (phone, created_at),
  KEY idx_wa_question (question_id),
  CONSTRAINT fk_wa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_wa_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE SET NULL,
  CONSTRAINT fk_wa_media FOREIGN KEY (media_id) REFERENCES media_files (id) ON DELETE SET NULL,
  CONSTRAINT fk_wa_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. Paramètres et journal d'activité
-- ---------------------------------------------------------------------
CREATE TABLE settings (
  setting_key    VARCHAR(100) NOT NULL,
  setting_value  TEXT NULL,
  setting_group  VARCHAR(40)  NOT NULL DEFAULT 'general',
  is_secret      TINYINT(1)   NOT NULL DEFAULT 0,     -- valeur chiffrée (APP_KEY) et masquée dans l'admin
  updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NULL,
  action        VARCHAR(80) NOT NULL,                 -- question.assigned, user.banned, settings.updated...
  subject_type  VARCHAR(40) NULL,
  subject_id    INT UNSIGNED NULL,
  details       JSON NULL,
  ip_address    VARCHAR(45) NULL,
  user_agent    VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_al_subject (subject_type, subject_id),
  KEY idx_al_user (user_id, created_at),
  KEY idx_al_action (action, created_at),
  CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Données initiales : 01_roles.sql
-- ---------------------------------------------------------------------
-- 01_roles.sql
-- Données initiales Darul Fatwa (cahier des charges §10.4). Exécuté par bin/install.php.
SET NAMES utf8mb4;

INSERT INTO roles (id, slug, name) VALUES
 (1,'member','Membre'), (2,'scholar','Professeur'), (3,'moderator','Modérateur'),
 (4,'admin','Administrateur'), (5,'superadmin','Super-administrateur');

-- ---------------------------------------------------------------------
-- Données initiales : 02_rubriques.sql
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- Données initiales : 03_pages.sql
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- Données initiales : 04_forum.sql
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- Données initiales : 05_home_sections.sql
-- ---------------------------------------------------------------------
-- 05_home_sections.sql
-- Données initiales Darul Fatwa (cahier des charges §10.4). Exécuté par bin/install.php.
SET NAMES utf8mb4;

INSERT INTO home_sections (code, sort_order, is_active) VALUES
 ('hero',1,1), ('voice_cta',2,1), ('how_it_works',3,1), ('rubriques',4,1), ('latest_qa',5,1),
 ('daily_quote',6,1), ('latest_articles',7,1), ('scholars',8,1), ('stats',9,1),
 ('forum_ideas',10,1), ('cta',11,1), ('newsletter',12,1);

-- ---------------------------------------------------------------------
-- Données initiales : 06_settings.sql
-- ---------------------------------------------------------------------
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

-- Phase 7 : forum
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
 ('forum_first_posts_moderated','3','forum'),
 ('forum_banned_words','','forum');

-- ---------------------------------------------------------------------
-- Données initiales : 07_banners.sql
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- Données initiales : 08_daily_quotes.sql
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- Données initiales : 09_articles_initiaux.sql
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- Migrations déjà incluses dans le schéma
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
  migration  VARCHAR(190) NOT NULL,
  batch      INT UNSIGNED NOT NULL DEFAULT 0,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO schema_migrations (migration, batch) VALUES ('2026_10_17_phase2_contenus_initiaux.sql', 0);
INSERT IGNORE INTO schema_migrations (migration, batch) VALUES ('2026_10_17_phase5_administration.sql', 0);
INSERT IGNORE INTO schema_migrations (migration, batch) VALUES ('2026_10_17_phase9_invitations.sql', 0);
INSERT IGNORE INTO schema_migrations (migration, batch) VALUES ('2026_10_18_requested_scholar.sql', 0);

SET FOREIGN_KEY_CHECKS = 1;
