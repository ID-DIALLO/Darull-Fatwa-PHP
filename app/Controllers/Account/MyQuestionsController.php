<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Question;
use App\Models\Rubrique;
use App\Services\ActivityLog;
use App\Services\AudioException;
use App\Services\AudioService;
use App\Services\QuestionService;
use App\Services\WorkflowException;

/**
 * « Mes questions » (§4.4) : liste filtrée, détail avec fil et réponse, précision, résolue, avis.
 */
final class MyQuestionsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $filters = [
            'tab'      => (string) $request->query('statut', $request->query('status', 'all')),
            'rubrique' => (string) $request->query('rubrique', ''),
            'period'   => (string) $request->query('periode', $request->query('period', '')),
            'q'        => (string) $request->query('q', ''),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $result = Question::forMember((int) $user['id'], $filters, $page, 10);

        $counts = ['all' => 0];
        foreach (Database::fetchAll('SELECT status, COUNT(*) AS n FROM questions WHERE user_id = ? AND deleted_at IS NULL GROUP BY status', [(int) $user['id']]) as $row) {
            $counts['all'] += (int) $row['n'];
            $group = Question::groupOf((string) $row['status']);
            $counts[$group] = ($counts[$group] ?? 0) + (int) $row['n'];
        }

        return $this->view('account/questions/index', [
            'result'    => $result,
            'filters'   => $filters,
            'counts'    => $counts,
            'rubriques' => Rubrique::allActive(),
        ]);
    }

    public function show(Request $request, string $reference): Response
    {
        $question = $this->ownQuestion($reference);
        $messages = Question::messages((int) $question['id']);
        $answer = null;
        foreach ($messages as $message) {
            if ($message['kind'] === 'answer') {
                $answer = $message;
            }
        }
        $timeline = Database::fetchAll(
            'SELECT l.*, u.full_name AS actor_name FROM question_status_logs l LEFT JOIN users u ON u.id = l.changed_by WHERE l.question_id = ? ORDER BY l.created_at, l.id',
            [(int) $question['id']],
        );
        // Marquer les messages de l'équipe comme lus par le membre
        Database::execute('UPDATE question_messages SET read_at = UTC_TIMESTAMP() WHERE question_id = ? AND read_at IS NULL AND sender_id <> ? AND kind <> ?', [(int) $question['id'], (int) Auth::id(), 'internal_note']);

        $whatsappMessage = __('questions.whatsapp.message', ['reference' => $question['reference'], 'rubrique' => $question['rubrique_name']]);

        return $this->view('account/questions/show', [
            'question'     => $question,
            'messages'     => $messages,
            'answer'       => $answer,
            'timeline'     => $timeline,
            'canFollowUp'  => in_array($question['status'], ['new', 'assigned', 'in_progress', 'needs_info', 'answered'], true),
            'canResolve'   => in_array($question['status'], ['answered', 'published'], true),
            'whatsappLink' => whatsapp_link($whatsappMessage),
            'maxSeconds'   => AudioService::maxSeconds(),
        ]);
    }

    public function followUp(Request $request, string $reference): Response
    {
        $question = $this->ownQuestion($reference);
        if (!in_array($question['status'], ['new', 'assigned', 'in_progress', 'needs_info', 'answered'], true)) {
            throw new HttpException(403);
        }
        $data = $this->validate($request, [
            'body'       => 'nullable|string|max:10000',
            'media_uuid' => 'nullable|string|max:36|regex:/^[0-9a-f-]{36}$/',
        ]);
        $mediaUuid = $data['media_uuid'] ?? null;
        $attached = $request->file('audio_file');
        if (empty($mediaUuid) && $attached !== null && ($attached['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $mediaUuid = AudioService::store($attached, (int) Auth::id(), null, 'upload')['uuid'];
            } catch (AudioException $e) {
                throw new ValidationException(['audio_file' => [$e->userMessage()]]);
            }
        }
        try {
            QuestionService::addMemberMessage($question, Auth::user(), $data['body'] ?? null, $mediaUuid !== null ? (string) $mediaUuid : null);
        } catch (\InvalidArgumentException) {
            throw new ValidationException(['body' => [__('questions.errors.empty')]]);
        } catch (WorkflowException) {
            throw new HttpException(409);
        }

        return $this->redirectToRoute('account.questions.show', ['reference' => $reference])->with('success', __('questions.detail.follow_up_sent'));
    }

    public function resolve(Request $request, string $reference): Response
    {
        $question = $this->ownQuestion($reference);
        try {
            QuestionService::resolve($question, (int) Auth::id());
        } catch (WorkflowException) {
            return $this->redirectToRoute('account.questions.show', ['reference' => $reference])->with('error', __('questions.errors.cannot_resolve'));
        }

        return $this->redirectToRoute('account.questions.show', ['reference' => $reference])->with('success', __('questions.detail.resolved'));
    }

    public function feedback(Request $request, string $reference): Response
    {
        $question = $this->ownQuestion($reference);
        $data = $this->validate($request, ['helpful' => 'required|in:0,1']);
        Question::update((int) $question['id'], ['user_found_helpful' => (int) $data['helpful']]);
        ActivityLog::record('question.feedback', 'question', (int) $question['id'], ['helpful' => (int) $data['helpful']]);

        return $this->redirectToRoute('account.questions.show', ['reference' => $reference])->with('success', __('questions.detail.feedback_thanks'));
    }

    /** @return array<string, mixed> */
    private function ownQuestion(string $reference): array
    {
        $question = Question::findByReference($reference);
        if ($question === null || (int) $question['user_id'] !== (int) Auth::id()) {
            throw new HttpException(404);
        }

        return $question;
    }
}
