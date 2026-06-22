<?php
/**
 * Email verification template.
 *
 * Variables:
 *   $name      - User's display name
 *   $verifyUrl - Full URL to verify (includes token)
 *   $appName   - Application name
 *   $subject   - Email subject (passed to layout)
 */

ob_start();
?>
<p>Hi <?= htmlspecialchars($name) ?>,</p>

<p>Thanks for signing up. Please verify your email address by clicking the button below.</p>

<p style="text-align: center; margin: 32px 0;">
    <a href="<?= htmlspecialchars($verifyUrl) ?>" class="btn">Verify Email</a>
</p>

<p>This link will expire in 24 hours. If you didn't create an account, you can safely ignore this email.</p>
<?php
$content = ob_get_clean();

include __DIR__ . '/layout.php';
