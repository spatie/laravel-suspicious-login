# Changelog

All notable changes to `laravel-suspicious-login` will be documented in this file.

## Unreleased

- Use `CookieFingerprinter` by default for encrypted, cookie-based browser recognition. Login routes need Laravel's web cookie middleware. Select `UserAgentFingerprinter` explicitly for stateless apps. Existing published configs keep their selected fingerprinter; switching drivers can flag known browsers as new devices on their next login.
- Replace the built-in notification with a queued `SuspiciousLoginMail`. Configure custom mailables with `mailable` instead of `notification`; use a custom action to keep using Laravel notifications. The user model no longer needs `Notifiable` for the default email action.
- Choose a preset per successful login with `SuspiciousLogin::determinePresetUsing()`.
- Add `NonePreset` to record logins without location lookups, scoring or actions.
- Keep three built-in presets: `DefaultPreset` (formerly `BalancedPreset`), `MonitorOnlyPreset` and `NonePreset`. Remove `LenientPreset` and `StrictPreset`; use a custom preset for stricter or more lenient behavior. Update published config files, imports and custom presets when upgrading.

## 0.0.1 - 2026-11-18

**Full Changelog**: https://github.com/spatie/laravel-suspicious-login/commits/0.0.1
