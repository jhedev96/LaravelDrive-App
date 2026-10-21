<?php

namespace App\Http\Controllers\Translation;

use App\Http\Controllers\API\V1\ApiController;
use App\Language;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LanguageController extends ApiController
{
    /**
     * List registered languages: map of name => locale, matching the old
     * translation store contract.
     */
    public function index()
    {
        $languages = Language::orderBy('id')->get()
            ->mapWithKeys(fn (Language $l) => [$l->name => $l->locale]);

        return new \Illuminate\Http\Resources\Json\JsonResource($languages);
    }

    /**
     * Add a new language.
     */
    public function store(Request $request)
    {
        $request->validate([
            'locale' => [
                'required', 'string', 'max:12',
                Rule::unique('languages', 'locale'),
            ],
            'name' => 'required|string|max:40',
        ]);

        $language = Language::create($request->only(['locale', 'name']));

        return new \Illuminate\Http\Resources\Json\JsonResource($language);
    }
}
