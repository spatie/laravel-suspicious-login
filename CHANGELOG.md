# Changelog

All notable changes to `laravel-suspicious-login` will be documented in this file.

## Unreleased

- Choose a preset per successful login with `SuspiciousLogin::determinePresetUsing()`.
- Add `NonePreset` to record logins without location lookups, scoring or actions.
- Keep three built-in presets: `DefaultPreset` (formerly `BalancedPreset`), `MonitorOnlyPreset` and `NonePreset`. Remove `LenientPreset` and `StrictPreset`; use a custom preset for stricter or more lenient behavior. Update published config files, imports and custom presets when upgrading.

## 0.0.1 - 2026-11-18

**Full Changelog**: https://github.com/spatie/laravel-suspicious-login/commits/0.0.1
