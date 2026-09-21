<?php

namespace App\Enums;

enum AdministrativeAreaType: string
{
    case Province = 'province';
    case Municipality = 'municipality';
    case Barangay = 'barangay';
    case Purok = 'purok';

    public function label(): string
    {
        return match ($this) {
            self::Province => 'Province',
            self::Municipality => 'Municipality / City',
            self::Barangay => 'Barangay',
            self::Purok => 'Purok',
        };
    }

    public function parentType(): ?self
    {
        return match ($this) {
            self::Province => null,
            self::Municipality => self::Province,
            self::Barangay => self::Municipality,
            self::Purok => self::Barangay,
        };
    }
}
