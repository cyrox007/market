@auth('web')
<script>
window.adminWorkspaceConfig = @js([
    'userId' => (string) auth('web')->id(),
    'statusUrl' => route('admin.session-status'),
    'loginUrl' => route('filament.admin_sv.auth.login'),
    'interval' => max(15, min(60, (int) config('session.lifetime') * 20)) * 1000,
    'diagnosticsUrl' => config('operator_diagnostics.enabled') && strtotime((string) config('operator_diagnostics.until')) > time()
        ? route('admin.operator-diagnostics') : null,
    // Dedicated per-user encryption key. APP_KEY itself never leaves the server.
    'draftKey' => base64_encode(hash_hmac('sha256', 'admin-workspace-draft:v1:'.auth('web')->id(), (string) config('app.key'), true)),
]);
</script>
<script src="{{ asset('js/admin-workspace.js') }}?v={{ filemtime(public_path('js/admin-workspace.js')) }}" defer></script>
@endauth
