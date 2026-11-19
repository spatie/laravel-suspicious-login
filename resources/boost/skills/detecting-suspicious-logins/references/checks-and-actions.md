# Checks and actions

## Built-in checks

Every check has `new()` and `score(int $score)`. A check's default score is separate from the weight a built-in preset assigns to it. Read the selected preset before predicting the final score.

- `NewDeviceCheck`: default 25. Flags a fingerprint absent from earlier successful, unblocked logins. Passes without trusted history.
- `NewIpCheck`: default 10. Flags an IP absent from trusted history. Passes without trusted history.
- `NewCountryCheck`: default 30. Flags a country absent from trusted history with a known country. Passes when the current country or all historical countries are unknown.
- `MultipleCountriesCheck`: default 40. Flags a login if the same user has an earlier successful, unblocked login from another country within 60 minutes, even when both countries are familiar. Configure `withinMinutes(int $minutes)`. Requires country codes, not coordinates; unknown countries are ignored. Not enabled in built-in presets.
- `ImpossibleTravelCheck`: default 60. Compares coordinates with the most recent trusted login with coordinates. Configure `maxSpeedInKilometersPerHour(int $speed)`, default 900, and `minimumDistanceInKilometers(int $distance)`, default 300 to tolerate geolocation error.
- `CountryCheck`: default 50. Configure `onlyAllow(array $countryCodes)` or `block(array $countryCodes)` using country codes such as `['BE', 'NL']`. Blocked countries take precedence. Passes without a current country.
- `FailedAttemptsCheck`: default 30. Flags a successful login after 5 failed attempts for the same account in 15 minutes. Configure `threshold(int $failedAttempts)` and `withinMinutes(int $minutes)`. Unknown accounts do not count toward a known user's history.
- `CredentialStuffingCheck`: default 40. Flags an IP that failed to authenticate against at least 5 distinct identifiers in 60 minutes, including identifiers for nonexistent accounts. Configure `threshold(int $accounts)` and `withinMinutes(int $minutes)`.
- `UnusualTimeCheck`: default 15. Uses the latest 100 trusted logins, requires 10, and allows 2 hours either side of a familiar hour, including across midnight. Configure `minimumLogins(int $logins)`, `toleranceInHours(int $hours)`, and `timezone(string $timezone)`; defaults to `app.timezone`.
- `SuspiciousUserAgentCheck`: default 20. Flags missing user agents and known script/headless clients.
- `IpReputationCheck`: default 50. Flags Tor exit nodes or an abuse score of at least 50. Configure `allowTor()` and `minimumAbuseScore(int $abuseScore)`. Select an IP reputation driver; its `null` default has no reputation data.

Geolocation checks pass when required location data is missing. Selecting a driver does not add checks to the preset.

## Custom checks

Extend `Spatie\SuspiciousLogin\Checks\Check` and implement `public function evaluate(LoginAttempt $attempt): CheckResult`, importing `LoginAttempt` from `Models` and `CheckResult` from `Checks`. Return `$this->pass()` or `$this->flag('Human-readable reason')`. Reasons appear in stored attempts and user emails, so keep them suitable for users and avoid secrets. Set `protected int $score` for a default; callers can override it with `score()`.

Use `$attempt->history()->trusted()` for known devices and locations. `history()` excludes the current record and records after its timestamp. Failed and blocked logins are not trustworthy evidence that a device or location belongs to the user.

## Built-in and custom actions

`NotifyUserAction::new()` sends the configured notification to `$attempt->authenticatable`. It skips deleted users and users without `notify()`.

`BlockLoginAction::new()->errorKey('email')->message('...')` marks the saved attempt blocked, dispatches `SuspiciousLoginBlockedEvent`, logs the user out of the recorded session guard, and throws a validation exception. Put it last because it ends the request. It is not suitable for logging out stateless guards or revoking API tokens.

To customize the rejection, extend `BlockLoginAction` and override `protected function respond(LoginAttempt $attempt): never`. Throw an exception or abort with an HTTP response. Returning a response from a login-event listener will not replace the controller's response. Keep the blocking/event/logout lifecycle in the inherited `execute()` method.

For another action, extend `Spatie\SuspiciousLogin\Actions\Action`, implement `public function execute(LoginAttempt $attempt): void`, and return `YourAction::new()` from the preset. Both checks and actions resolve `new()` through the container, allowing constructor injection. For queued webhooks or MFA, implement the application-specific delivery or challenge; the package has neither built in.

## Customize notifications

The default `Spatie\SuspiciousLogin\Notifications\SuspiciousLoginNotification` implements `ShouldQueue` and uses the mail channel. It receives `LoginAttempt` in its constructor. In `config/suspicious-login.php`, set `'notification' => App\Notifications\SuspiciousLoginNotification::class` to use an application notification. Extend the default notification to reuse behavior, or implement a Laravel notification for Slack, SMS, or other channels. Queue selection and retry behavior belong on the notification, not additional package config keys.

To change only the content or layout:

```bash
php artisan vendor:publish --tag="suspicious-login-views"
```

Edit `resources/views/vendor/suspicious-login/mail/suspicious-login.blade.php`. It uses Laravel's Markdown mail components and receives `$loginAttempt` and `$place` (city and country, or translated `Unknown`). Escape user agents and reasons with Blade's escaped output. Override `toMail()` in a notification subclass to change the subject, for example `return parent::toMail($notifiable)->subject('Unusual sign-in');`.

Do not claim a login was blocked merely because this notification is sent: the preset may only notify, or a synchronous notification may run before the blocking action. Add account-security buttons only for routes the application actually provides.

## Events

Use the events in `Spatie\SuspiciousLogin\Events` for logging and integrations instead of adding logging configuration. Each has a public `$loginAttempt` property:

- `LoginAttemptRecordedEvent`: after saving any attempt; successful attempts are scored first except with `NonePreset`. Failed attempts and `NonePreset` logins are stored without scoring or preset actions.
- `SuspiciousLoginDetectedEvent`: after recording a medium/high-risk successful login and before executing its actions. Also fires with `MonitorOnlyPreset`, never with `NonePreset`.
- `SuspiciousLoginBlockedEvent`: when `BlockLoginAction` marks the attempt blocked, before logout and rejection.
