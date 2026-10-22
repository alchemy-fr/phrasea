import type {AttributeEntity} from '@/types/api';

/** A row added inline, not saved yet: only its value can be typed */
export const DRAFT_PREFIX = 'draft-';

export function isDraft(entity: AttributeEntity): boolean {
    return entity.id.startsWith(DRAFT_PREFIX);
}
