<?php
/**
 * Boîte à idées : liste triable (populaires, récentes, statut), filtres catégorie/statut, vote.
 *
 * @var \App\Core\View $this
 * @var array{sort: string, status: string, category: string} $filters
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 */
$this->extend('layouts/front');
$this->section('title', e(__('ideas.title')));
$this->section('description', e(__('ideas.meta')));
$active = array_filter($filters, static fn (string $v): bool => $v !== '' && $v !== 'popular');
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('ideas.title')]]]) ?>
<section class="df-section">
    <div class="container">
        <div class="df-page__header d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div><h1 class="df-title mb-1"><?= e(__('ideas.title')) ?></h1><p class="df-lead mb-0 df-measure"><?= e(__('ideas.intro')) ?></p></div>
            <a class="btn btn-gold" href="<?= e(auth() !== null ? route('ideas.create') : route('auth.login', ['redirect' => route('ideas.create')])) ?>"><i class="bi bi-lightbulb" aria-hidden="true"></i> <?= e(__('ideas.propose')) ?></a>
        </div>
        <form method="get" action="<?= e(route('ideas.index')) ?>" class="df-card df-filters mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3"><label class="form-label" for="tri"><?= e(__('ideas.sort')) ?></label><select class="form-select" id="tri" name="tri"><?php foreach (['popular', 'recent', 'status'] as $s): ?><option value="<?= e($s) ?>" <?= $filters['sort'] === $s ? 'selected' : '' ?>><?= e(__('ideas.sort_' . $s)) ?></option><?php endforeach ?></select></div>
                <div class="col-6 col-md-3"><label class="form-label" for="categorie"><?= e(__('ideas.form.category')) ?></label><select class="form-select" id="categorie" name="categorie"><option value=""><?= e(__('admin.common.all')) ?></option><?php foreach (\App\Services\IdeaService::CATEGORIES as $c): ?><option value="<?= e($c) ?>" <?= $filters['category'] === $c ? 'selected' : '' ?>><?= e(__('ideas.category.' . $c)) ?></option><?php endforeach ?></select></div>
                <div class="col-6 col-md-3"><label class="form-label" for="statut"><?= e(__('ideas.status_label')) ?></label><select class="form-select" id="statut" name="statut"><option value=""><?= e(__('admin.common.all')) ?></option><?php foreach (\App\Services\IdeaService::PUBLIC_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(__('ideas.status.' . $s)) ?></option><?php endforeach ?></select></div>
                <div class="col-6 col-md-3 d-grid"><button type="submit" class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> <?= e(__('admin.common.filter')) ?></button></div>
            </div>
        </form>
        <?php if ($result['data'] === []): ?>
        <div class="df-empty"><i class="bi bi-lightbulb" aria-hidden="true"></i><p class="mb-0"><?= e(__('ideas.empty')) ?></p></div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($result['data'] as $idea): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <article class="df-card df-idea h-100<?= $idea['status'] === 'pending' ? ' df-idea--pending' : '' ?>">
                    <div class="df-idea__head">
                        <span class="df-badge df-badge--<?= ['pending' => 'warning', 'new' => 'info', 'under_review' => 'info', 'accepted' => 'success', 'done' => 'success', 'declined' => 'secondary'][$idea['status']] ?>"><?= e(__('ideas.status.' . $idea['status'])) ?></span>
                        <span class="small text-secondary"><?= e(__('ideas.category.' . $idea['category'])) ?></span>
                    </div>
                    <h2 class="df-idea__title h5"><a href="<?= e(route('ideas.show', ['id' => $idea['id']])) ?>" dir="auto"><?= e($idea['title']) ?></a></h2>
                    <p class="df-idea__excerpt text-secondary" dir="auto"><?= e(excerpt((string) $idea['description'], 140)) ?></p>
                    <div class="df-idea__foot">
                        <?php if (auth() !== null && in_array($idea['status'], ['new', 'under_review', 'accepted'], true)): ?>
                        <form method="post" action="<?= e(route('ideas.vote', ['id' => $idea['id']])) ?>" data-vote-form><?= csrf_field() ?><button type="submit" class="btn btn-sm <?= !empty($idea['voted']) ? 'btn-primary' : 'btn-outline-primary' ?>" aria-pressed="<?= !empty($idea['voted']) ? 'true' : 'false' ?>"><i class="bi bi-hand-thumbs-up<?= !empty($idea['voted']) ? '-fill' : '' ?>" aria-hidden="true"></i> <span data-vote-count><?= (int) $idea['votes_count'] ?></span></button></form>
                        <?php else: ?><span class="small"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> <?= e(trans_choice('home.forum_ideas.votes', (int) $idea['votes_count'])) ?></span><?php endif ?>
                        <span class="small text-secondary"><i class="bi bi-chat-left" aria-hidden="true"></i> <?= (int) $idea['comments_count'] ?> · <?= e(time_ago((string) $idea['created_at'])) ?></span>
                    </div>
                </article>
            </div>
            <?php endforeach ?>
        </div>
        <div class="mt-4"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => ['tri' => $filters['sort'], 'statut' => $filters['status'], 'categorie' => $filters['category']]]) ?></div>
        <?php endif ?>
    </div>
</section>
<?php $this->endSection(); ?>
