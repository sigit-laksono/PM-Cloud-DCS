<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\FilamentUserSettings;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->spa()
            ->databaseTransactions()
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
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
                FilamentUserSettings::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(PanelsRenderHook::BODY_END, fn () => Blade::render(<<<'HTML'
                <script data-navigate-once>
                (function () {
                    const SEL = '.fi-sidebar-nav';
                    const KEY = 'fi_sidebar_scroll';

                    // Simpan posisi setiap kali user scroll sidebar
                    document.addEventListener('scroll', function (e) {
                        if (e.target && e.target.closest && e.target.closest(SEL) === e.target) {
                            sessionStorage.setItem(KEY, e.target.scrollTop);
                        }
                    }, true);

                    function restore() {
                        const nav = document.querySelector(SEL);
                        const saved = parseInt(sessionStorage.getItem(KEY) || '0', 10);
                        if (nav && saved > 0) {
                            nav.scrollTop = saved;
                        }
                    }

                    // Restore setelah navigasi dengan beberapa percobaan bertahap
                    // karena Alpine.js (x-cloak removal) bisa reset scroll setelah livewire:navigated
                    document.addEventListener('livewire:navigated', function () {
                        restore();
                        requestAnimationFrame(restore);
                        setTimeout(restore, 50);
                        setTimeout(restore, 150);
                        setTimeout(restore, 400);
                    });
                })();
                </script>
            HTML));


        return $panel;
    }
}
