<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Lang;
use App\Models\Question;
use App\Models\User;

/**
 * Tâches périodiques métier (cron/run.php) : rappels de retard (§4.12) et clôture automatique (§4.2.3).
 */
final class Maintenance
{
    /**
     * Rappelle les questions en retard : notification au professeur assigné (ou aux modérateurs/administrateurs
     * si personne n'est assigné), une fois par jour et par question ; e-mail récapitulatif quotidien à l'équipe.
     *
     * @return int Nombre de questions signalées
     */
    public static function remindLateQuestions(): int
    {
        $late = ScholarService::lateQuestions();
        if ($late === []) {
            return 0;
        }
        $hours = QuestionService::lateHours();
        $staffRows = Database::fetchAll("SELECT u.* FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('moderator','admin','superadmin') AND u.status = 'active' AND u.deleted_at IS NULL");
        $staffIds = array_map(static fn (array $r): int => (int) $r['id'], $staffRows);
        $count = 0;
        foreach ($late as $question) {
            $alreadyToday = Database::value(
                "SELECT 1 FROM notifications WHERE type = 'question.late' AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.reference')) = ? AND created_at >= UTC_DATE() LIMIT 1",
                [(string) $question['reference']],
            );
            if ($alreadyToday !== null) {
                continue;
            }
            $data = ['reference' => (string) $question['reference'], 'hours' => $hours];
            if (!empty($question['assigned_to'])) {
                NotificationService::notify((int) $question['assigned_to'], 'question.late', $data, route_exists('scholar.questions.show') ? route('scholar.questions.show', ['reference' => $question['reference']]) : null, null);
            }
            NotificationService::notifyMany($staffIds, 'question.late', $data, route_exists('admin.questions.show') ? route('admin.questions.show', ['id' => $question['id']]) : '/admin', null);
            $count++;
        }
        if ($count > 0) {
            $references = array_map(static fn (array $q): string => (string) $q['reference'], $late);
            foreach ($staffRows as $admin) {
                if (!empty($admin['email']) && (int) $admin['notify_email'] === 1) {
                    QueueService::push('email', (string) $admin['email'], 'late_digest', (string) $admin['locale'], [
                        'name'       => User::publicName($admin),
                        'count'      => count($late),
                        'hours'      => $hours,
                        'references' => $references,
                        'url'        => url('/admin'),
                    ]);
                }
            }
        }

        return $count;
    }

    /** Clôture automatique des questions répondues depuis plus de « auto_close_days » jours. */
    public static function autoCloseAnswered(): int
    {
        $days = max(1, (int) (\App\Models\Setting::get('auto_close_days') ?? 30));
        $rows = Database::fetchAll(
            "SELECT * FROM questions WHERE status IN ('answered','published') AND deleted_at IS NULL AND answered_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)",
            [$days],
        );
        $closed = 0;
        foreach ($rows as $question) {
            try {
                QuestionWorkflow::transition($question, 'closed', null, 'Clôture automatique après ' . $days . ' jours');
                $closed++;
            } catch (WorkflowException) {
                continue;
            }
        }

        return $closed;
    }
}
