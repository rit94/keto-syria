<?php

it('renders the style guide in Arabic with both direction panels', function () {
    $this->get('/_ui')
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee(__('ui.title', [], 'ar'))
        ->assertSee('data-ui-panel="ar"', false)
        ->assertSee('data-ui-panel="en"', false)
        // each panel speaks its own language regardless of the page locale
        ->assertSee(__('ui.sections.macros', [], 'ar'))
        ->assertSee(__('ui.sections.macros', [], 'en'))
        ->assertSee(__('common.estimate', [], 'ar'))
        ->assertSee(__('common.estimate', [], 'en'));
});

it('renders the style guide in English', function () {
    $this->withSession(['locale' => 'en'])
        ->get('/_ui')
        ->assertOk()
        ->assertSee('<html lang="en" dir="ltr">', false)
        ->assertSee(__('ui.title', [], 'en'))
        ->assertSee('data-ui-panel="ar"', false)
        ->assertSee('data-ui-panel="en"', false);
});

it('restores the page locale after rendering both panels', function () {
    $this->get('/_ui')->assertOk();

    expect(app()->getLocale())->toBe('ar');
});

it('shows every component family', function () {
    $html = $this->get('/_ui')->getContent();

    expect($html)
        ->toContain('role="progressbar"')            // macro-bar
        ->toContain('stroke-dasharray')              // macro-ring
        ->toContain('role="dialog"')                 // modal / bottom-sheet
        ->toContain('role="tablist"')                // tabs
        ->toContain('x-data="numberStepper(')        // number-stepper
        ->toContain('x-data="toaster(')              // toaster
        ->toContain('<figure')                       // charts
        ->toContain('type="radio"')                  // segmented
        ->toContain('skeleton');
});

it('is not available in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/_ui')->assertNotFound();
});
