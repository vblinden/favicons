<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class LlmsTxtController extends Controller
{
    public function __invoke(): Response
    {
        return response(view('llms')->render(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
