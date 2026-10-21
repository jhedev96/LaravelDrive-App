<?php

namespace Tests\Feature\Files;

use App\Language;
use App\User;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TranslationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Passport::actingAs(User::factory()->create());
    }

    public function test_languages_can_be_registered_and_listed(): void
    {
        // old contract: a flat map of name => locale
        $this->getJson('/api/translation/languages')->assertOk();

        $created = $this->postJson('/api/translation/languages', [
            'locale' => 'de',
            'name' => 'Deutsch',
        ])->assertStatus(201)->json('data');

        $this->assertSame('de', $created['locale']);
        $this->assertSame('Deutsch', $created['name']);

        // the locale must stay unique
        $this->postJson('/api/translation/languages', ['locale' => 'de', 'name' => 'X'])
            ->assertStatus(422);

        $this->getJson('/api/translation/languages')
            ->assertOk()
            ->assertJsonPath('data.Deutsch', 'de');
    }

    public function test_translation_lines_follow_the_single_and_group_contract(): void
    {
        // an ungrouped key lands in the `single` bucket (group `*`)
        $this->postJson('/api/translation/de/translations', [
            'language' => 'de',
            'key' => 'nav.home',
            'value' => 'Startseite',
        ])->assertSuccessful();

        $single = $this->getJson('/api/translation/de/translations')
            ->assertOk()->json('data.single.single');

        $this->assertSame('Startseite', $single['nav.home']['de']);

        // a grouped key lands under its group
        $this->postJson('/api/translation/de/translations', [
            'language' => 'de',
            'key' => 'back',
            'group' => 'nav',
            'value' => 'Zurück',
        ])->assertSuccessful();

        $this->getJson('/api/translation/de/translations')
            ->assertOk()
            ->assertJsonPath('data.group.nav.back.de', 'Zurück');
    }

    public function test_upsert_accumulates_locales_and_updates_values(): void
    {
        $this->postJson('/api/translation/de/translations', [
            'language' => 'de',
            'key' => 'back',
            'group' => 'nav',
            'value' => 'Zurück',
        ])->assertSuccessful();

        $this->postJson('/api/translation/en/translations', [
            'language' => 'en',
            'key' => 'back',
            'group' => 'nav',
            'value' => 'Back',
        ])->assertSuccessful();

        // both locales now live on the same line
        $this->getJson('/api/translation/en/translations')
            ->assertOk()
            ->assertJsonPath('data.group.nav.back.de', 'Zurück')
            ->assertJsonPath('data.group.nav.back.en', 'Back');

        // updating rewrites only the touched locale
        $this->putJson('/api/translation/en/translations', [
            'language' => 'en',
            'key' => 'back',
            'group' => 'nav',
            'value' => 'Go back',
        ])->assertSuccessful();

        $this->getJson('/api/translation/en/translations')
            ->assertOk()
            ->assertJsonPath('data.group.nav.back.en', 'Go back')
            ->assertJsonPath('data.group.nav.back.de', 'Zurück');
    }

    public function test_stored_lines_feed_the_translation_loader(): void
    {
        Language::create(['locale' => 'de', 'name' => 'Deutsch']);

        $this->postJson('/api/translation/de/translations', [
            'language' => 'de',
            'key' => 'back',
            'group' => 'nav',
            'value' => 'Zurück',
        ])->assertSuccessful();

        // D14: spatie translation-loader serves our lines through __()
        app()->setLocale('de');
        $this->assertSame('Zurück', __('nav.back'));

        // unknown keys fall back through untouched
        $this->assertSame('nav.missing', __('nav.missing'));
    }
}
