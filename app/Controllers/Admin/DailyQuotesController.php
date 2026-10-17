<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Cache;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Models\DailyQuote;
use App\Services\ActivityLog;
use App\Services\Translations;

/** Versets et hadiths du jour (§6.6) : CRUD, traductions, date de diffusion. */
final class DailyQuotesController extends AdminController
{
    protected ?string $permission = 'home.manage';

    public function index(Request $request): Response
    {
        $rows = Database::fetchAll(
            'SELECT q.*, COALESCE(t.translation, d.translation) AS translation, (SELECT GROUP_CONCAT(l.locale ORDER BY l.locale) FROM daily_quote_translations l WHERE l.quote_id = q.id) AS locales
             FROM daily_quotes q LEFT JOIN daily_quote_translations t ON t.quote_id = q.id AND t.locale = ? LEFT JOIN daily_quote_translations d ON d.quote_id = q.id AND d.locale = ?
             ORDER BY q.display_date IS NULL, q.display_date DESC, q.id DESC',
            [Lang::getLocale(), Lang::getDefault()],
        );

        return $this->view('admin/daily_quotes/index', ['quotes' => $rows, 'today' => DailyQuote::today()]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/daily_quotes/form', ['quote' => null]);
    }

    public function edit(Request $request, string $id): Response
    {
        $quote = DailyQuote::find($this->id($id));
        if ($quote === null) {
            throw new HttpException(404);
        }
        $quote['translations'] = Translations::all('daily_quote_translations', 'quote_id', (int) $quote['id']);

        return $this->view('admin/daily_quotes/form', ['quote' => $quote]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request);
        $id = DailyQuote::create($data['attributes']);
        Translations::save('daily_quote_translations', 'quote_id', $id, $data['translations'], ['translation']);
        ActivityLog::record('admin.quote.created', 'daily_quote', $id);
        Cache::flush('daily_quote.');

        return $this->done('admin.daily_quotes');
    }

    public function update(Request $request, string $id): Response
    {
        $quoteId = $this->id($id);
        if (DailyQuote::find($quoteId) === null) {
            throw new HttpException(404);
        }
        $data = $this->payload($request);
        DailyQuote::update($quoteId, $data['attributes']);
        Translations::save('daily_quote_translations', 'quote_id', $quoteId, $data['translations'], ['translation']);
        ActivityLog::record('admin.quote.updated', 'daily_quote', $quoteId);
        Cache::flush('daily_quote.');

        return $this->done('admin.daily_quotes');
    }

    public function destroy(Request $request, string $id): Response
    {
        $quoteId = $this->id($id);
        DailyQuote::destroy($quoteId);
        ActivityLog::record('admin.quote.deleted', 'daily_quote', $quoteId);
        Cache::flush('daily_quote.');

        return $this->done('admin.daily_quotes', [], __('admin.common.deleted'));
    }

    /** @return array{attributes: array<string, mixed>, translations: array<string, array<string, mixed>>} */
    private function payload(Request $request): array
    {
        $this->validate($request, ['type' => 'required|in:ayah,hadith', 'arabic_text' => 'required|max:5000', 'reference' => 'required|max:160', 'display_date' => 'date']);
        $translations = [];
        foreach (Lang::locales() as $locale) {
            $translations[$locale] = ['translation' => Translations::text(((array) $request->input('translation', []))[$locale] ?? null, 5000)];
        }
        $date = trim((string) $request->input('display_date', ''));

        return [
            'attributes' => [
                'type'         => (string) $request->input('type'),
                'arabic_text'  => trim((string) $request->input('arabic_text')),
                'reference'    => trim((string) $request->input('reference')),
                'display_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
                'is_active'    => $request->input('is_active') === '1' ? 1 : 0,
            ],
            'translations' => $translations,
        ];
    }
}
