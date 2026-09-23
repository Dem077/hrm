export type ReportTemplateCreator = {
    id: number;
    name: string;
    email: string;
};

export type ReportFieldConfig = {
    field: string;
    heading: string;
    filter_type: string;
    filter_value: string;
    filter_value_from: string;
    filter_value_to: string;
    filter_values: string[];
};

export type ReportDefinition = {
    model_type: string;
    field_configs: ReportFieldConfig[];
    from_date: string | null;
    to_date: string | null;
};

export type ReportCatalogFieldOption = {
    value: string;
    label: string;
};

export type ReportCatalogField = {
    value: string;
    label: string;
    input: 'text' | 'status';
    options: ReportCatalogFieldOption[];
    filter_types: ReportCatalogFieldOption[];
    is_relation: boolean;
    kind: string;
};

export type ReportCatalogModel = {
    value: string;
    label: string;
    fields: ReportCatalogField[];
};

export type ReportCatalog = {
    models: ReportCatalogModel[];
    filter_types: ReportCatalogFieldOption[];
};

export type ReportTemplate = {
    id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    is_global: boolean;
    is_active: boolean;
    sort_order: number;
    definition?: ReportDefinition | Record<string, unknown>;
    created_by_user_id?: number | null;
    created_by?: ReportTemplateCreator | null;
    created_at?: string | null;
    updated_at?: string | null;
};
