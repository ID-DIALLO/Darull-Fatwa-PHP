<?php
/**
 * Formulaire d'article partagé (administration / professeur).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $article
 * @var list<array<string, mixed>> $rubriques
 * @var list<array<string, mixed>> $authors
 * @var string $action
 * @var string $cancel
 * @var bool $byScholar
 */
$t = $article['translations'] ?? [];
$tags = $article['tags'] ?? [];
$editing = $article !== null;
$locked = $byScholar && $editing && !in_array($article['status'], ['draft', 'submitted'], true);
?>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate <?= $locked ? 'inert' : '' ?>>
    <?= csrf_field() ?>
    <?php if ($locked): ?><div class="alert alert-info"><?= e(__('blog.manage.locked')) ?></div><?php endif ?>
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="df-card mb-3">
                <?= $this->partial('admin/partials/locale-tabs', ['id' => 'art', 'filled' => array_map(static fn (array $r): bool => !empty($r['title']), $t), 'render' => function (string $code) use ($t, $tags): string {
                    ob_start(); $row = $t[$code] ?? []; ?>
                    <div class="mb-2"><label class="form-label" for="title_<?= e($code) ?>"><?= e(__('blog.manage.field.title')) ?></label><input type="text" class="form-control" id="title_<?= e($code) ?>" name="title[<?= e($code) ?>]" maxlength="200" value="<?= e(old('title.' . $code, $row['title'] ?? '')) ?>"><?php if (!empty($row['slug'])): ?><div class="form-text">URL : <span class="df-code ltr">/<?= e($code) ?>/blog/<?= e($row['slug']) ?></span></div><?php endif ?></div>
                    <div class="mb-2"><label class="form-label" for="excerpt_<?= e($code) ?>"><?= e(__('blog.manage.field.excerpt')) ?></label><textarea class="form-control" id="excerpt_<?= e($code) ?>" name="excerpt[<?= e($code) ?>]" rows="2" maxlength="500"><?= e(old('excerpt.' . $code, $row['excerpt'] ?? '')) ?></textarea><div class="form-text"><?= e(__('blog.manage.field.excerpt_help')) ?></div></div>
                    <div class="mb-2"><label class="form-label" for="content_<?= e($code) ?>"><?= e(__('blog.manage.field.content')) ?></label><textarea class="form-control" id="content_<?= e($code) ?>" name="content[<?= e($code) ?>]" rows="14" data-rich-editor><?= e(old('content.' . $code, $row['content'] ?? '')) ?></textarea><div class="form-text"><?= e(__('blog.manage.field.content_help')) ?></div></div>
                    <div class="mb-2"><label class="form-label" for="tags_<?= e($code) ?>"><?= e(__('blog.manage.field.tags')) ?></label><input type="text" class="form-control" id="tags_<?= e($code) ?>" name="tags[<?= e($code) ?>]" value="<?= e(old('tags.' . $code, $tags[$code] ?? '')) ?>" placeholder="<?= e(__('blog.manage.field.tags_placeholder')) ?>"></div>
                    <div class="row g-2">
                        <div class="col-12 col-md-6"><label class="form-label" for="mt_<?= e($code) ?>"><?= e(__('admin.common.meta_title')) ?></label><input type="text" class="form-control" id="mt_<?= e($code) ?>" name="meta_title[<?= e($code) ?>]" maxlength="160" value="<?= e($row['meta_title'] ?? '') ?>"></div>
                        <div class="col-12 col-md-6"><label class="form-label" for="md_<?= e($code) ?>"><?= e(__('admin.common.meta_description')) ?></label><input type="text" class="form-control" id="md_<?= e($code) ?>" name="meta_description[<?= e($code) ?>]" maxlength="255" value="<?= e($row['meta_description'] ?? '') ?>"></div>
                    </div>
                    <?php return (string) ob_get_clean();
                }]) ?>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="df-card mb-3">
                <?php if (!$byScholar): ?>
                <div class="mb-3"><label class="form-label" for="status"><?= e(__('admin.qa.col.status')) ?></label><select class="form-select" id="status" name="status"><?php foreach (\App\Services\ArticleService::STATUSES as $s): ?><option value="<?= e($s) ?>" <?= old('status', $article['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= e(__('blog.status.' . $s)) ?></option><?php endforeach ?></select></div>
                <div class="mb-3"><label class="form-label" for="published_at"><?= e(__('blog.manage.field.published_at')) ?></label><input type="datetime-local" class="form-control" id="published_at" name="published_at" value="<?= e(old('published_at', \App\Services\ArticleService::localInput($article['published_at'] ?? null))) ?>"><div class="form-text"><?= e(__('blog.manage.field.published_at_help')) ?></div></div>
                <div class="mb-3"><label class="form-label" for="author_id"><?= e(__('admin.articles.col.author')) ?></label><select class="form-select" id="author_id" name="author_id"><?php foreach ($authors as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (string) old('author_id', $article['author_id'] ?? auth()['id']) === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?> (<?= e(__('account.roles.' . $u['role'])) ?>)</option><?php endforeach ?></select></div>
                <?php endif ?>
                <div class="mb-3"><label class="form-label" for="rubrique_id"><?= e(__('admin.questions.col.rubrique')) ?></label><select class="form-select" id="rubrique_id" name="rubrique_id"><option value="">—</option><?php foreach ($rubriques as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (string) old('rubrique_id', $article['rubrique_id'] ?? '') === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach ?></select></div>
                <?php if (!$byScholar): ?><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="is_featured" name="is_featured" value="1" <?= (int) old('is_featured', $article['is_featured'] ?? 0) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="is_featured"><?= e(__('blog.manage.field.featured')) ?></label></div><?php endif ?>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="allow_comments" name="allow_comments" value="1" <?= (int) old('allow_comments', $article['allow_comments'] ?? 1) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="allow_comments"><?= e(__('blog.manage.field.allow_comments')) ?></label></div>
            </div>
            <div class="df-card mb-3">
                <label class="form-label" for="cover"><?= e(__('blog.manage.field.cover')) ?></label>
                <img id="cover-preview" class="df-thumb w-100 mb-2" style="height:150px" src="<?= e($article['cover_path'] ?? '') ?>" alt="" <?= empty($article['cover_path']) ? 'hidden' : '' ?>>
                <input type="file" class="form-control<?= error_for('cover') ? ' is-invalid' : '' ?>" id="cover" name="cover" accept="image/jpeg,image/png,image/webp" data-preview="#cover-preview"><?= $this->partial('partials/field-error', ['name' => 'cover']) ?>
                <div class="form-text"><?= e(__('blog.manage.field.cover_help')) ?></div>
                <?php if (!empty($article['cover_path'])): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="remove_cover" name="remove_cover" value="1"><label class="form-check-label" for="remove_cover"><?= e(__('admin.common.remove_image')) ?></label></div><?php endif ?>
            </div>
            <div class="df-card">
                <p class="fw-semibold mb-2"><i class="bi bi-headphones" aria-hidden="true"></i> <?= e(__('blog.manage.field.audio')) ?></p>
                <?php if (!empty($article['audio'])): ?>
                <p class="small mb-2"><?= e(__('blog.manage.field.audio_current', ['name' => $article['audio']['original_name'] ?? $article['audio']['uuid']])) ?></p>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="remove_audio" name="remove_audio" value="1"><label class="form-check-label" for="remove_audio"><?= e(__('blog.manage.field.audio_remove')) ?></label></div>
                <?php endif ?>
                <?= $this->partial('partials/voice-recorder', ['name' => 'audio_uuid', 'maxSeconds' => 3600]) ?>
                <div class="form-text"><?= e(__('blog.manage.field.audio_help')) ?></div>
            </div>
        </div>
    </div>
    <?php if (!$locked): ?>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <?php if ($byScholar): ?>
        <button type="submit" class="btn btn-outline-primary" name="submit" value="draft"><i class="bi bi-save" aria-hidden="true"></i> <?= e(__('blog.manage.save_draft')) ?></button>
        <button type="submit" class="btn btn-primary" name="submit" value="submit"><i class="bi bi-send-check" aria-hidden="true"></i> <?= e(__('blog.manage.submit_review')) ?></button>
        <?php else: ?>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('common.save')) ?></button>
        <?php endif ?>
        <a class="btn btn-outline-secondary" href="<?= e($cancel) ?>"><?= e(__('common.cancel')) ?></a>
        <?php if ($editing && !$byScholar): ?><span class="ms-auto"><?= $this->partial('admin/partials/post-button', ['url' => route('admin.articles.destroy', ['id' => $article['id']]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-outline-danger', 'confirm' => __('admin.common.delete_confirm')]) ?></span><?php endif ?>
    </div>
    <?php endif ?>
</form>
