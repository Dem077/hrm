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

    /**
     * Parent group codes allowed for a node of this type (empty = must be top-level).
     *
     * @return list<self>
     */
    public function allowedParentCodes(): array
    {
        return match ($this) {
            self::Division => [],
            self::Department => [self::Division, self::Department],
            self::UnitSection => [self::Department, self::UnitSection],
            self::StrategicLeadership => [],
        };
    }

    public function requiresParent(): bool
    {
        return $this->allowedParentCodes() !== [];
    }

    /**
     * Child group codes that can be added directly under a node of this type.
     *
     * @return list<self>
     */
    public function allowedChildCodes(): array
    {
        return match ($this) {
            self::Division => [self::Department],
            self::Department => [self::Department, self::UnitSection],
            self::UnitSection => [self::UnitSection],
            self::StrategicLeadership => [self::Division],
        };
    }
}
