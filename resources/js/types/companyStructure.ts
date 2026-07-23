export type StructureHeadGradeOption = {
    id: number;
    label: string;
    source: 'current' | 'parent';
    source_label: string;
};

export type StructureHead = {
    id: number;
    name: string;
    staff_id: string;
    grade_id: number;
    grade_label: string;
};

export type StructureGrade = {
    id: number;
    structure_level_id: number;
    grade: string;
    title: string;
    requirements?: string | null;
    job_description?: string | null;
    label: string;
    sort_order: number;
    is_active: boolean;
};

export type StructureLevel = {
    id: number;
    structure_group_id: number | null;
    structure_node_id: number | null;
    level_number: number;
    reference_title: string;
    sort_order: number;
    grades: StructureGrade[];
};

export type StructureGroupOption = {
    id: number;
    code: string;
    name: string;
};

export type StructureNode = {
    id: number;
    structure_group_id: number;
    group_code?: string | null;
    group_name?: string | null;
    parent_id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    head_grade_ids: number[];
    head_grades: { id: number; label: string }[];
    heads?: StructureHead[];
    is_active: boolean;
    sort_order: number;
    allowed_child_codes?: string[];
    levels: StructureLevel[];
    children: StructureNode[];
};

export type StructureGroup = {
    id: number;
    code: string;
    name: string;
    sort_order: number;
    allows_nodes: boolean;
    is_org_tree?: boolean;
    levels: StructureLevel[];
    nodes: StructureNode[];
    group_options?: StructureGroupOption[];
};
