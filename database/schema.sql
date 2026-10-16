-- =====================================================================
--  Darul Fatwa — schéma de base de données
--  MySQL 8.0+ / MariaDB 10.6+  ·  InnoDB  ·  utf8mb4
-- =====================================================================
--  Hébergement mutualisé : la base est créée depuis le panneau d'hébergement ;
--  bin/install.php ignore alors les deux instructions CREATE DATABASE / USE ci-dessous.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS darul_fatwa
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE darul_fatwa;

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
  type        ENUM('email_verify','password_reset','remember','phone_otp') NOT NULL,
  token_hash  CHAR(64)     NOT NULL,                  -- SHA-256 du jeton (jamais le jeton en clair)
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
  KEY idx_questions_user (user_id, created_at),
  CONSTRAINT fk_questions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_questions_rubrique FOREIGN KEY (rubrique_id) REFERENCES rubriques (id),
  CONSTRAINT fk_questions_assigned FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL
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
