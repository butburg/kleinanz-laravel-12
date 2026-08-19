<?php

use Illuminate\Support\Facades\Route;

it('renders a useful server error page with recovery and support links', function (): void {
    Route::get('/test-server-error', fn () => abort(500));

    $this->get('/test-server-error')
        ->assertInternalServerError()
        ->assertSee('Something went wrong on the server')
        ->assertSee('not a missing page')
        ->assertSee('Report this problem')
        ->assertSee(route('support.create', absolute: false), escape: false)
        ->assertSee(route('home', absolute: false), escape: false);
});
