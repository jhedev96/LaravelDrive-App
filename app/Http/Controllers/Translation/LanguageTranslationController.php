<?php

namespace App\Http\Controllers\Translation;

use App\Http\Controllers\API\V1\ApiController;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Spatie\TranslationLoader\LanguageLine;

/**
 * Translation lines CRUD on top of spatie/laravel-translation-loader,
 * keeping the response contract of the old Translation UI:
 * data = { single: { single: {key: {locale: value}} }, group: {group: {key: {locale: value}}} }
 */
class LanguageTranslationController extends ApiController
{
    /**
     * List all translation lines, grouped, with every locale value per key.
     */
    public function index()
    {
        $lines = LanguageLine::all();

        $single = [];
        $grouped = [];

        foreach ($lines as $line) {
            if ($line->group === '*' || $line->group === '' || $line->group === 'single') {
                $single[$line->key] = $line->text;
            } else {
                $grouped[$line->group][$line->key] = $line->text;
            }
        }

        $payload = [
            'single' => ['single' => $single],
            'group' => $grouped,
        ];

        return new JsonResource($payload);
    }

    /**
     * Add a translation line (upsert for the requested locale).
     */
    public function store(Request $request)
    {
        $request->validate([
            'language' => 'required|string|max:12',
            'key' => 'required|string|max:190',
            'group' => 'nullable|string|max:190',
            'namespace' => 'nullable|string|max:190',
            'value' => 'nullable|string',
        ]);

        $language = $request->input('language');
        $group = $this->resolveGroup($request);

        $line = $this->upsert($group, $request->input('key'), $language, $request->input('value'));

        return new JsonResource($line);
    }

    /**
     * Update one translated value for the given key/group.
     */
    public function update(Request $request)
    {
        $request->validate([
            'language' => 'required|string|max:12',
            'key' => 'required|string|max:190',
            'group' => 'nullable|string|max:190',
            'namespace' => 'nullable|string|max:190',
            'value' => 'nullable|string',
        ]);

        $language = $request->input('language');
        $group = $this->resolveGroup($request);

        $line = $this->upsert($group, $request->input('key'), $language, $request->input('value'));

        return new JsonResource($line);
    }

    private function resolveGroup(Request $request): string
    {
        $group = (string) $request->input('group', '');

        $namespace = (string) $request->input('namespace', '');

        if ($group === '') {
            return '*';
        }

        if ($namespace !== '') {
            $group = $namespace.'::'.$group;
        }

        return $group;
    }

    private function upsert(string $group, string $key, string $locale, $value): LanguageLine
    {
        $line = LanguageLine::where('group', $group)->where('key', $key)->first();

        if (! $line) {
            return LanguageLine::create([
                'group' => $group,
                'key' => $key,
                'text' => [$locale => $value],
            ]);
        }

        $texts = $line->text;
        $texts[$locale] = $value;
        $line->text = $texts;
        $line->save();

        return $line;
    }
}
