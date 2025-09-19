<?php

namespace ImagePlaceholder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ImagePlaceholder\Support\ImageGenerator;
use Intervention\Image\ImageManager;
use ImagePlaceholder\Events\ImagePlaceholderGenerated;

class ImagePlaceholderController extends Controller
{
    public function __construct(private ImageManager $manager) {}

    public function __invoke(Request $req, string $size, ?string $bg = null, ?string $fg = null, ?string $format = null)
    {
        $isRandom = ($size === 'nxn');

        if ($isRandom) {
            [$w, $h, $seed] = $this->randomSize($req);
        } else {
            [$w, $h] = array_map('intval', explode('x', strtolower($size)));
            $seed = $req->query('seed');
        }

        $w = max((int)config('image-placeholder.min_width'),  min((int)config('image-placeholder.max_width'),  $w));
        $h = max((int)config('image-placeholder.min_height'), min((int)config('image-placeholder.max_height'), $h));

        $bg = $req->query('bg', $bg ?? config('image-placeholder.default.bg'));
        $fg = $req->query('fg', $fg ?? config('image-placeholder.default.fg'));
        $format = strtolower($req->query('format', $format ?? config('image-placeholder.default.format')));
        if ($format === 'jpeg') $format = 'jpg';

        $allowed = config('image-placeholder.allowed_formats', []);
        if (!in_array($format, $allowed, true)) {
            $format = config('image-placeholder.default.format');
        }

        $textTpl = $req->query('text', config('image-placeholder.default.text'));
        $text = $textTpl ? strtr($textTpl, ['{w}' => (string)$w, '{h}' => (string)$h, '{size}' => "{$w}x{$h}"]) : null;

        $cacheKey = sha1(json_encode(compact('w','h','bg','fg','text','format','seed','isRandom'), JSON_UNESCAPED_UNICODE));

        if (config('image-placeholder.cache.etag')) {
            $ifNoneMatch = $req->headers->get('If-None-Match');
            if ($ifNoneMatch && trim($ifNoneMatch, '"') === $cacheKey) {
                return response('', 304, ['ETag' => "\"{$cacheKey}\""]);
            }
        }

        $diskCache = (bool)config('image-placeholder.cache.disk', false);
        $diskPath = null;
        if ($diskCache) {
            $dir = rtrim((string)config('image-placeholder.cache.disk_path'), '/');
            @is_dir($dir) || @mkdir($dir, 0775, true);
            $diskPath = "{$dir}/{$cacheKey}.{$format}";
            if (is_file($diskPath)) {
                return $this->fileResponse($diskPath, $format, $cacheKey, $isRandom);
            }
        }

        $gen = new ImageGenerator($this->manager);
        $binary = $gen->generate([
            'width'  => $w,
            'height' => $h,
            'bg'     => $bg,
            'fg'     => $fg,
            'text'   => $text,
            'format' => $format,
        ]);

        if ($diskCache) {
            @file_put_contents($diskPath, $binary);
        }

        $meta = [
            'width' => $w, 'height' => $h, 'bg' => $bg, 'fg' => $fg,
            'text' => $text, 'format' => $format, 'seed' => $seed,
            'cache_key' => $cacheKey, 'random' => $isRandom,
        ];

        if ($req->boolean(config('image-placeholder.debug.meta_query_key'))) {
            return response()->json($meta, 200);
        }

        if (config('image-placeholder.debug.event')) {
            event(new ImagePlaceholderGenerated($meta));
        }

        $headers = $this->headers($format, $cacheKey, $isRandom, $meta);

        return new StreamedResponse(function () use ($binary) {
            echo $binary;
        }, 200, $headers);
    }

    protected function randomSize(Request $req): array
    {
        $cfg = config('image-placeholder.random');
        $seed = $req->query('seed');
        $seed !== null ? mt_srand(crc32((string)$seed)) : null;

        $minW = (int)($req->query('min_w', $cfg['min_width']));
        $maxW = (int)($req->query('max_w', $cfg['max_width']));
        $minH = (int)($req->query('min_h', $cfg['min_height']));
        $maxH = (int)($req->query('max_h', $cfg['max_height']));

        $w = mt_rand($minW, $maxW);
        $h = $cfg['square'] ? $w : mt_rand($minH, $maxH);

        if ($seed !== null) mt_srand();

        return [$w, $h, $seed];
    }

    protected function headers(string $format, string $etag, bool $isRandom, array $meta): array
    {
        $h = [
            'Content-Type' => match ($format) {
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'webp'=> 'image/webp',
                default => 'application/octet-stream',
            }
        ];

        if (config('image-placeholder.cache.etag')) {
            $h['ETag'] = "\"{$etag}\"";
        }

        if ($isRandom && !config('image-placeholder.random.cacheable')) {
            $h['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
        } else {
            $h['Cache-Control'] = 'public, max-age=' . (int)config('image-placeholder.cache.browser_max_age', 86400);
        }

        if (config('image-placeholder.debug.headers')) {
            $h['X-Placeholder-Size'] = $meta['width'].'x'.$meta['height'];
            if (!empty($meta['seed'])) $h['X-Placeholder-Seed'] = (string)$meta['seed'];
            $h['X-Placeholder-CacheKey'] = $meta['cache_key'];
        }

        return $h;
    }

    protected function fileResponse(string $path, string $format, string $etag, bool $isRandom)
    {
        $headers = [
            'Content-Type' => match ($format) {
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'webp'=> 'image/webp',
                default => 'application/octet-stream',
            }
        ];
        if (config('image-placeholder.cache.etag')) $headers['ETag'] = "\"{$etag}\"";
        if ($isRandom && !config('image-placeholder.random.cacheable')) {
            $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
        } else {
            $headers['Cache-Control'] = 'public, max-age=' . (int)config('image-placeholder.cache.browser_max_age', 86400);
        }

        return response()->file($path, $headers);
    }
}
