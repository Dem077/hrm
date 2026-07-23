<?php

use App\Enums\StructureGroupCode;

it('allows unit/section at the organization root and under division', function () {
    expect(StructureGroupCode::UnitSection->allowsTopLevel())->toBeTrue()
        ->and(StructureGroupCode::UnitSection->requiresParent())->toBeFalse()
        ->and(StructureGroupCode::UnitSection->allowedParentCodes())->toBe([
            StructureGroupCode::Division,
            StructureGroupCode::Department,
            StructureGroupCode::UnitSection,
        ])
        ->and(StructureGroupCode::Division->allowedChildCodes())->toBe([
            StructureGroupCode::Department,
            StructureGroupCode::UnitSection,
        ]);
});

it('still requires departments to have a parent', function () {
    expect(StructureGroupCode::Department->allowsTopLevel())->toBeFalse()
        ->and(StructureGroupCode::Department->requiresParent())->toBeTrue()
        ->and(StructureGroupCode::Division->allowsTopLevel())->toBeTrue()
        ->and(StructureGroupCode::Division->requiresParent())->toBeFalse();
});
