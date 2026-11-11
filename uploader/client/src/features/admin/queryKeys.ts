import {targetsQueryKey} from '@/features/targets/TargetListScreen';

/** Under the key of the targets: refreshing them refreshes these too */
export const targetFormQueryKey = (targetId: string) =>
    [...targetsQueryKey, targetId, 'form'] as const;

export const targetDataQueryKey = (targetId: string) =>
    [...targetsQueryKey, targetId, 'data'] as const;
