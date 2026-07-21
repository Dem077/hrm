<?php

namespace App\Enums;

enum StructureGroupCode: string
{
    case StrategicLeadership = 'strategic_leadership';
    case Division = 'division';
    case Department = 'department';
    case UnitSection = 'unit_section';

    public function label(): string
    {
        return match ($this) {
            self::StrategicLeadership => 'Strategic Leadership',
            self::Division => 'Division',
            self::Department => 'Department',
            self::UnitSection => 'Unit / Section',
        };
    }

    public function allowsNodes(): bool
    {
        return $this !== self::StrategicLeadership;
    }
}
