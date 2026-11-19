<?php

namespace Spatie\SuspiciousLogin\Presets;

class NonePreset extends Preset
{
    public function checks(): array
    {
        return [];
    }
}
