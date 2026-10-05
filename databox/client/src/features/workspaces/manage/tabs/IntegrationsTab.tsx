'use client';

import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {IntegrationManager} from '@/features/integrations/manage/IntegrationManager';

export function IntegrationsTab({workspace}: WorkspaceTabProps) {
    return <IntegrationManager workspaceId={workspace.id} />;
}
