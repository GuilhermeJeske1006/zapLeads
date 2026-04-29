<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class LangController extends Controller
{
    private array $supported = ['pt_BR', 'es'];

    public function switch(string $locale): RedirectResponse
    {
        if (in_array($locale, $this->supported)) {
            Session::put('locale', $locale);
        }

        return redirect()->back();
    }
}
