<?php

declare(strict_types=1);

namespace App\Controllers\Scholar;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Question;
use App\Models\Rubrique;
use App\Models\User;
use App\Services\AnswerService;
use App\Services\AssignmentService;
use App\Services\AudioException;
use App\Services\AudioService;
use App\Services\QuestionService;
use App\Services\QuestionWorkflow;
use App\Services\ScholarService;
use App\Services\Surahs;
use App\Services\WhatsApp\WhatsApp;
use App\Services\WorkflowException;

/**
 * File de travail et fiche question du professeur (§4.5) : prendre, répondre, demander une précision,
 * note interne, réassignation, WhatsApp, proposition de publication, transcription.
 */
final class QueueController extends Controller
{
    public function index(Request $request): Response
    {
        $file = $request->query('file') === 'a-prendre' || $request->query('file') === 'available' ? 'available' : 'mine';
        $filters = [
            'sort'     => (string) $request->query('tri', $request->query('sort', 'oldest')),
            'rubrique' => (string) $request->query('rubrique', ''),
            'language' => (string) $request->query('langue', $request->query('language', '')),
            'type'     => (string) $request->query('type', ''),
            'status'   => (string) $request->query('statut', $request->query('status', 'open')),
        ];
        $result = ScholarService::queue((int) Auth::id(), $file, $filters, max(1, (int) $request->query('page', 1)));

        return $this->view('scholar/queue', [
            'file'      => $file,
            'result'    => $result,
            'filters'   => $filters,
            'rubriques' => Rubrique::allActive(),
            'lateHours' => QuestionService::lateHours(),
        ]);
    }

    public function show(Request $request, string $reference): Response
    {
        $question = $this->accessibleQuestion($reference);
        $scholarId = (int) Auth::id();
        $mine = (int) ($question['assigned_to'] ?? 0) === $scholarId;

        // Le professeur ouvre une question qui vient de lui être assignée : elle passe « en cours »
        if ($mine && $question['status'] === 'assigned') {
            $question = Question::findByReference($reference) ?? $question;
            QuestionWorkflow::transition($question, 'in_progress', $scholarId);
            $question = Question::findByReference($reference) ?? $question;
        }
        if ($mine) {
            Database::execute('UPDATE question_messages SET read_at = UTC_TIMESTAMP() WHERE question_id = ? AND read_at IS NULL AND sender_id = ?', [(int) $question['id'], (int) $question['user_id']]);
        }
        $member = User::findWithRole((int) $question['user_id'], true) ?? [];
        $messages = Question::messages((int) $question['id'], true, false);
        $pendingReview = AnswerService::pendingReview((int) $question['id']);
        $rejectedReview = Database::fetch('SELECT id FROM question_messages WHERE question_id = ? AND kind = ? AND review_status = ? ORDER BY id DESC LIMIT 1', [(int) $question['id'], 'answer', 'rejected']);
        $phone = AnswerService::phoneForScholar($question);
        $answer = null;
        foreach ($messages as $m) {
            if ($m['kind'] === 'answer' && in_array($m['review_status'], ['not_required', 'approved'], true)) {
                $answer = $m;
            }
        }
        $whatsappText = $phone['visible'] ? WhatsApp::scholarReplyText($question, (string) $question['answer_language'], $answer !== null ? AnswerService::summary($answer['body'] ?? null) : null) : null;

        return $this->view('scholar/question', [
            'question'      => $question,
            'member'        => $member,
            'messages'      => $messages,
            'answer'        => $answer,
            'mine'          => $mine,
            'canTake'       => AssignmentService::canSelfAssign($question, $scholarId),
            'canAct'        => $mine && in_array($question['status'], ['assigned', 'in_progress', 'needs_info'], true),
            'pendingReview' => $pendingReview,
            'rejectedReview' => $pendingReview === null && $rejectedReview !== null && in_array($question['status'], ['in_progress', 'assigned'], true),
            'phone'         => $phone,
            'whatsappLink'  => $whatsappText !== null && $phone['raw'] !== null ? WhatsApp::gateway()->conversationLink((string) $phone['raw'], $whatsappText) : null,
            'whatsappText'  => $whatsappText,
            'others'        => AnswerService::otherQuestions((int) $question['user_id'], (int) $question['id']),
            'timeline'      => $this->timeline($question),
            'assignments'   => AssignmentService::history((int) $question['id']),
            'surahs'        => Surahs::options(),
            'collections'   => AnswerService::COLLECTIONS,
            'maxSeconds'    => AudioService::maxSeconds(),
            'country'       => !empty($member['country']) ? \Locale::getDisplayRegion('-' . $member['country'], (string) config('app.intl_locales.' . locale(), 'fr_FR')) : null,
        ]);
    }

    public function take(Request $request, string $reference): Response
    {
        $question = $this->accessibleQuestion($reference);
        try {
            AssignmentService::selfAssign($question, (int) Auth::id());
        } catch (WorkflowException) {
            return $this->redirectToRoute('scholar.questions', ['file' => 'a-prendre'])->with('error', __('scholar.queue.cannot_take'));
        }

        return $this->redirectToRoute('scholar.questions.show', ['reference' => $reference])->with('success', __('scholar.queue.taken', ['reference' => $reference]));
    }

    public function answer(Request $request, string $reference): Response
    {
        $question = $this->ownQuestion($reference);
        $data = $this->validate($request, [
            'body_html'       => 'nullable|string|max:60000',
            'media_uuid'      => 'nullable|string|max:36|regex:/^[0-9a-f-]{36}$/',
            'answer_language' => 'required|in:fr,ar,en,bm',
        ]);
        $mediaUuid = $this->mediaFromRequest($request, $data['media_uuid'] ?? null);
        $references = $this->referencesFromRequest($request);
        try {
            $result = AnswerService::answer($question, Auth::user(), $data['body_html'] ?? null, $mediaUuid, $references, (string) $data['answer_language']);
        } catch (\InvalidArgumentException) {
            throw new ValidationException(['body_html' => [__('scholar.actions.answer_empty')]]);
        } catch (WorkflowException) {
            return $this->redirectToRoute('scholar.questions.show', ['reference' => $reference])->with('error', __('scholar.actions.wrong_state'));
        }

        return $this->redirectToRoute('scholar.questions.show', ['reference' => $reference])
            ->with('success', __($result['review'] ? 'scholar.actions.answer_submitted' : 'scholar.actions.answer_sent'));
    }

    public function requestInfo(Request $request, string $reference): Response
    {
        $question = $this->ownQuestion($reference);
        $data = $this->validate($request, ['body' => 'nullable|string|max:10000', 'media_uuid' => 'nullable|string|max:36|regex:/^[0-9a-f-]{36}$/']);
        $mediaUuid = $this->mediaFromRequest($request, $data['media_uuid'] ?? null);
        try {
            AnswerService::requestInfo($question, (int) Auth::id(), isset($data['body']) ? \App\Services\Html::fromPlainText((string) $data['body']) : null, $mediaUuid);
        } catch (\InvalidArgumentException) {
            throw new ValidationException(['body' => [__('scholar.actions.answer_empty')]]);
        } catch (WorkflowException) {
            return $this->redirectToRoute('scholar.questions.show', ['reference' => $reference])->with('error', __('scholar.actions.wrong_state'));
        }

        return $this->redirectToRoute('scholar.questions.show', ['reference' => $reference])->with('success', __('scholar.actions.request_info_sent'));
    }

    public function note(Request $request, string $reference): Response
    {
        $question = $this->accessibleQuestion($reference);
        $data = $this->validate($request, ['body' => 'required|string|min:2|max:5000']);
        AnswerService::internalNote($question, (int) Auth::id(), (string) $data['body']);

        return $this->redirectToRoute('scholar.questions.show', ['reference' => $reference])->with('success', __('scholar.actions.note_saved'));
    }

    public function reassign(Request $request, string $reference): Response
    {
        $question = $this->ownQuestion($reference);
        $data = $this->validate($request, ['reason' => 'required|string|min:5|max:500']);
        AnswerService::requestReassignment($question, (int) Auth::id(), (string) $data['reason']);

        return $this->redirectToRoute('scholar.questions.show', ['reference' => $reference])->with('success', __('scholar.actions.reassign_sent'));
    }

    public function whatsappSent(Request $request, string $reference): Response
    {
        $question = $this->ownQuestion($reference, false);
        $phone = AnswerService::phoneForScholar($question);
        if (!$phone['visible']) {
            return $this->redirectToRoute('scholar.questions.show', ['reference' => $reference])->with('error', __('scholar.actions.whatsapp_unavailable'));
        }
        $text = (string) $request->input('text', WhatsApp::scholarReplyText($question, (string) $question['answer_language']));
        AnswerService::markSentViaWhatsApp($question, (int) Auth::id(), mb_substr($text, 0, 4000));

        return $this->redirectToRoute('scholar.questions.show', ['reference' => $reference])->with('success', __('scholar.actions.whatsapp_marked'));
    }

    public function proposePublication(Request $request, string $reference): Response
    {
        $question = $this->ownQuestion($reference, false);
        if ($question['visibility'] !== 'publishable') {
            return $this->redirectToRoute('scholar.questions.show', ['reference' => $reference])->with('error', __('scholar.actions.publish_not_allowed'));
        }
        $data = $this->validate($request, ['title' => 'required|string|min:5|max:200', 'rephrased' => 'required|string|min:10|max:5000']);
        AnswerService::proposePublication($question, (int) Auth::id(), (string) $data['title'], (string) $data['rephrased']);

        return $this->redirectToRoute('scholar.questions.show', ['reference' => $reference])->with('success', __('scholar.actions.publish_sent'));
    }

    public function transcript(Request $request, string $reference): Response
    {
        $question = $this->accessibleQuestion($reference);
        $data = $this->validate($request, ['media_id' => 'required|integer', 'transcript' => 'nullable|string|max:20000']);
        $owned = Database::fetch('SELECT m.media_id FROM question_messages m WHERE m.question_id = ? AND m.media_id = ?', [(int) $question['id'], (int) $data['media_id']]);
        if ($owned === null) {
            throw new HttpException(404);
        }
        AnswerService::saveTranscript((int) $data['media_id'], $data['transcript'] ?? null);

        return $this->redirectToRoute('scholar.questions.show', ['reference' => $reference])->with('success', __('scholar.question.transcript_saved'));
    }

    /** @return array<string, mixed> */
    private function accessibleQuestion(string $reference): array
    {
        $question = Question::findByReference($reference);
        if ($question === null || !QuestionService::canView($question, Auth::user()) || (int) $question['user_id'] === (int) Auth::id()) {
            throw new HttpException(404);
        }

        return $question;
    }

    /** @return array<string, mixed> */
    private function ownQuestion(string $reference, bool $mustBeOpen = true): array
    {
        $question = $this->accessibleQuestion($reference);
        if ((int) ($question['assigned_to'] ?? 0) !== (int) Auth::id()) {
            throw new HttpException(403);
        }
        if ($mustBeOpen && !in_array($question['status'], ['assigned', 'in_progress', 'needs_info'], true)) {
            throw new HttpException(409);
        }

        return $question;
    }

    private function mediaFromRequest(Request $request, ?string $uuid): ?string
    {
        if ($uuid !== null && $uuid !== '') {
            return $uuid;
        }
        $file = $request->file('audio_file');
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                return (string) AudioService::store($file, Auth::id(), null, 'upload')['uuid'];
            } catch (AudioException $e) {
                throw new ValidationException(['audio_file' => [$e->userMessage()]]);
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function referencesFromRequest(Request $request): array
    {
        $raw = $request->input('references');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? array_values(array_filter($raw, 'is_array')) : [];
    }

    /**
     * Historique des statuts. Le nom réel du demandeur n'est jamais montré au professeur :
     * seul son pseudonyme (ou une mention anonyme) apparaît.
     *
     * @param array<string, mixed> $question
     * @return list<array<string, mixed>>
     */
    private function timeline(array $question): array
    {
        $rows = Database::fetchAll(
            'SELECT l.*, u.full_name AS actor_full_name, u.display_name AS actor_display_name
             FROM question_status_logs l LEFT JOIN users u ON u.id = l.changed_by
             WHERE l.question_id = ? ORDER BY l.created_at, l.id',
            [(int) $question['id']],
        );
        foreach ($rows as &$row) {
            $isMember = (int) ($row['changed_by'] ?? 0) === (int) $question['user_id'];
            $row['actor_name'] = $isMember
                ? ((string) ($row['actor_display_name'] ?? '') !== '' ? $row['actor_display_name'] : __('scholar.question.member_anonymous'))
                : $row['actor_full_name'];
            unset($row['actor_full_name'], $row['actor_display_name']);
        }
        unset($row);

        return $rows;
    }
}
