<?php

use Illuminate\Support\Facades\Schema;

it('has published ai configuration for provider defaults', function (): void {
    expect(file_exists(config_path('ai.php')))->toBeTrue();
    expect(config('ai.default'))->toBe('openai');
});

it('runs laravel ai migrations for conversation storage', function (): void {
    expect(Schema::hasTable('agent_conversations'))->toBeTrue();
});
