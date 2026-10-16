-- 01_roles.sql
-- Données initiales Darul Fatwa (cahier des charges §10.4). Exécuté par bin/install.php.
SET NAMES utf8mb4;

INSERT INTO roles (id, slug, name) VALUES
 (1,'member','Membre'), (2,'scholar','Professeur'), (3,'moderator','Modérateur'),
 (4,'admin','Administrateur'), (5,'superadmin','Super-administrateur');

