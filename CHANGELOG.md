# Changelog

All notable changes to `laravel-suspicious-login` will be documented in this file.

## Unreleased

- Choose a preset per successful login with `SuspiciousLogin::determinePresetUsing()`.
- Add `NonePreset` to record logins without location lookups, scoring or actions.
- Rename the built-in presets to `BalancedPreset`, `LenientPreset`, `StrictPreset` and `MonitorOnlyPreset`. Update references in published config files, imports and custom presets when upgrading.

## 0.0.1 - 2026-11-18

**Full Changelog**: https://github.com/spatie/laravel-suspicious-login/commits/0.0.1
