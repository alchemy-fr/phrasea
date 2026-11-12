'use client';

import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {SecretManager} from '@/features/secrets/SecretManager';

export function SecretsTab({workspace}: WorkspaceTabProps) {
    return <SecretManager workspaceId={workspace.id} />;
}
