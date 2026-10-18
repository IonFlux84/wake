<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Wake\Forms\FormGuard;

beforeEach(function () {
    Route::post('test-form', fn () => 'sent')->middleware('wake.form:contact');
});

function stampOf(string $form): string
{
    preg_match('/name="_wake_form" value="([^"]+)"/', (string) FormGuard::fields($form), $m);

    return $m[1];
}

it('renders a signed stamp and a hidden honeypot with @wakeForm', function () {
    $html = Blade::render("<form>@wakeForm('contact')</form>");

    expect($html)->toContain('name="_wake_form"')->toContain('name="_wake_website"')->toContain('aria-hidden="true"');
});

it('lets a person send the form, but not bots or other sites', function () {
    $stamp = stampOf('contact');

    $this->post('/test-form', ['_wake_form' => $stamp])->assertStatus(422); // sent at once: a bot

    $this->travel(5)->seconds();

    $this->post('/test-form', ['_wake_form' => $stamp, '_wake_website' => 'spam'])->assertStatus(422);
    $this->post('/test-form', ['_wake_form' => stampOf('newsletter')])->assertStatus(422);
    $this->post('/test-form', ['_wake_form' => '123.abc'])->assertStatus(422);
    $this->post('/test-form', [])->assertStatus(422);
    $this->post('/test-form', ['_wake_form' => $stamp])->assertOk()->assertSee('sent');

    $this->travel(8)->days();

    $this->post('/test-form', ['_wake_form' => $stamp])->assertStatus(422);
});

it('allows ten submissions per visitor in ten minutes', function () {
    $stamp = stampOf('contact');
    $this->travel(5)->seconds();

    foreach (range(1, 10) as $i) {
        $this->post('/test-form', ['_wake_form' => $stamp])->assertOk();
    }

    $this->post('/test-form', ['_wake_form' => $stamp])->assertStatus(429);
});
