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

    public function templateColumn(): string
    {
        return match ($this) {
            self::Leave => 'leave_approval_template_id',
            self::Overtime => 'overtime_approval_template_id',
        };
    }
}
