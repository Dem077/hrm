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
     * Parent group codes allowed for a node of this type (empty = must be top-level only).
     *
     * @return list<self>
     */
    public function allowedParentCodes(): array
    {
        return match ($this) {
            self::Division => [],
            self::Department => [self::Division, self::Department],
            self::UnitSection => [self::Division, self::Department, self::UnitSection],
            self::StrategicLeadership => [],
        };
    }

    /**
     * Whether this type may sit at the organization root (under Strategic Leadership).
     */
    public function allowsTopLevel(): bool
    {
        return match ($this) {
            self::Division, self::UnitSection => true,
            default => false,
        };
    }

    public function requiresParent(): bool
    {
        return $this->allowsNodes() && ! $this->allowsTopLevel();
    }

    /**
     * Child group codes that can be added directly under a node of this type.
     *
     * @return list<self>
     */
    public function allowedChildCodes(): array
    {
        return match ($this) {
            self::Division => [self::Department, self::UnitSection],
            self::Department => [self::Department, self::UnitSection],
            self::UnitSection => [self::UnitSection],
            self::StrategicLeadership => [self::Division, self::UnitSection],
        };
    }
}
