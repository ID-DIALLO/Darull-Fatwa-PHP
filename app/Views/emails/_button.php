<?php
/**
 * Bouton d'action d'un e-mail (or sur vert nuit : contraste AA) + lien de repli en clair.
 *
 * @var string $url
 * @var string $label
 * @var string $mailLocale
 */
?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto;">
    <tr>
        <td align="center" style="background:#C9A24B;border-radius:10px;">
            <a href="<?= e($url) ?>" style="display:inline-block;padding:14px 28px;font-weight:700;color:#073D2E;text-decoration:none;font-size:16px;"><?= e($label) ?></a>
        </td>
    </tr>
</table>
<p style="margin:0 0 8px;font-size:13px;color:#4A5A54;"><?= e(__('emails.common.button_fallback', [], $mailLocale)) ?></p>
<p style="margin:0 0 16px;font-size:13px;word-break:break-all;" dir="ltr"><a href="<?= e($url) ?>" style="color:#0B5D45;"><?= e($url) ?></a></p>
