<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectStatus: string implements HasLabel, HasColor
{
    case Poc = 'poc';
    case Running = 'running';
    case Completed = 'completed';
    case Managed = 'managed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Poc => 'POC',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Managed => 'Managed Services',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Poc => 'warning',
            self::Running => 'info',
            self::Completed => 'success',
            self::Managed => 'primary',
        };
    }
}
