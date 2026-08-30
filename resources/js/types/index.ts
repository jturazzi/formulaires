import type { LucideIcon } from '@lucide/vue';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    [key: string]: unknown;
    name: string;
    auth: Auth;
    locale: string;
    features: {
        sso: boolean;
        registration: boolean;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'creator';
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type FieldType = 'text' | 'textarea' | 'email' | 'number' | 'date' | 'choice' | 'checkboxes' | 'dropdown' | 'file' | 'info';

export interface FieldOptions {
    choices?: string[];
    max_length?: number;
    multiple?: boolean;
    min?: number;
    max?: number;
    min_date?: string;
    max_date?: string;
    allow_other?: boolean;
}

export type VisibilityOperator = 'equals' | 'not_equals' | 'contains' | 'not_contains' | 'empty' | 'not_empty' | 'greater_than' | 'less_than';

export interface VisibilityCondition {
    field_id: number;
    operator: VisibilityOperator;
    value: string | null;
}

export interface FieldVisibility {
    mode: 'visible_if' | 'hidden_if';
    logic: 'all' | 'any';
    conditions: VisibilityCondition[];
}

export interface FormFieldData {
    id: number | null;
    type: FieldType;
    label: string;
    description: string | null;
    required: boolean;
    options: FieldOptions | null;
    visibility: FieldVisibility | null;
}

export interface FormSectionData {
    id: number | null;
    title: string | null;
    description: string | null;
    fields: FormFieldData[];
}

export type FormStatus = 'draft' | 'published' | 'closed';

export interface FormShareData {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
    };
}

export interface FormData {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    logo_url: string | null;
    primary_color: string | null;
    status: FormStatus;
    require_email_verification: boolean;
    notify_on_response: boolean;
    notification_emails: string[];
    max_responses: number | null;
    expires_at: string | null;
    retention_days: number | null;
    success_message: string | null;
    public_url: string;
    responses_count: number;
    is_owner: boolean;
    is_shared_with_me: boolean;
    can_manage_shares: boolean;
    can_transfer_ownership: boolean;
    owner: { name: string; email: string };
    shares: FormShareData[] | null;
    sections: FormSectionData[];
}

export type BreadcrumbItemType = BreadcrumbItem;
