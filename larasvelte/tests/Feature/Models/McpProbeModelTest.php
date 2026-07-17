<?php

use App\Models\McpProbe;
use Illuminate\Database\Eloquent\Model;

it('is an eloquent model', function () {
    expect(new McpProbe)->toBeInstanceOf(Model::class);
});
