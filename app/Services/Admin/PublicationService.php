<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Core\Lang;
use App\Models\Question;
use App\Services\ActivityLog;
use App\Services\Html;
use App\Services\QuestionWorkflow;
use App\Services\SlugService;
use App\Services\Translations;

/**
 * Base publique de questions-réponses (§4.8) côté administration : création d'une publication
 * anonymisée à partir d'une question répondue (texte reformulé, sans nom ni voix du demandeur), édition, statut.
 */
final class PublicationService
{
    /**
     * Publie une question : crée la Q/R anonymisée (dans la langue de la réponse) et passe la question à « published ».
     *
     * @param array<string, mixed> $question Question détaillée (Question::findDetailed)
     * @param array{title: string, question_text: string, answer_html: string, include_audio?: bool, status?: string} $data
     * @return int Identifiant de la publication
     */
    public static function publishFromQuestion(array $question, array $data, int $adminId): int
    {
        if (!in_array($question['status'], ['answered', 'closed'], true)) {
            throw new \InvalidArgumentException('not_answered');
        }
        $answer = Database::fetch(
            "SELECT m.*, mf.id AS media_file_id, mf.owner_id AS media_owner FROM question_messages m LEFT JOIN media_files mf ON mf.id = m.media_id
             WHERE m.question_id = ? AND m.kind = 'answer' AND m.review_status IN ('not_required','approved') ORDER BY m.id DESC LIMIT 1",
            [(int) $question['id']],
        );
        $locale = in_array($question['answer_language'], Lang::locales(), true) ? (string) $question['answer_language'] : Lang::getDefault();
        // L'audio n'est repris que s'il s'agit de la voix du professeur (jamais celle du demandeur, R2).
        $audioId = !empty($data['include_audio']) && $answer !== null && $answer['media_file_id'] !== null
            && (int) $answer['media_owner'] !== (int) $question['user_id'] ? (int) $answer['media_file_id'] : null;
        $status = ($data['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

        return Database::transaction(static function () use ($question, $data, $answer, $locale, $audioId, $status, $adminId): int {
            Database::execute(
                'INSERT INTO qa_publications (question_id, rubrique_id, scholar_id, audio_media_id, status, published_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [(int) $question['id'], (int) $question['rubrique_id'], $question['assigned_to'] !== null ? (int) $question['assigned_to'] : null, $audioId, $status, $status === 'published' ? gmdate('Y-m-d H:i:s') : null, $adminId],
            );
            $id = Database::lastInsertId();
            $title = mb_substr(trim((string) $data['title']), 0, 200);
            Translations::save('qa_publication_translations', 'publication_id', $id, [$locale => [
                'title'            => $title,
                'slug'             => SlugService::unique($title, 'qa_publication_translations', $locale),
                'question_text'    => Html::purify((string) $data['question_text'], 'basic'),
                'answer_html'      => Html::purify((string) $data['answer_html'], 'rich'),
                'references_json'  => $answer['references_json'] ?? null,
                'meta_description' => mb_substr(trim(strip_tags((string) $data['question_text'])), 0, 255),
            ]], ['title']);
            if ($question['status'] === 'answered') {
                QuestionWorkflow::transition($question, 'published', $adminId, null);
            }
            ActivityLog::record('admin.question.published', 'question', (int) $question['id'], ['publication_id' => $id], $adminId);

            return $id;
        });
    }

    /**
     * Liste paginée pour l'administration.
     *
     * @param array<string, string> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function list(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1 = 1'];
        $bindings = [Lang::getLocale(), Lang::getDefault(), Lang::getLocale(), Lang::getDefault()];
        if (in_array($filters['status'] ?? '', ['draft', 'published', 'archived'], true)) {
            $where[] = 'p.status = ?';
            $bindings[] = $filters['status'];
        }
        if (ctype_digit((string) ($filters['rubrique'] ?? ''))) {
            $where[] = 'p.rubrique_id = ?';
            $bindings[] = (int) $filters['rubrique'];
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM qa_publication_translations s WHERE s.publication_id = p.id AND (s.title LIKE ? OR s.question_text LIKE ?))';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            array_push($bindings, $like, $like);
        }
        $sql = 'FROM qa_publications p
                LEFT JOIN qa_publication_translations t ON t.publication_id = p.id AND t.locale = ?
                LEFT JOIN qa_publication_translations d ON d.publication_id = p.id AND d.locale = ?
                JOIN rubriques r ON r.id = p.rubrique_id
                LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ?
                LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
                LEFT JOIN users s ON s.id = p.scholar_id
                LEFT JOIN questions q ON q.id = p.question_id
                WHERE ' . implode(' AND ', $where);
        $total = (int) Database::value('SELECT COUNT(*) ' . $sql, $bindings);
        $page = max(1, $page);
        $rows = Database::fetchAll(
            'SELECT p.*, COALESCE(t.title, d.title) AS title, COALESCE(rt.name, rd.name) AS rubrique_name, s.full_name AS scholar_name, q.reference,
                    (SELECT GROUP_CONCAT(l.locale ORDER BY l.locale) FROM qa_publication_translations l WHERE l.publication_id = p.id) AS locales ' . $sql .
            ' ORDER BY p.created_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $bindings,
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /**
     * Mise à jour d'une publication (statut, rubrique, audio, traductions).
     *
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data, int $adminId): void
    {
        $current = Database::fetch('SELECT * FROM qa_publications WHERE id = ?', [$id]);
        if ($current === null) {
            throw new \InvalidArgumentException('not_found');
        }
        $status = in_array($data['status'] ?? '', ['draft', 'published', 'archived'], true) ? $data['status'] : $current['status'];
        Database::execute(
            'UPDATE qa_publications SET status = ?, rubrique_id = ?, audio_media_id = ?, published_at = COALESCE(published_at, ?) WHERE id = ?',
            [$status, (int) ($data['rubrique_id'] ?? $current['rubrique_id']), !empty($data['keep_audio']) ? $current['audio_media_id'] : null, $status === 'published' ? gmdate('Y-m-d H:i:s') : null, $id],
        );
        $translations = [];
        $existing = Translations::all('qa_publication_translations', 'publication_id', $id);
        foreach (Lang::locales() as $locale) {
            $title = Translations::text($data['title'][$locale] ?? null, 200);
            if ($title === null) {
                $translations[$locale] = ['title' => null];
                continue;
            }
            $translations[$locale] = [
                'title'            => $title,
                'slug'             => isset($existing[$locale]) && $existing[$locale]['title'] === $title ? $existing[$locale]['slug'] : SlugService::unique($title, 'qa_publication_translations', $locale, ['publication_id' => $id]),
                'question_text'    => Html::purify((string) ($data['question_text'][$locale] ?? ''), 'basic'),
                'answer_html'      => Html::purify((string) ($data['answer_html'][$locale] ?? ''), 'rich'),
                'meta_description' => Translations::text($data['meta_description'][$locale] ?? null, 255),
            ];
        }
        Translations::save('qa_publication_translations', 'publication_id', $id, $translations, ['title']);
        ActivityLog::record('admin.qa.updated', 'qa_publication', $id, ['status' => $status], $adminId);
        \App\Core\Cache::flush('qa.');
        \App\Core\Cache::flush('rubriques.');
    }

    public static function delete(int $id, int $adminId): void
    {
        Database::execute('DELETE FROM qa_publications WHERE id = ?', [$id]);
        ActivityLog::record('admin.qa.deleted', 'qa_publication', $id, [], $adminId);
        \App\Core\Cache::flush('qa.');
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $row = Database::fetch('SELECT p.*, q.reference, s.full_name AS scholar_name FROM qa_publications p LEFT JOIN questions q ON q.id = p.question_id LEFT JOIN users s ON s.id = p.scholar_id WHERE p.id = ?', [$id]);
        if ($row !== null) {
            $row['translations'] = Translations::all('qa_publication_translations', 'publication_id', $id);
        }

        return $row;
    }

    /**
     * Pré-remplissage du formulaire de publication à partir de la question (texte reformulable par l'admin).
     *
     * @param array<string, mixed> $question
     * @return array{title: string, question_text: string, answer_html: string, has_scholar_audio: bool}
     */
    public static function draftFromQuestion(array $question): array
    {
        $messages = Question::messages((int) $question['id'], false, true);
        $questionText = '';
        $answerHtml = '';
        $scholarAudio = false;
        foreach ($messages as $m) {
            if ($m['kind'] === 'question' && $questionText === '') {
                $questionText = (string) ($m['body'] ?? '');
            }
            if ($m['kind'] === 'answer') {
                $answerHtml = (string) ($m['body'] ?? '');
                $scholarAudio = !empty($m['media_id']) && (int) $m['sender_id'] !== (int) $question['user_id'];
            }
        }

        return [
            'title'             => (string) ($question['title'] ?? ''),
            'question_text'     => $questionText,
            'answer_html'       => $answerHtml,
            'has_scholar_audio' => $scholarAudio,
        ];
    }
}
