export type StructureHeadOption = {
    id: number;
    name: string;
    staff_id: string;
};

export type StructureGrade = {
    id: number;
    structure_level_id: number;
    grade: string;
    title: string;
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

export type StructureNode = {
    id: number;
    structure_group_id: number;
    parent_id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    head_employee_id: number | null;
    head_employee?: StructureHeadOption | null;
    is_active: boolean;
    sort_order: number;
    levels: StructureLevel[];
    children: StructureNode[];
};

export type StructureGroup = {
    id: number;
    code: string;
    name: string;
    sort_order: number;
    allows_nodes: boolean;
    levels: StructureLevel[];
    nodes: StructureNode[];
};
