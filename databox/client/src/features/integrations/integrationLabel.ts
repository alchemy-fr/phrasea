import type {WorkspaceIntegration} from '@/types/api';

/**
 * The name given to the integration, or its type when it has none.
 */
export function integrationLabel(
    i: Pick<WorkspaceIntegration, 'name' | 'integrationName' | 'integration'>
): string {
    return i.name || i.integrationName || i.integration;
}
