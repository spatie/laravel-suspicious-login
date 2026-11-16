---
title: Using your own model
weight: 5
---

To add your own methods, relations or scopes to login attempts, extend `Spatie\SuspiciousLogin\Models\LoginAttempt`:

```php
namespace App\Models;

use Spatie\SuspiciousLogin\Models\LoginAttempt as BaseLoginAttempt;

class LoginAttempt extends BaseLoginAttempt
{
    public function wasFromMobile(): bool
    {
        return str_contains((string) $this->user_agent, 'Mobile');
    }
}
```

Then register it in `config/suspicious-login.php`:

```php
'model' => App\Models\LoginAttempt::class,
```

From now on, the package creates your model for every login attempt, and checks, actions and events receive it.

When you schedule pruning, pass your own model:

```php
Schedule::command('model:prune', ['--model' => [App\Models\LoginAttempt::class]])->daily();
```
