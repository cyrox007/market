<!doctype html>
<html lang="ru"><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;line-height:1.5;color:#222">
{!! \App\Services\Mail\MailTemplateContent::html((string) ($customBody ?: ($fallbackBody ?? ''))) !!}
</body></html>
