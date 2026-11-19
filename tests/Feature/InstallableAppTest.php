<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallableAppTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        return json_decode(
            (string) file_get_contents(public_path('images/brand/site.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    public function test_pages_link_the_web_app_manifest(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/images/brand/site.webmanifest">', false);
    }

    public function test_manifest_opens_the_app_full_screen_on_the_dashboard(): void
    {
        $manifest = $this->manifest();

        $this->assertSame('SpendLess', $manifest['name']);
        $this->assertSame('/', $manifest['id']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
    }

    public function test_manifest_has_installable_icons_that_exist(): void
    {
        $icons = collect($this->manifest()['icons']);

        $this->assertTrue($icons->contains(fn (array $icon): bool => $icon['sizes'] === '192x192'));
        $this->assertTrue($icons->contains(fn (array $icon): bool => $icon['sizes'] === '512x512'));
        $this->assertTrue($icons->contains(fn (array $icon): bool => $icon['purpose'] === 'maskable'));

        $icons->each(fn (array $icon) => $this->assertFileExists(public_path(ltrim($icon['src'], '/'))));
    }

    public function test_manifest_shortcuts_point_at_real_routes(): void
    {
        $shortcutUrls = collect($this->manifest()['shortcuts'])->pluck('url')->all();

        $this->assertSame([route('activity.index', absolute: false), route('budgets.index', absolute: false)], $shortcutUrls);
    }

    public function test_service_worker_precaches_files_that_exist(): void
    {
        $worker = (string) file_get_contents(public_path('sw.js'));

        preg_match('/const SHELL_FILES = \[(.*?)\];/s', $worker, $match);
        preg_match_all("/'([^']+)'/", $match[1] ?? '', $files);

        $this->assertContains('/offline.html', $files[1]);

        foreach ($files[1] as $file) {
            $this->assertFileExists(public_path(ltrim($file, '/')));
        }
    }

    public function test_service_worker_never_caches_pages_or_data(): void
    {
        $worker = (string) file_get_contents(public_path('sw.js'));

        preg_match('/const CACHEABLE_PREFIXES = \[(.*?)\];/s', $worker, $match);
        preg_match_all("/'([^']+)'/", $match[1] ?? '', $prefixes);

        $this->assertSame(['/build/assets/', '/fonts/', '/images/brand/'], $prefixes[1]);
    }
}
