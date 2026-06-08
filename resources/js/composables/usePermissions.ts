import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { Auth } from '@/types/auth';

export function usePermissions() {
    const page = usePage<{ auth: Auth }>();

    const permissions = computed(() => new Set(page.props.auth.permissions ?? []));
    const roles = computed(() => page.props.auth.roles ?? []);

    function can(permission: string): boolean {
        return permissions.value.has(permission);
    }

    function canAny(required: string[]): boolean {
        return required.some((permission) => can(permission));
    }

    function hasRole(role: string): boolean {
        return roles.value.includes(role);
    }

    const primaryRole = computed(() => roles.value[0] ?? 'No role assigned');

    return {
        can,
        canAny,
        hasRole,
        roles,
        permissions,
        primaryRole,
    };
}
