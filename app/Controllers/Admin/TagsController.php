<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLog;
use App\Services\SlugService;

/** Tags du blog (par langue) : renommage et suppression ; la création se fait depuis l'article. */
final class TagsController extends AdminController
{
    protected ?string $permission = 'articles.publish';

    public function index(Request $request): Response
    {
        return $this->view('admin/tags', ['tags' => Database::fetchAll('SELECT t.*, (SELECT COUNT(*) FROM article_tag at WHERE at.tag_id = t.id) AS articles_count FROM tags t ORDER BY t.locale, t.name')]);
    }

    public function update(Request $request, string $id): Response
    {
        $tagId = $this->id($id);
        $data = $this->validate($request, ['name' => 'required|max:60']);
        $tag = Database::fetch('SELECT * FROM tags WHERE id = ?', [$tagId]);
        if ($tag !== null) {
            Database::execute('UPDATE tags SET name = ?, slug = ? WHERE id = ?', [trim((string) $data['name']), SlugService::unique(trim((string) $data['name']), 'tags', (string) $tag['locale'], ['id' => $tagId], 80), $tagId]);
            ActivityLog::record('admin.tag.updated', 'tag', $tagId);
            \App\Core\Cache::flush('articles.');
        }

        return $this->done('admin.tags');
    }

    public function destroy(Request $request, string $id): Response
    {
        $tagId = $this->id($id);
        Database::execute('DELETE FROM tags WHERE id = ?', [$tagId]);
        ActivityLog::record('admin.tag.deleted', 'tag', $tagId);
        \App\Core\Cache::flush('articles.');

        return $this->done('admin.tags', [], __('admin.common.deleted'));
    }
}
