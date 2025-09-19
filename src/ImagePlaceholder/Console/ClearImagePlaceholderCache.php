<?php

namespace ImagePlaceholder\Console;

use Illuminate\Console\Command;

class ClearImagePlaceholderCache extends Command
{
    protected $signature = 'placeholder:clear';
    protected $description = 'Clear placeholder disk cache';

    public function handle(): int
    {
        $dir = (string) config('image-placeholder.cache.disk_path');
        if (!is_dir($dir)) {
            $this->info('No cache directory');
            return self::SUCCESS;
        }
        $count = 0;
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_file($f)) { @unlink($f); $count++; }
        }
        $this->info("Cleared {$count} files from {$dir}");
        return self::SUCCESS;
    }
}