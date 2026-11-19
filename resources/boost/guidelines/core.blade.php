## Suspicious login

- `spatie/laravel-suspicious-login` records authentication attempts, scores successful logins with a preset, and runs that risk level's actions. Use the `Spatie\SuspiciousLogin` namespace and `Spatie\SuspiciousLogin\Facades\SuspiciousLogin` facade.
- Activate the `detecting-suspicious-logins` skill when installing or customizing this package, including presets, checks, actions, mailables, notifications, events, drivers, device fingerprints, and login history.
- Use the configured preset and replaceable classes rather than editing vendor files. The built-in `BlockLoginAction` supports session guards, not API token revocation.
- Use `SuspiciousLogin::determinePresetUsing()` to choose a preset per successful login. Return a preset class name, using `NonePreset` to skip assessment rather than returning null. All preset class names have a `Preset` suffix.
- Configure behavior in `config/suspicious-login.php`, not environment variables. Geolocation defaults to `ipinfo` (external lookups without a token); IP reputation defaults to `null`. Selecting a driver does not add its checks to a preset.
