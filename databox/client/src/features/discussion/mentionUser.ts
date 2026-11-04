import {
    ResolvedEntity,
    useEntitiesStore,
    useEntity,
} from '@/features/search/entitiesStore';
import {getUserDisplayName, UserLike} from '@/lib/users';
import type {User} from '@/types/api';

export function userIri(id: string): string {
    return `/users/${id}`;
}

/**
 * Keeps the users we already hold (message authors, picked suggestions) so
 * that their mentions are labelled without resolving them again.
 */
export function rememberUsers(users: (User | null | undefined)[]): void {
    const {index, storeMany} = useEntitiesStore.getState();
    const entities: Record<string, ResolvedEntity> = {};
    users.forEach(u => {
        if (u && !(userIri(u.id) in index)) {
            entities[userIri(u.id)] = {...u};
        }
    });
    if (Object.keys(entities).length > 0) {
        storeMany(entities);
    }
}

function isUser(entry: unknown): entry is UserLike {
    return (
        !!entry &&
        typeof entry === 'object' &&
        typeof (entry as UserLike).username === 'string'
    );
}

/**
 * Text of a mention: the bare display name when the user has one, else the
 * username, which is always prefixed with `@` to tell it apart from a name.
 */
export function mentionText(
    user: UserLike | undefined,
    username: string
): string {
    const name = user ? getUserDisplayName(user) : username;

    return name === (user?.username ?? username) ? `@${name}` : name;
}

/**
 * Label of a `@[username](id)` mention, from the user when it is known
 * (resolved or remembered).
 */
export function mentionLabel(id: string, username: string): string {
    const entry = useEntitiesStore.getState().index[userIri(id)];

    return mentionText(isUser(entry) ? entry : undefined, username);
}

/** Reactive variant of `mentionLabel`, resolving the user if needed. */
export function useMentionLabel(id: string, username: string): string {
    const entry = useEntity(userIri(id));

    return mentionText(isUser(entry) ? entry : undefined, username);
}
