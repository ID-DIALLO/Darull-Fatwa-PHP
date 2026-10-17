-- Invitations des comptes créés par l'administration (professeurs, modérateurs…) :
-- lien d'activation + code à saisir avec son identifiant (user_tokens.type = 'invite', code haché).
ALTER TABLE user_tokens
  MODIFY type ENUM('email_verify','password_reset','remember','phone_otp','invite') NOT NULL,
  ADD COLUMN code_hash CHAR(64) NULL AFTER token_hash;
