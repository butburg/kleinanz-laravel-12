<?php

use App\Ai\Agents\DefaultAppAssistant;

it('provides baseline instructions and no tools by default', function (): void {
    $agent = new DefaultAppAssistant;

    expect((string) $agent->instructions())->toBe('You are a helpful assistant.');
    expect(iterator_to_array($agent->tools()))->toBe([]);
});
