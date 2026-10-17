<?php
/**
 * Fiche membre (§4.6.2) : profil, statistiques, toutes ses questions, journal, actions.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 * @var list<string> $roles
 */
$this->extend('layouts/admin');
$this->section('title', e($user['full_name']));
$this->section('subtitle', e(__('account.roles.' . $user['role'])) . ' · ' . e(__('admin.users.status.' . $user['status'])) . ($user['deleted_at'] ? ' · ' . e(__('admin.users.deleted')) : ''));
$id = (int) $user['id'];
$self = (int) \App\Core\Auth::id() === $id;
$this->section('breadcrumbs');
?>
<ol><li><a href="<?= e(route('admin.dashboard')) ?>"><?= e(__('admin.nav.dashboard')) ?></a></li><li><a href="<?= e(route('admin.users')) ?>"><?= e(__('admin.nav.users')) ?></a></li><li aria-current="page"><?= e($user['full_name']) ?></li></ol>
<?php $this->endSection(); ?>
<?php $this->section('actions'); ?>
<?php if (!$user['deleted_at']): ?><a class="btn btn-outline-primary" href="<?= e(route('admin.users.edit', ['id' => $id])) ?>"><i class="bi bi-pencil" aria-hidden="true"></i> <?= e(__('admin.common.edit')) ?></a><?php endif ?>
<?php if ($user['role'] === 'scholar' && can('scholars.manage')): ?><a class="btn btn-outline-primary" href="<?= e(route('admin.scholars.edit', ['id' => $id])) ?>"><i class="bi bi-mortarboard" aria-hidden="true"></i> <?= e(__('admin.users.scholar_sheet')) ?></a><?php endif ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php if (!empty($invitation)): $waText = __('admin.users.invite.whatsapp_text', ['name' => $user['full_name'], 'url' => $invitation['url'], 'code' => $invitation['code']]); ?>
<div class="alert alert-success df-invitation" role="status">
    <h2 class="h6 mb-2"><i class="bi bi-envelope-paper-heart" aria-hidden="true"></i> <?= e(__('admin.users.invite.title')) ?></h2>
    <p class="mb-2"><?= e(__($invitation['mailed'] ? 'admin.users.invite.mailed' : 'admin.users.invite.not_mailed')) ?> <?= e(__('admin.users.invite.once')) ?></p>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-8"><label class="form-label small mb-1" for="invite-url"><?= e(__('admin.users.invite.link')) ?></label><div class="input-group"><input type="text" class="form-control ltr" id="invite-url" value="<?= e($invitation['url']) ?>" readonly><button type="button" class="btn btn-outline-primary" data-copy-link="<?= e($invitation['url']) ?>" data-copied-label="<?= e(__('common.share.copied')) ?>"><i class="bi bi-clipboard" aria-hidden="true"></i> <?= e(__('admin.users.invite.copy')) ?></button><span class="df-share__status visually-hidden" aria-live="polite"></span></div></div>
        <div class="col-12 col-lg-4"><label class="form-label small mb-1" for="invite-code"><?= e(__('admin.users.invite.code')) ?></label><div class="input-group"><input type="text" class="form-control ltr fw-bold fs-4 text-center" id="invite-code" value="<?= e(substr($invitation['code'], 0, 3) . ' ' . substr($invitation['code'], 3)) ?>" readonly><button type="button" class="btn btn-outline-primary" data-copy-link="<?= e($invitation['code']) ?>" data-copied-label="<?= e(__('common.share.copied')) ?>"><i class="bi bi-clipboard" aria-hidden="true"></i></button><span class="df-share__status visually-hidden" aria-live="polite"></span></div></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <?php if ($user['whatsapp'] || $user['phone']): ?><a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="<?= e(whatsapp_link($waText, (string) ($user['whatsapp'] ?: $user['phone']))) ?>"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('admin.users.invite.send_whatsapp')) ?></a><?php endif ?>
        <button type="button" class="btn btn-sm btn-outline-primary" data-copy-link="<?= e($waText) ?>" data-copied-label="<?= e(__('common.share.copied')) ?>"><i class="bi bi-chat-left-text" aria-hidden="true"></i> <?= e(__('admin.users.invite.copy_message')) ?></button><span class="df-share__status small align-self-center" aria-live="polite"></span>
    </div>
    <p class="small mb-0 mt-2 text-secondary"><?= e(__('admin.users.invite.expires', ['date' => format_date($invitation['expires_at'], true)])) ?></p>
</div>
<?php elseif ($user['status'] === 'pending' && !$user['deleted_at']): ?>
<div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
    <span><i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= e(!empty($pendingInvitation) ? __('admin.users.invite.pending', ['date' => format_date($pendingInvitation['expires_at'], true)]) : __('admin.users.invite.expired')) ?></span>
    <?= $this->partial('admin/partials/post-button', ['url' => route('admin.users.invite', ['id' => $id]), 'label' => __('admin.users.invite.renew'), 'icon' => 'arrow-repeat', 'class' => 'btn btn-sm btn-primary']) ?>
</div>
<?php endif ?>
<div class="row g-3 mb-3">
    <?php foreach (['questions' => 'chat-left-dots', 'answered' => 'check2-circle', 'topics' => 'chat-square-text', 'posts' => 'chat-dots', 'ideas' => 'lightbulb', 'comments' => 'chat-left-quote'] as $key => $icon): ?>
    <div class="col-6 col-md-4 col-xl-3 col-xxl-2"><div class="df-kpi"><span class="df-kpi__icon"><i class="bi bi-<?= e($icon) ?>" aria-hidden="true"></i></span><span><span class="df-kpi__value d-block"><?= (int) $user['stats'][$key] ?></span><span class="df-kpi__label"><?= e(__('admin.users.stats.' . $key)) ?></span></span></div></div>
    <?php endforeach ?>
</div>
<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="df-card mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-person-vcard" aria-hidden="true"></i> <?= e(__('admin.users.profile')) ?></h2>
            <dl class="df-dl df-dl--compact mb-0">
                <?php if ($user['display_name']): ?><dt><?= e(__('validation.attributes.display_name')) ?></dt><dd dir="auto"><?= e($user['display_name']) ?></dd><?php endif ?>
                <dt><?= e(__('admin.users.col.email')) ?></dt><dd class="ltr"><?= e($user['email'] ?? '—') ?><?php if ($user['email']): ?> <?= $user['email_verified_at'] ? '<i class="bi bi-patch-check-fill text-success" title="' . e(__('admin.users.verified')) . '"></i>' : '<i class="bi bi-envelope-exclamation text-warning" title="' . e(__('admin.users.unverified')) . '"></i>' ?><?php endif ?></dd>
                <dt><?= e(__('admin.users.col.phone')) ?></dt><dd class="ltr"><?= e($user['phone'] ?? '—') ?></dd>
                <dt>WhatsApp</dt><dd class="ltr"><?= e($user['whatsapp'] ?? '—') ?></dd>
                <dt><?= e(__('admin.users.col.country')) ?></dt><dd><?= e($user['country'] ? (\Locale::getDisplayRegion('-' . $user['country'], locale()) ?: $user['country']) : '—') ?><?= $user['city'] ? ' · ' . e($user['city']) : '' ?></dd>
                <dt><?= e(__('validation.attributes.locale')) ?></dt><dd><?= e(__('common.lang.' . $user['locale'])) ?></dd>
                <dt><?= e(__('validation.attributes.preferred_channel')) ?></dt><dd><?= e(__('questions.channels.' . ($user['preferred_channel'] ?? 'platform'))) ?></dd>
                <dt><?= e(__('admin.users.col.registered')) ?></dt><dd><?= e(format_date((string) $user['created_at'], true)) ?></dd>
                <dt><?= e(__('admin.users.col.last_login')) ?></dt><dd><?= $user['last_login_at'] ? e(format_date((string) $user['last_login_at'], true)) . ' <small class="text-secondary ltr">' . e($user['last_login_ip'] ?? '') . '</small>' : '—' ?></dd>
            </dl>
        </div>
        <?php if (!$user['deleted_at']): ?>
        <div class="df-card mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-shield-lock" aria-hidden="true"></i> <?= e(__('admin.users.actions')) ?></h2>
            <form method="post" action="<?= e(route('admin.users.role', ['id' => $id])) ?>" class="mb-3">
                <?= csrf_field() ?>
                <label class="form-label" for="role"><?= e(__('admin.users.col.role')) ?></label>
                <div class="input-group">
                    <select class="form-select" id="role" name="role" <?= $self ? 'disabled' : '' ?>><?php foreach ($roles as $r): ?><option value="<?= e($r) ?>" <?= $user['role'] === $r ? 'selected' : '' ?>><?= e(__('account.roles.' . $r)) ?></option><?php endforeach ?><?php if (!in_array($user['role'], $roles, true)): ?><option value="<?= e($user['role']) ?>" selected><?= e(__('account.roles.' . $user['role'])) ?></option><?php endif ?></select>
                    <button type="submit" class="btn btn-outline-primary" <?= $self ? 'disabled' : '' ?>><?= e(__('admin.common.change')) ?></button>
                </div>
            </form>
            <?php if (!$self): ?>
            <form method="post" action="<?= e(route('admin.users.status', ['id' => $id])) ?>" class="mb-3" data-confirm="<?= e(__('admin.users.status_confirm')) ?>">
                <?= csrf_field() ?>
                <label class="form-label" for="status"><?= e(__('admin.users.col.status')) ?></label>
                <div class="input-group mb-1">
                    <select class="form-select" id="status" name="status"><?php foreach (['active', 'suspended', 'banned'] as $s): ?><option value="<?= e($s) ?>" <?= $user['status'] === $s ? 'selected' : '' ?>><?= e(__('admin.users.status.' . $s)) ?></option><?php endforeach ?></select>
                    <button type="submit" class="btn btn-outline-primary"><?= e(__('admin.common.change')) ?></button>
                </div>
                <input type="text" class="form-control form-control-sm" name="reason" maxlength="255" placeholder="<?= e(__('admin.users.status_reason')) ?>" aria-label="<?= e(__('admin.users.status_reason')) ?>">
            </form>
            <?php endif ?>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($user['status'] !== 'pending'): ?><?= $this->partial('admin/partials/post-button', ['url' => route('admin.users.invite', ['id' => $id]), 'label' => __('admin.users.invite.renew'), 'icon' => 'envelope-paper', 'class' => 'btn btn-sm btn-outline-primary', 'confirm' => __('admin.users.invite.renew_confirm')]) ?><?php endif ?>
                <?php if ($user['email']): ?><?= $this->partial('admin/partials/post-button', ['url' => route('admin.users.reset_link', ['id' => $id]), 'label' => __('admin.users.send_reset'), 'icon' => 'key', 'class' => 'btn btn-sm btn-outline-secondary']) ?><?php endif ?>
                <?php if (!$self): ?><?= $this->partial('admin/partials/post-button', ['url' => route('admin.users.destroy', ['id' => $id]), 'label' => __('admin.users.delete'), 'icon' => 'trash', 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('admin.users.delete_confirm')]) ?><?php endif ?>
            </div>
        </div>
        <?php endif ?>
    </div>
    <div class="col-12 col-xl-8">
        <div class="df-card mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-chat-left-dots" aria-hidden="true"></i> <?= e(__('admin.users.all_questions')) ?></h2>
            <?php if ($user['questions'] === []): ?><p class="text-secondary mb-0"><?= e(__('admin.common.empty')) ?></p><?php else: ?>
            <div class="table-responsive"><table class="table table-sm df-table">
                <thead><tr><th><?= e(__('admin.questions.col.reference')) ?></th><th><?= e(__('admin.questions.col.date')) ?></th><th><?= e(__('questions.create.title_label')) ?></th><th><?= e(__('admin.questions.col.status')) ?></th><th><?= e(__('admin.questions.col.scholar')) ?></th><th><?= e(__('admin.questions.col.delay')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($user['questions'] as $q): ?>
                <tr>
                    <td class="ref"><a href="<?= e(route('admin.questions.show', ['id' => $q['id']])) ?>"><?= e($q['reference']) ?></a></td>
                    <td><?= e(format_date((string) $q['created_at'], true)) ?></td>
                    <td dir="auto"><?= e($q['title'] ?: __('questions.list.untitled')) ?> <small class="text-secondary">(<?= e(__('questions.types.' . $q['input_type'])) ?>)</small></td>
                    <td><?= $this->partial('partials/status-badge', ['status' => $q['status']]) ?></td>
                    <td dir="auto"><?= e($q['scholar_name'] ?? '—') ?></td>
                    <td><?= $q['first_response_minutes'] !== null ? e(__('admin.questions.answered_in', ['hours' => round(((int) $q['first_response_minutes']) / 60, 1)])) : '—' ?></td>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table></div>
            <?php endif ?>
        </div>
        <div class="df-card">
            <h2 class="df-card__title h6"><i class="bi bi-clock-history" aria-hidden="true"></i> <?= e(__('admin.users.activity')) ?></h2>
            <?php if ($user['activity'] === []): ?><p class="text-secondary mb-0"><?= e(__('admin.common.empty')) ?></p><?php else: ?>
            <ul class="df-list small mb-0">
                <?php foreach ($user['activity'] as $a): ?>
                <li><span><span class="df-code"><?= e($a['action']) ?></span><?php if ($a['subject_type']): ?> · <?= e($a['subject_type']) ?> #<?= (int) $a['subject_id'] ?><?php endif ?></span><small><?= e(format_date((string) $a['created_at'], true)) ?> · <span class="ltr"><?= e($a['ip_address'] ?? '') ?></span></small></li>
                <?php endforeach ?>
            </ul>
            <?php endif ?>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
