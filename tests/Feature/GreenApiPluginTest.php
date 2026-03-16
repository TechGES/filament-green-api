<?php

namespace Ges\FilamentGreenApi\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Support\Assets\Css;
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

        $matchingStyle = null;

        foreach ($styles as $style) {
            if ($style instanceof Css && $style->getId() === 'styles') {
                $matchingStyle = $style;

                break;
            }
        }

        $this->assertNotNull($matchingStyle);
        $this->assertStringEndsWith('/resources/dist/filament-green-api.css', $matchingStyle->getPath());
    }

    public function test_page_navigation_metadata_can_be_configured_per_page(): void
    {
        config()->set('green_api_filament.pages.settings.navigation_icon', 'heroicon-o-wrench-screwdriver');
        config()->set('green_api_filament.pages.settings.navigation_group', 'Admin');
        config()->set('green_api_filament.pages.settings.navigation_sort', 10);
        config()->set('green_api_filament.pages.whatsapp.navigation_icon', 'heroicon-o-chat-bubble-oval-left-ellipsis');
        config()->set('green_api_filament.pages.whatsapp.navigation_group', 'Support');
        config()->set('green_api_filament.pages.whatsapp.navigation_sort', 20);

        $this->assertSame('heroicon-o-wrench-screwdriver', GreenApiSettings::getNavigationIcon());
        $this->assertSame('Admin', GreenApiSettings::getNavigationGroup());
        $this->assertSame(10, GreenApiSettings::getNavigationSort());

        $this->assertSame('heroicon-o-chat-bubble-oval-left-ellipsis', GreenApiInbox::getNavigationIcon());
        $this->assertSame('Support', GreenApiInbox::getNavigationGroup());
        $this->assertSame(20, GreenApiInbox::getNavigationSort());
    }
}
