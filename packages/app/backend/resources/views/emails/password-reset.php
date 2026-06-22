<?php
/**
 * Password reset email template.
 *
 * Variables:
 *   $name     - User's display name
 *   $resetUrl - Full URL to the reset password page (includes token)
 *   $appName  - Application name
 *   $subject  - Email subject (passed to layout)
 */

ob_start();
?>
<p>Hi <?= htmlspecialchars($name) ?>,</p>

<p>We received a request to reset your password. Click the button below to choose a new one.</p>

<p style="text-align: center; margin: 32px 0;">
    <a href="<?= htmlspecialchars($resetUrl) ?>" class="btn">Reset Password</a>
</p>

<p>This link will expire in 1 hour. If you didn't request a password reset, you can safely ignore this email.</p>
<?php
$content = ob_get_clean();

include __DIR__ . '/layout.php';
