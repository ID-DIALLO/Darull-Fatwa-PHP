<?php
/**
 * Tableau de bord du membre (§4.4) : compteurs, dernières réponses, raccourci, notifications récentes.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 * @var array{asked: int, answered: int, pending: int} $counts
 * @var list<array<string, mixed>> $latestAnswers
 * @var list<array<string, mixed>> $notifications
 */
$this->extend('layouts/account');
$this->section('title', __('account.dashboard.welcome', ['name' => \App\Models\User::publicName($user)]));
$this->section('subtitle', __(can_ask() ? 'account.dashboard.intro' : 'account.dashboard.staff_intro'));
?>
<?php $this->section('content'); ?>
<div class="row g-3 mb-4">
    <?php foreach (['asked' => ['chat-left-dots', 'all'], 'answered' => ['check2-circle', 'answered'], 'pending' => ['hourglass-split', 'pending']] as $key => [$icon, $tab]): ?>
    <div class="col-12 col-sm-4">
        <a class="df-card df-stat d-block text-decoration-none" href="<?= e(route('account.questions', ['statut' => $tab])) ?>">
            <i class="bi bi-<?= e($icon) ?> df-stat__icon" aria-hidden="true"></i>
            <p class="df-stat__value mb-0"><?= (int) $counts[$key] ?></p>
            <p class="df-stat__label mb-0"><?= e(__('account.dashboard.stats.' . $key)) ?></p>
        </a>
    </div>
    <?php endforeach ?>
</div>

<?php if (can_ask()): ?>
<div class="df-card mb-4 df-dashboard-cta">
    <div>
        <h2 class="h5 df-title mb-1"><?= e(__('home.voice.title')) ?></h2>
        <p class="text-secondary mb-0"><?= e(__('home.voice.subtitle')) ?></p>
    </div>
    <a class="btn btn-gold btn-lg" href="<?= e(route('questions.create')) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('account.dashboard.ask')) ?></a>
</div>
<?php else: ?>
<div class="df-card mb-4 df-dashboard-cta">
    <div>
        <h2 class="h5 df-title mb-1"><?= e(__('account.dashboard.staff_title')) ?></h2>
        <p class="text-secondary mb-0"><?= e(__('account.dashboard.staff_text')) ?></p>
    </div>
    <a class="btn btn-gold btn-lg" href="<?= e(space_url()) ?>"><i class="bi bi-mortarboard" aria-hidden="true"></i> <?= e(__(\App\Core\Auth::hasRole('scholar') ? 'nav.scholar_space' : 'nav.admin')) ?></a>
</div>
<?php endif ?>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="df-card h-100">
            <div class="df-section__head df-section__head--row mb-2">
                <h2 class="df-card__title mb-0"><i class="bi bi-envelope-open-heart" aria-hidden="true"></i> <?= e(__('questions.dashboard.latest_answers')) ?></h2>
                <a class="small" href="<?= e(route('account.questions')) ?>"><?= e(__('questions.dashboard.all_questions')) ?></a>
            </div>
            <?php if ($latestAnswers === []): ?>
            <p class="text-secondary mb-0"><?= e(__('questions.dashboard.no_answer_yet')) ?></p>
            <?php else: ?>
            <ul class="df-list">
                <?php foreach ($latestAnswers as $q): ?>
                <li><a href="<?= e(route('account.questions.show', ['reference' => $q['reference']])) ?>" dir="auto"><span class="ltr"><?= e($q['reference']) ?></span> · <?= e($q['title'] ?: $q['rubrique_name']) ?></a><small><?= e(format_date((string) $q['answered_at'])) ?></small></li>
                <?php endforeach ?>
            </ul>
            <?php endif ?>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="df-card h-100">
            <div class="df-section__head df-section__head--row mb-2">
                <h2 class="df-card__title mb-0"><i class="bi bi-bell" aria-hidden="true"></i> <?= e(__('questions.dashboard.recent_notifications')) ?></h2>
                <a class="small" href="<?= e(route('account.notifications')) ?>"><?= e(__('front.see_all')) ?></a>
            </div>
            <?php if ($notifications === []): ?>
            <p class="text-secondary mb-0"><?= e(__('questions.notifications.empty')) ?></p>
            <?php else: ?>
            <ul class="df-list">
                <?php foreach ($notifications as $n): ?>
                <li><a href="<?= e(route('account.notifications.open', ['id' => $n['id']])) ?>" dir="auto"><?= $n['read_at'] === null ? '<i class="bi bi-circle-fill text-warning small" aria-hidden="true"></i> ' : '' ?><?= e($n['rendered']['title']) ?></a><small><?= e(time_ago((string) $n['created_at'])) ?></small></li>
                <?php endforeach ?>
            </ul>
            <?php endif ?>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
