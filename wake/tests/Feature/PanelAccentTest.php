<?php

use Wake\Panel\Panel;

it('treats the old lime default as black and emits no glow', function () {
    config(['wake.panel.accent' => '#C6FF3D']);
    $css = (string) app(Panel::class)->accentStyles();

    expect($css)->toContain('--accent:#0A0A0A')->toContain('--accent-ink:#FFFFFF')->not->toContain('--glow');
});

it('keeps a chosen colour visible on dark surfaces', function () {
    config(['wake.panel.accent' => '#3D5AFE']);

    expect((string) app(Panel::class)->accentStyles())->toContain('--accent-ink:#3D5AFE');
});

it('shows the new sign-in page with the claim and no glow', function () {
    $this->get('/wake/setup')->assertOk()->assertSee('auth-ink', false)->assertSee('leaves a', false);
});
