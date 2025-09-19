<?php

namespace ImagePlaceholder\Events;

class ImagePlaceholderGenerated
{
    public function __construct(public array $meta)
    {
    }
}
