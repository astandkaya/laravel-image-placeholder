<?php

namespace ImagePlaceholder\Support;

use Intervention\Image\ImageManager;

class ImageGenerator
{
    public function __construct(private ImageManager $manager) {}

    public function generate(array $opt): string
    {
        $w = (int) $opt['width'];
        $h = (int) $opt['height'];
        $bg = $this->toRgbaString((string) $opt['bg']);
        $fg = $this->toRgbaString((string) $opt['fg']);
        $text = $opt['text'] ?? null;
        $fmt  = strtolower($opt['format'] ?? 'png');

        $image = $this->manager->create($w, $h);
        $image->fill($bg);

        if ($text !== null && $text !== '') {
            $fontPath = config('image-placeholder.default.ttf_font');
            $fontSize = (int) (config('image-placeholder.default.font_size') ?: max(8, min($w, $h) / 5));

            $image->text($text, (int) ($w / 2), (int) ($h / 2), function ($font) use ($fontPath, $fontSize, $fg) {
                if ($fontPath && is_file($fontPath)) {
                    $font->filename($fontPath);
                }
                $font->size($fontSize);
                $font->color($fg);
                $font->align('center');
                $font->valign('middle');
            });
        }

        if (config('image-placeholder.debug.overlay')) {
            $this->drawOverlay($image, $fg);
        }

        return match ($fmt) {
            'jpg', 'jpeg' => $image->toJpeg((int) config('image-placeholder.default.jpeg_quality', 90)),
            'webp'        => $image->toWebp((int) config('image-placeholder.default.webp_quality', 80)),
            default       => $image->toPng(), // 既定: PNG
        };
    }

    private function drawOverlay($image, string $color): void
    {
        // ガイドライン
        $w = $image->width();
        $h = $image->height();
        $image->line(0, (int)($h/2), $w, (int)($h/2), function ($line) use ($color) {
            $line->color($color);
            $line->width(1);
        });
        $image->line((int)($w/2), 0, (int)($w/2), $h, function ($line) use ($color) {
            $line->color($color);
            $line->width(1);
        });
    }

    // #RGB/#RRGGBB/#RRGGBBAA/#RGBA → rgba(r,g,b,a) に正規化
    private function toRgbaString(string $hex): string
    {
        $h = ltrim($hex, '#');
        $r=$g=$b=$a=255;

        if (strlen($h) === 3) {
            $r = hexdec(str_repeat($h[0], 2));
            $g = hexdec(str_repeat($h[1], 2));
            $b = hexdec(str_repeat($h[2], 2));
        } elseif (strlen($h) === 4) {
            $r = hexdec(str_repeat($h[0], 2));
            $g = hexdec(str_repeat($h[1], 2));
            $b = hexdec(str_repeat($h[2], 2));
            $a = hexdec(str_repeat($h[3], 2));
        } elseif (strlen($h) === 6) {
            $r = hexdec(substr($h, 0, 2));
            $g = hexdec(substr($h, 2, 2));
            $b = hexdec(substr($h, 4, 2));
        } elseif (strlen($h) === 8) {
            $r = hexdec(substr($h, 0, 2));
            $g = hexdec(substr($h, 2, 2));
            $b = hexdec(substr($h, 4, 2));
            $a = hexdec(substr($h, 6, 2));
        }

        $alpha = round($a / 255, 3);
        return "rgba({$r},{$g},{$b},{$alpha})";
    }
}