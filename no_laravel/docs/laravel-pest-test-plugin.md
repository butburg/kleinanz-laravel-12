# Laravel Pest Test Plugin

## Contents

- [Installation](#installation)
- [Artisan Commands](#artisan-commands)
- [Assertions & Helpers](#assertions-helpers)
- [Resources](#resources)

Laravel
Source code: github.com/pestphp/pest-plugin-laravel v4

## Installation

To start using Pest's Laravel plugin, you need to require this plugin via Composer.

```bash
composer require pestphp/pest-plugin-laravel --dev
```

## Artisan Commands

This plugin adds additional Artisan commands and functions to the default Pest installation. For example, to generate a new test in the `tests/Feature` directory, you can now utilize the `pest:test` Artisan command.

```bash
php artisan pest:test UsersTest
```

You may provide the `--unit` option when creating a test to place the test in the `tests/Unit` directory.

```bash
php artisan pest:test UsersTest --unit
```

Executing the `pest:dataset` Artisan command will create a fresh dataset in the `tests/Datasets` directory.

```bash
php artisan pest:dataset Emails
```

## Assertions & Helpers

As you may know, Laravel provides a variety of assertions you can take advantage of in your feature tests. When using Pest's Laravel plugin, you may access all of those assertions as you typically would.

```php
it('has a welcome page', function () {
    $this->get('/')->assertStatus(200);
});
```

In addition, with the assistance of this plugin, it is possible for you to bypass the `$this` variable while using namespaced functions such as `actingAs`, `get`, `post` and `delete`.

```php
use function Pest\Laravel\{get};

it('has a welcome page', function () {
    get('/')->assertStatus(200);
    // same as $this->get('/')...
});
```

To illustrate this convenient feature using another example, we can write a test acting as an authenticated user accessing the restricted dashboard page.

```php
use App\Models\User;
use function Pest\Laravel\{actingAs};

test('authenticated user can access the dashboard', function () {
    $user = User::factory()->create();

    actingAs($user)->get('/dashboard')
        ->assertStatus(200);
});
```

As you may expect, all of the assertions that were previously accessible via `$this->` are available as namespace functions.

```php
use function Pest\Laravel\{actingAs, get, post, delete, ...};
```

## Resources

You can find the full testing documentation on the Laravel website: laravel.com/docs/12.x/testing.
