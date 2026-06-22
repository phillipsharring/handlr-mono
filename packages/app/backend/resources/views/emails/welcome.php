<?php
/**
 * Welcome email template.
 *
 * Variables:
 *   $name    - User's display name
 *   $appName - Application name (e.g., 'Reuse Lists')
 *   $appUrl  - Application URL (e.g., 'https://reuselists.com')
 */

ob_start();
?>
<p>Hi <?= htmlspecialchars($name) ?>,</p>

<p>Welcome to <?= htmlspecialchars($appName) ?>. Your account is ready.</p>

<p style="text-align: center; margin: 32px 0;">
    <a href="<?= htmlspecialchars($appUrl) ?>/checklists/" class="btn">Get Started</a>
</p>

<p>If you have any questions, just reply to this email.</p>
<?php
$content = ob_get_clean();

include __DIR__ . '/layout.php';
