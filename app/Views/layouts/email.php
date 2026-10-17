<?php
/**
 * Gabarit des e-mails HTML (tableaux + styles en ligne pour les clients de messagerie), couleurs de la charte,
 * direction RTL pour l'arabe. Sections attendues : content ; facultative : title.
 *
 * @var \App\Core\View $this
 * @var string $mailLocale
 * @var string $subject
 */
$dir = text_dir($mailLocale);
$font = $dir === 'rtl' ? "Tajawal, 'Segoe UI', Tahoma, Arial, sans-serif" : "Inter, 'Segoe UI', Roboto, Arial, sans-serif";
$align = $dir === 'rtl' ? 'right' : 'left';
?>
<!doctype html>
<html lang="<?= e($mailLocale) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#F7F1E1;font-family:<?= $font ?>;color:#1F2A26;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F1E1;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(7,61,46,.08);">
                <tr>
                    <td align="center" style="background:#073D2E;padding:24px;">
                        <img src="<?= e(url('/assets/img/logo/darul-fatwa-logo-blanc.png')) ?>" alt="Darul Fatwa – دار الفتوى" width="200" style="display:block;width:200px;max-width:80%;height:auto;">
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px 28px;text-align:<?= $align ?>;font-size:16px;line-height:1.6;">
                        <?php if ($this->hasSection('title')): ?>
                        <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#0B5D45;font-weight:700;"><?= $this->yield('title') ?></h1>
                        <?php endif ?>
                        <?= $this->yield('content') ?>
                        <p style="margin:28px 0 0;color:#4A5A54;"><?= e(__('emails.common.signature', [], $mailLocale)) ?></p>
                    </td>
                </tr>
                <tr>
                    <td style="background:#F7F1E1;padding:16px 28px;font-size:12px;line-height:1.5;color:#4A5A54;text-align:center;">
                        <?= e(__('emails.common.footer', [], $mailLocale)) ?><br>
                        <a href="<?= e(url('/' . $mailLocale)) ?>" style="color:#0B5D45;"><?= e(__('emails.common.visit', [], $mailLocale)) ?></a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
