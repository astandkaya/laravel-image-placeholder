<?php

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ImagePlaceholder\ImagePlaceholderServiceProvider;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
// use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Illuminate\Encryption\Encrypter;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ImagePlaceholderServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // APP_KEY を設定（AES-256-CBC 用に 32 bytes）
        $app['config']->set('app.cipher', 'AES-256-CBC');
        $key = 'base64:' . base64_encode(Encrypter::generateKey($app['config']['app.cipher']));
        $app['config']->set('app.key', $key);

        // ここでパッケージの設定を上書き
        $app['config']->set('placeholder.allowed_formats', ['png','jpg','jpeg','webp']);
        $app['config']->set('placeholder.route_prefix', 'placeholder');
        $app['config']->set('placeholder.middleware', ['web']);
        $app['config']->set('placeholder.driver', 'gd'); // CI 安定のため gd
        $app['config']->set('placeholder.cache.etag', true);
        $app['config']->set('placeholder.cache.browser_max_age', 60);
        $app['config']->set('placeholder.cache.disk', false);
        $app['config']->set('placeholder.default.format', 'png');
        $app['config']->set('placeholder.allowed_formats', ['png','jpg','jpeg','webp']);

        // ImageManager のドライバを明示
        $app->singleton(ImageManager::class, fn () => new ImageManager(new GdDriver()));
        // Imagick を使うなら上記をコメントアウトして以下を有効化
        // $app->singleton(ImageManager::class, fn () => new ImageManager(new ImagickDriver()));
    }
}