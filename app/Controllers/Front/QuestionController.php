<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\MediaFile;
use App\Models\Question;
use App\Models\Rubrique;
use App\Services\AudioService;
use App\Services\PhoneNumber;
use App\Services\QuestionService;

/**
 * Poser une question (§4.2.1) : assistant en 3 étapes (thème, question vocale/écrite, réception),
 * limite quotidienne, page de confirmation avec référence et bouton WhatsApp.
 */
final class QuestionController extends Controller
{
    public const SPOKEN = ['fr', 'ar', 'en', 'bm', 'other'];
    public const ANSWER = ['fr', 'ar', 'en', 'bm'];

    public function create(Request $request): Response
    {
        $user = Auth::user();
        $preselected = null;
        if (is_string($request->query('rubrique'))) {
            $preselected = Rubrique::findByCode((string) $request->query('rubrique'))['id'] ?? null;
        }

        return $this->view('front/questions/create', [
            'rubriques'   => Rubrique::allActive(),
            'user'        => $user,
            'preselected' => $preselected !== null ? (int) $preselected : null,
            'mode'        => $request->query('mode') === 'text' ? 'text' : 'voice',
            'limit'       => QuestionService::dailyLimit(),
            'used'        => QuestionService::countLast24h((int) $user['id']),
            'maxSeconds'  => AudioService::maxSeconds(),
            'maxMb'       => (int) round(AudioService::maxBytes() / 1048576),
            'whatsapp'    => $user['whatsapp'] ?: $user['phone'],
            'defaultChannel' => $user['preferred_channel'] ?? 'platform',
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if (QuestionService::countLast24h((int) $user['id']) >= QuestionService::dailyLimit()) {
            throw new ValidationException(['body' => [__('questions.errors.daily_limit', ['count' => QuestionService::dailyLimit()])]]);
        }

        $input = $request->all();
        $input['whatsapp_number'] = PhoneNumber::normalize((string) ($input['whatsapp_number_e164'] ?? '') !== '' ? (string) $input['whatsapp_number_e164'] : (string) ($input['whatsapp_number'] ?? ''));
        $channel = (string) ($input['answer_channel'] ?? 'platform');

        $rules = [
            'rubrique_id'     => 'required|integer|exists:rubriques,id',
            'title'           => 'nullable|string|max:200',
            'body'            => 'nullable|string|max:10000',
            'media_uuid'      => 'nullable|string|max:36|regex:/^[0-9a-f-]{36}$/',
            'spoken_language' => 'required|in:' . implode(',', self::SPOKEN),
            'answer_language' => 'required|in:' . implode(',', self::ANSWER),
            'answer_channel'  => 'required|in:platform,whatsapp,both',
            'whatsapp_number' => ($channel !== 'platform' ? 'required|' : 'nullable|') . 'phone',
            'visibility'      => 'required|in:private,publishable',
        ];
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }
        $data = $validator->validated();

        // Fichier audio joint directement au formulaire (sans JavaScript ou note vocale existante)
        $attached = $request->file('audio_file');
        if (empty($data['media_uuid']) && $attached !== null && ($attached['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $media = AudioService::store($attached, (int) $user['id'], null, 'upload');
                $data['media_uuid'] = $media['uuid'];
            } catch (\App\Services\AudioException $e) {
                throw new ValidationException(['audio_file' => [$e->userMessage()]]);
            }
        }
        if (trim((string) ($data['body'] ?? '')) === '' && empty($data['media_uuid'])) {
            throw new ValidationException(['body' => [__('questions.errors.empty')]]);
        }
        if (!empty($data['media_uuid'])) {
            $media = MediaFile::findByUuid((string) $data['media_uuid']);
            if ($media === null || (int) $media['owner_id'] !== (int) $user['id'] || MediaFile::isAttached((int) $media['id'])) {
                throw new ValidationException(['body' => [__('questions.errors.audio_missing')]]);
            }
        }

        $question = QuestionService::create($user, [
            'rubrique_id'     => (int) $data['rubrique_id'],
            'title'           => $data['title'] ?? null,
            'body'            => $data['body'] ?? null,
            'media_uuid'      => $data['media_uuid'] ?? null,
            'spoken_language' => $data['spoken_language'],
            'answer_language' => $data['answer_language'],
            'answer_channel'  => $data['answer_channel'],
            'whatsapp_number' => $data['whatsapp_number'],
            'visibility'      => $data['visibility'],
            'source'          => 'web',
            'ip'              => $request->ip(),
            'user_agent'      => $request->userAgent(),
        ]);
        QuestionService::notifyCreated($question);

        return $this->redirectToRoute('questions.confirmation', ['reference' => $question['reference']]);
    }

    public function confirmation(Request $request, string $reference): Response
    {
        $question = Question::findByReference($reference);
        if ($question === null || (int) $question['user_id'] !== (int) Auth::id()) {
            throw new HttpException(404);
        }
        $message = __('questions.whatsapp.message', ['reference' => $question['reference'], 'rubrique' => $question['rubrique_name']]);

        return $this->view('front/questions/confirmation', [
            'question'     => $question,
            'lateHours'    => QuestionService::lateHours(),
            'whatsappLink' => whatsapp_link($message),
        ]);
    }
}
