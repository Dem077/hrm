<?php

namespace App\Enums;

enum LeaveApprovalStepKey: string
{
    case DirectManager = 'direct_manager';
    case UnitSection = 'unit_section';
    case Department = 'department';
    case Division = 'division';
    case Hr = 'hr';

    public function label(): string
    {
        return match ($this) {
            self::DirectManager => 'Direct manager',
            self::UnitSection => 'Unit / Section head',
            self::Department => 'Department head',
            self::Division => 'Division head',
            self::Hr => 'HR (final approval)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DirectManager => 'Bypass: if the employee has a direct manager, approval goes to that manager then HR (structure head steps are skipped).',
            self::UnitSection => 'Head of the employee’s unit or section in the company structure. Used when no direct manager is assigned.',
            self::Department => 'Head of the employee’s department (or parent department). Used when no direct manager is assigned.',
            self::Division => 'Head of the employee’s division. Used when no direct manager is assigned.',
            self::Hr => 'Always last. Final approval by Human Resources.',
        };
    }

    public function isStructureHead(): bool
    {
        return in_array($this, [self::UnitSection, self::Department, self::Division], true);
    }

    public function structureGroupCode(): ?StructureGroupCode
    {
        return match ($this) {
            self::UnitSection => StructureGroupCode::UnitSection,
            self::Department => StructureGroupCode::Department,
            self::Division => StructureGroupCode::Division,
            default => null,
        };
    }

    /**
     * Configurable structure steps before HR, in default order.
     *
     * @return list<self>
     */
    public static function configurableKeys(): array
    {
        return [
            self::DirectManager,
            self::UnitSection,
            self::Department,
            self::Division,
        ];
    }
}
