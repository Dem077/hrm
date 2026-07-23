<?php

namespace App\Enums;

enum ApprovalWorkflowKind: string
{
    case Leave = 'leave';
    case Overtime = 'overtime';

    public function label(): string
    {
        return match ($this) {
            self::Leave => 'Leave',
            self::Overtime => 'Overtime',
        };
    }

    public function column(): string
    {
        return match ($this) {
            self::Leave => 'leave_approval_workflow',
            self::Overtime => 'overtime_approval_workflow',
        };
    }
}
