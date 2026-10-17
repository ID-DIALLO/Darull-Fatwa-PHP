<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Question;
use App\Models\Rubrique;
use App\Models\User;
use App\Services\Admin\AdminQuestionService;
use App\Services\Admin\ExportService;
use App\Services\Admin\PublicationService;
use App\Services\AnswerService;
use App\Services\AssignmentService;
use App\Services\AudioService;
use App\Services\AudioException;
use App\Services\QuestionService;
use App\Services\ReferenceFormatter;
use App\Services\WorkflowException;

/**
 * Gestion complète des questions (§4.6.2) : tableau, fiche, assignation, statut, relecture,
 * publication anonymisée, import d'audio WhatsApp, actions groupées, export, création depuis WhatsApp.
 */
final class QuestionsController extends AdminController
{
    protected ?string $permission = 'questions.view_all';

    private const FILTER_KEYS = ['status', 'rubrique', 'language', 'channel', 'source', 'type', 'scholar', 'country', 'late', 'review', 'visibility', 'from', 'to', 'q', 'sort'];

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $filters = [];
        foreach (self::FILTER_KEYS as $key) {
            $filters[$key] = trim((string) $request->query($key, ''));
        }

        return $filters;
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return $this->view('admin/questions/index', [
            'filters'   => $filters,
            'result'    => AdminQuestionService::list($filters, $this->page($request)),
            'rubriques' => Rubrique::allActive(),
            'scholars'  => AdminQuestionService::scholars(),
            'countries' => AdminQuestionService::countries(),
            'lateHours' => QuestionService::lateHours(),
        ]);
    }

    public function export(Request $request): Response
    {
        $this->authorize('questions.export');
        $filters = $this->filters($request);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';
        $rows = AdminQuestionService::forExport($filters);
        \App\Services\ActivityLog::record('admin.question.exported', 'question', null, ['format' => $format, 'count' => count($rows), 'filters' => array_filter($filters)]);
        $headers = array_map(static fn (string $k): string => __('admin.questions.export.' . $k), ['reference', 'created_at', 'member', 'phone', 'email', 'country', 'rubrique', 'type', 'duration', 'spoken_language', 'answer_language', 'channel', 'source', 'status', 'scholar', 'assigned_at', 'answered_at', 'response_hours', 'title']);
        $generator = static function () use ($rows): \Generator {
            foreach ($rows as $r) {
                yield [
                    $r['reference'], format_date((string) $r['created_at'], true), $r['member_name'], $r['member_phone'], $r['member_email'], $r['member_country'],
                    $r['rubrique_name'], __('questions.types.' . $r['input_type']), $r['audio_seconds'] !== null ? sprintf('%d:%02d', intdiv((int) $r['audio_seconds'], 60), (int) $r['audio_seconds'] % 60) : '',
                    __('questions.languages.' . $r['spoken_language']), __('questions.languages.' . $r['answer_language']), __('questions.channels.' . $r['answer_channel']),
                    __('admin.questions.sources.' . $r['source']), __('questions.status.' . $r['status']), $r['scholar_name'],
                    format_date($r['assigned_at'], true), format_date($r['answered_at'], true),
                    $r['first_response_minutes'] !== null ? (string) round(((int) $r['first_response_minutes']) / 60, 1) : '', $r['title'],
                ];
            }
        };

        return ExportService::download($format, 'questions', $headers, $generator());
    }

    public function show(Request $request, string $id): Response
    {
        $question = AdminQuestionService::sheet($this->id($id));
        if ($question === null) {
            throw new HttpException(404);
        }
        $draft = in_array($question['status'], ['answered', 'closed'], true) ? PublicationService::draftFromQuestion($question) : null;

        return $this->view('admin/questions/show', [
            'question'   => $question,
            'timeline'   => AdminQuestionService::timeline((int) $question['id']),
            'scholars'   => AdminQuestionService::scholars(),
            'pending'    => AnswerService::pendingReview((int) $question['id']),
            'nextStatuses' => \App\Services\QuestionWorkflow::TRANSITIONS[$question['status']] ?? [],
            'draft'      => $draft,
            'publication' => Database::fetch('SELECT id, status FROM qa_publications WHERE question_id = ? ORDER BY id DESC LIMIT 1', [(int) $question['id']]),
            'formatter'  => static fn (array $ref): string => ReferenceFormatter::format($ref),
            'lateHours'  => QuestionService::lateHours(),
        ]);
    }

    public function assign(Request $request, string $id): Response
    {
        $this->authorize('questions.assign');
        $question = $this->find($id);
        $data = $this->validate($request, ['scholar_id' => 'required|integer', 'comment' => 'max:255']);
        try {
            AssignmentService::assign($question, (int) $data['scholar_id'], (int) Auth::id(), $data['comment'] ?: null);
        } catch (WorkflowException|\InvalidArgumentException) {
            return $this->done('admin.questions.show', ['id' => $id], __('admin.questions.flash.transition_denied'), 'error');
        }

        return $this->done('admin.questions.show', ['id' => $id], __('admin.questions.flash.assigned'));
    }

    public function status(Request $request, string $id): Response
    {
        $this->authorize('questions.change_status');
        $question = $this->find($id);
        $data = $this->validate($request, ['status' => 'required|in:' . implode(',', Question::STATUSES), 'comment' => 'max:1000']);
        try {
            AdminQuestionService::changeStatus($question, $data['status'], (int) Auth::id(), $data['comment'] ?: null);
        } catch (WorkflowException) {
            return $this->done('admin.questions.show', ['id' => $id], __('admin.questions.flash.transition_denied'), 'error');
        }

        return $this->done('admin.questions.show', ['id' => $id], __('admin.questions.flash.status_changed'));
    }

    /** Validation ou refus d'une réponse en relecture (§11.4). */
    public function review(Request $request, string $id, string $messageId): Response
    {
        $this->authorize('questions.review');
        $question = $this->find($id);
        $data = $this->validate($request, ['decision' => 'required|in:approve,reject', 'reason' => 'max:1000']);
        $mid = $this->id($messageId);
        try {
            if ($data['decision'] === 'approve') {
                AnswerService::approve($question, $mid, (int) Auth::id());
                $message = __('admin.questions.flash.review_approved');
            } else {
                if (trim((string) ($data['reason'] ?? '')) === '') {
                    return $this->done('admin.questions.show', ['id' => $id], __('admin.questions.flash.reason_required'), 'error');
                }
                AnswerService::reject($question, $mid, (int) Auth::id(), (string) $data['reason']);
                $message = __('admin.questions.flash.review_rejected');
            }
        } catch (WorkflowException|\InvalidArgumentException) {
            return $this->done('admin.questions.show', ['id' => $id], __('admin.questions.flash.transition_denied'), 'error');
        }

        return $this->done('admin.questions.show', ['id' => $id], $message);
    }

    /** Création de la Q/R publique anonymisée (§4.8). */
    public function publish(Request $request, string $id): Response
    {
        $this->authorize('questions.publish');
        $question = $this->find($id);
        $data = $this->validate($request, ['title' => 'required|max:200', 'question_text' => 'required|max:20000', 'answer_html' => 'required', 'status' => 'in:draft,published']);
        try {
            $publicationId = PublicationService::publishFromQuestion($question, $data + ['include_audio' => $request->input('include_audio') === '1'], (int) Auth::id());
        } catch (WorkflowException|\InvalidArgumentException) {
            return $this->done('admin.questions.show', ['id' => $id], __('admin.questions.flash.not_publishable'), 'error');
        }
        Flash::success(__('admin.questions.flash.published'));

        return $this->redirectToRoute('admin.qa.edit', ['id' => $publicationId]);
    }

    /** Import d'un audio reçu sur WhatsApp. */
    public function importAudio(Request $request, string $id): Response
    {
        $this->authorize('questions.change_status');
        $question = $this->find($id);
        $file = $request->file('audio');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->done('admin.questions.show', ['id' => $id], __('admin.questions.flash.audio_missing'), 'error');
        }
        try {
            AdminQuestionService::importWhatsAppAudio($question, $file, (int) Auth::id(), $request->input('kind') === 'answer' ? 'answer' : 'question');
        } catch (AudioException $e) {
            return $this->done('admin.questions.show', ['id' => $id], $e->userMessage(), 'error');
        }

        return $this->done('admin.questions.show', ['id' => $id], __('admin.questions.flash.audio_imported'));
    }

    public function note(Request $request, string $id): Response
    {
        $question = $this->find($id);
        $data = $this->validate($request, ['body' => 'required|max:5000']);
        AnswerService::internalNote($question, (int) Auth::id(), (string) $data['body']);

        return $this->done('admin.questions.show', ['id' => $id], __('admin.questions.flash.note_added'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->authorize('questions.delete');
        AdminQuestionService::softDelete($this->find($id), (int) Auth::id());

        return $this->done('admin.questions', [], __('admin.questions.flash.deleted'));
    }

    /** Actions groupées (§11.4 : POST /admin/questions/bulk). */
    public function bulk(Request $request): Response
    {
        $data = $this->validate($request, ['action' => 'required|in:assign,status,priority,delete', 'ids' => 'required']);
        $permission = match ($data['action']) { 'assign' => 'questions.assign', 'delete' => 'questions.delete', default => 'questions.change_status' };
        $this->authorize($permission);
        $ids = array_map('intval', (array) $request->input('ids', []));
        $count = AdminQuestionService::bulk((string) $data['action'], $ids, [
            'scholar_id' => (int) $request->input('scholar_id', 0),
            'status'     => (string) $request->input('status', ''),
            'priority'   => (string) $request->input('priority', 'normal'),
            'comment'    => $request->input('comment') ?: null,
        ], (int) Auth::id());
        Flash::add($count > 0 ? 'success' : 'warning', __('admin.questions.flash.bulk_done', ['count' => $count]));
        $back = (string) $request->input('return', route('admin.questions'));

        return $this->redirect(str_starts_with($back, '/admin') ? $back : route('admin.questions'));
    }

    /** Formulaire : enregistrer une question reçue sur WhatsApp au nom d'un membre (source whatsapp). */
    public function create(Request $request): Response
    {
        $this->authorize('questions.change_status');

        return $this->view('admin/questions/create', ['rubriques' => Rubrique::allActive(), 'members' => $this->searchMembers((string) $request->query('member', ''))]);
    }

    public function store(Request $request): Response
    {
        $this->authorize('questions.change_status');
        $data = $this->validate($request, [
            'user_id' => 'required|integer', 'rubrique_id' => 'required|integer|exists:rubriques,id', 'title' => 'max:200', 'body' => 'max:20000',
            'spoken_language' => 'required|in:fr,ar,en,bm,other', 'answer_language' => 'required|in:fr,ar,en,bm', 'answer_channel' => 'required|in:platform,whatsapp,both',
            'whatsapp_number' => 'phone', 'visibility' => 'required|in:private,publishable',
        ]);
        $member = User::findWithRole((int) $data['user_id']);
        if ($member === null) {
            throw new HttpException(404);
        }
        $file = $request->file('audio');
        $mediaUuid = null;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $mediaUuid = (string) AudioService::store($file, (int) $member['id'], null, 'whatsapp')['uuid'];
            } catch (AudioException $e) {
                Flash::error($e->userMessage());

                return $this->back($request, route('admin.questions.create'));
            }
        }
        try {
            $question = QuestionService::create($member, $data + ['media_uuid' => $mediaUuid, 'source' => 'whatsapp', 'whatsapp_number' => $data['whatsapp_number'] ?: ($member['whatsapp'] ?? null)]);
        } catch (\InvalidArgumentException) {
            Flash::error(__('admin.questions.flash.empty_question'));

            return $this->back($request, route('admin.questions.create'));
        }
        QuestionService::notifyCreated($question);
        AssignmentService::autoAssign($question);
        \App\Services\ActivityLog::record('admin.question.created_from_whatsapp', 'question', (int) $question['id'], ['reference' => $question['reference']]);

        return $this->done('admin.questions.show', ['id' => $question['id']], __('admin.questions.flash.created'));
    }

    /** @return list<array<string, mixed>> */
    private function searchMembers(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        return Database::fetchAll('SELECT id, full_name, email, phone, whatsapp, country FROM users WHERE deleted_at IS NULL AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR whatsapp LIKE ?) ORDER BY full_name LIMIT 20', [$like, $like, $like, $like]);
    }

    /** @return array<string, mixed> */
    private function find(string $id): array
    {
        $question = Question::find($this->id($id));
        if ($question === null) {
            throw new HttpException(404);
        }

        return $question;
    }
}
