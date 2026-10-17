<?php

declare(strict_types=1);

namespace App\Controllers\Scholar;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\ScholarProfile;
use App\Services\ActivityLog;
use App\Services\ImageService;
use App\Services\ScholarService;

/**
 * Profil public du professeur (§4.5) : photo, titre et biographie par langue, langues parlées, disponibilité.
 */
final class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $id = (int) Auth::id();
        $translations = [];
        foreach (Database::fetchAll('SELECT locale, title, bio FROM scholar_translations WHERE user_id = ?', [$id]) as $row) {
            $translations[(string) $row['locale']] = $row;
        }

        return $this->view('scholar/profile', [
            'profile'      => ScholarService::profile($id),
            'translations' => $translations,
            'rubriques'    => Database::fetchAll(
                'SELECT COALESCE(t.name, d.name) AS name, r.icon FROM scholar_rubriques sr JOIN rubriques r ON r.id = sr.rubrique_id
                 LEFT JOIN rubrique_translations t ON t.rubrique_id = r.id AND t.locale = ? LEFT JOIN rubrique_translations d ON d.rubrique_id = r.id AND d.locale = ?
                 WHERE sr.user_id = ? ORDER BY r.sort_order',
                [Lang::getLocale(), Lang::getDefault(), $id],
            ),
            'scholar' => ScholarProfile::findPublic($id) ?? ['id' => $id, 'full_name' => Auth::user()['full_name']],
        ]);
    }

    public function update(Request $request): Response
    {
        $id = (int) Auth::id();
        $input = $request->all();
        $input['photo'] = $request->file('photo');
        $validator = Validator::make($input, [
            'title'        => 'nullable',
            'bio'          => 'nullable',
            'languages'    => 'nullable',
            'is_available' => 'nullable|boolean',
            'photo'        => 'nullable|file|mimes:jpg,jpeg,png,webp|max_size:5120',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }
        $data = [
            'title'        => is_array($input['title'] ?? null) ? $input['title'] : [],
            'bio'          => is_array($input['bio'] ?? null) ? $input['bio'] : [],
            'languages'    => is_array($input['languages'] ?? null) ? $input['languages'] : [],
            'is_available' => isset($input['is_available']),
        ];
        foreach ($data['title'] as $locale => $title) {
            if (mb_strlen((string) $title) > 80) {
                throw new ValidationException(['title' => [__('validation.max.string', ['attribute' => __('scholar.profile.title_field'), 'max' => 80])]]);
            }
        }
        $profile = ScholarService::profile($id);
        if (in_array($input['remove_photo'] ?? '0', ['1', 'on'], true) && !empty($profile['photo_path'])) {
            ImageService::delete((string) $profile['photo_path']);
            $data['photo_path'] = null;
        }
        if (is_array($input['photo']) && ($input['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $data['photo_path'] = ImageService::store($input['photo'], 'scholars', 400, 400, true);
            if (!empty($profile['photo_path'])) {
                ImageService::delete((string) $profile['photo_path']);
            }
        }
        ScholarService::updateProfile($id, $data);
        ActivityLog::record('scholar.profile_updated', 'user', $id);

        return $this->redirectToRoute('scholar.profile')->with('success', __('scholar.profile.saved'));
    }
}
