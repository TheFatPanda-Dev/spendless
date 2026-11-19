<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\EnforceCanonicalHost;
use App\Http\Middleware\PreventAuthenticatedResponseCaching;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The admin area at /admin, for users with is_admin. People sign in on the app's own
 * login page (with two-factor); the panel has no login screen of its own.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('SpendLess admin')
            ->brandLogo(fn (): string => asset('images/brand/lockup-on-light.svg'))
            ->darkModeBrandLogo(fn (): string => asset('images/brand/lockup-on-dark.svg'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn (): string => asset('images/brand/favicon.svg'))
            ->font('Roboto', url: fn (): string => asset('fonts/roboto/roboto.css'), provider: LocalFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->breadcrumbs(false)
            ->colors([
                // The identity kit's deep green (#006447 at 600, mint #09c98c at 400).
                'primary' => [
                    50 => 'oklch(0.975 0.018 160)',
                    100 => 'oklch(0.945 0.04 160)',
                    200 => 'oklch(0.89 0.07 160)',
                    300 => 'oklch(0.82 0.11 162)',
                    400 => 'oklch(0.74 0.14 163)',
                    500 => 'oklch(0.6 0.12 164)',
                    600 => 'oklch(0.443 0.093 165)',
                    700 => 'oklch(0.385 0.08 165)',
                    800 => 'oklch(0.325 0.065 165)',
                    900 => 'oklch(0.27 0.05 165)',
                    950 => 'oklch(0.2 0.04 165)',
                ],
                // The kit's green-tinted neutrals: sage canvas to the dark #111714.
                'gray' => [
                    50 => 'oklch(0.976 0.006 125)',
                    100 => 'oklch(0.955 0.008 145)',
                    200 => 'oklch(0.905 0.011 150)',
                    300 => 'oklch(0.85 0.013 152)',
                    400 => 'oklch(0.7 0.017 155)',
                    500 => 'oklch(0.57 0.018 158)',
                    600 => 'oklch(0.47 0.02 158)',
                    700 => 'oklch(0.38 0.018 158)',
                    800 => 'oklch(0.29 0.015 158)',
                    900 => 'oklch(0.235 0.012 158)',
                    950 => 'oklch(0.19 0.011 160)',
                ],
                'danger' => Color::Red,
                'warning' => Color::Amber,
                'success' => Color::Emerald,
                'info' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->navigationItems([
                NavigationItem::make('Back to SpendLess')
                    ->url('/dashboard')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->sort(100),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                EnforceCanonicalHost::class,
                PreventAuthenticatedResponseCaching::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
