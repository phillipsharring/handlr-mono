<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $subject ?? '' ?></title>
    <style>
        body { margin: 0; padding: 0; background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .wrapper { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 32px; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0; }
        .body { font-size: 15px; line-height: 1.6; color: #334155; }
        .body p { margin: 0 0 16px; }
        .body a { color: #2563eb; }
        .btn { display: inline-block; padding: 12px 24px; background: #1e3a5f; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; }
        .btn:hover { background: #1e40af; }
        .footer { text-align: center; margin-top: 32px; font-size: 13px; color: #94a3b8; }
        .footer a { color: #94a3b8; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="header">
            <h1><?= $appName ?? 'Handlr App' ?></h1>
        </div>
        <div class="body">
            <?= $content ?? '' ?>
        </div>
    </div>
    <div class="footer">
        <p><?= $appName ?? 'Handlr App' ?></p>
    </div>
</div>
</body>
</html>
