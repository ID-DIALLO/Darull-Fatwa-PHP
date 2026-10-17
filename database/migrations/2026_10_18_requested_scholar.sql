-- Question adressée à un professeur précis (choix facultatif du membre, §4.2.1 étendu).
ALTER TABLE questions
  ADD COLUMN requested_scholar_id INT UNSIGNED NULL AFTER assigned_to,
  ADD KEY idx_questions_requested (requested_scholar_id),
  ADD CONSTRAINT fk_questions_requested FOREIGN KEY (requested_scholar_id) REFERENCES users (id) ON DELETE SET NULL;
