<?php

namespace Ges\FilamentGreenApi\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;
use Ges\FilamentGreenApi\Filament\Pages\GreenApiInbox;
use Ges\FilamentGreenApi\Filament\Pages\GreenApiSettings;
use Ges\FilamentGreenApi\GreenApiPlugin;
use Ges\FilamentGreenApi\Tests\TestCase;

class GreenApiPluginTest extends TestCase
{
    public function test_plugin_make_resolves_the_registered_singleton(): void
    {
        $this->assertSame(app(GreenApiPlugin::class), GreenApiPlugin::make());
        $this->assertSame('green-api', GreenApiPlugin::make()->getId());
    }

    public function test_plugin_registers_pages_on_a_panel(): void
    {
        $panel = Filament::getCurrentPanel();

        $this->assertTrue($panel->hasPlugin('green-api'));
        $this->assertContains(GreenApiSettings::class, $panel->getPages());
        $this->assertContains(GreenApiInbox::class, $panel->getPages());

        $styles = FilamentAsset::getStyles(['ges/filament-green-api']);

        $matchingStyle = collect($styles)->first(fn ($style): bool => $style->getId() === 'styles');

        $this->assertNotNull($matchingStyle);
        $this->assertStringEndsWith('/resources/dist/filament-green-api.css', $matchingStyle->getPath());
    }
}
