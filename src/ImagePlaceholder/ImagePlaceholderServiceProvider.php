<?php

namespace ImagePlaceholder;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use ImagePlaceholder\Console\ClearImagePlaceholderCache;

class ImagePlaceholderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/image-placeholder.php', 'image-placeholder');

        // ImageManager をシングルトンでバインド（driver は config から）
        $this->app->singleton(ImageManager::class, function () {
            $driver = strtolower(config('image-placeholder.driver', 'gd'));
            return new ImageManager(match ($driver) {
                'imagick' => new ImagickDriver(),
                default   => new GdDriver(),
            });
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__ . '/../../config/image-placeholder.php' => config_path('image-placeholder.php')],
                'image-placeholder-config'
            );
            $this->commands([ClearImagePlaceholderCache::class]);
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        $prefix = config('image-placeholder.route_prefix', 'placeholder');
        $mw = config('image-placeholder.middleware', ['web']);
        $ctrl = \ImagePlaceholder\Http\Controllers\ImagePlaceholderController::class;

        Route::group(['prefix' => $prefix, 'middleware' => $mw], function () use ($ctrl) {
            // 300x300 / nxn [/bg[/fg[/format]]]
            Route::get('{size}', $ctrl)->where('size', '\d+x\d+|nxn');
            Route::get('{size}/{bg}', $ctrl)->where(['size' => '\d+x\d+|nxn', 'bg' => '[#0-9a-fA-F]{3,8}']);
            Route::get('{size}/{bg}/{fg}', $ctrl)->where(['size' => '\d+x\d+|nxn', 'bg' => '[#0-9a-fA-F]{3,8}', 'fg' => '[#0-9a-fA-F]{3,8}']);
            Route::get('{size}/{bg}/{fg}/{format}', $ctrl)->where(['size' => '\d+x\d+|nxn', 'format' => '[a-zA-Z]+']);
        });
    }
}
