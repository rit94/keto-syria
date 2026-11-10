<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/*
| Home: guests are sent to login by `auth`; signed-in users go to Today once
| that feature exists, and see a minimal placeholder until then.
*/
Route::get('/', function () {
    if (Route::has('today')) {
        return redirect()->route('today');
    }

    return view('ui.home');
})->middleware(['auth', 'onboarded'])->name('home');

/*
| Locale switch: persists to the session and, when signed in, to users.locale.
*/
Route::post('/locale', function (Request $request) {
    $data = $request->validate([
        'locale' => ['required', 'string', Rule::in(config('app.available_locales'))],
    ]);

    $request->session()->put('locale', $data['locale']);
    $request->user()?->forceFill(['locale' => $data['locale']])->save();

    return back();
})->name('locale.switch');

/*
| Living style guide — every UI component in both directions. Local/testing only.
*/
Route::get('/_ui', function () {
    abort_unless(app()->environment(['local', 'testing']), 404);

    return view('ui.guide');
})->name('ui.guide');
