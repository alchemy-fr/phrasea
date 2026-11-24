export type TaskView =
    | {type: 'list'}
    | {type: 'catalog'}
    | {type: 'run'; taskType: string}
    | {type: 'details'; id: string}
    | {type: 'not_found'};

/**
 * View of the operation tasks screen from the segments following
 * `/admin/tasks` (see `routes.operationTask*`).
 */
export function parseTaskPath(segments: string[] | undefined): TaskView {
    const [first, second, ...rest] = segments ?? [];

    if (rest.length > 0) {
        return {type: 'not_found'};
    }
    if (!first) {
        return {type: 'list'};
    }
    if (first === 'new') {
        return second ? {type: 'run', taskType: second} : {type: 'catalog'};
    }

    return second ? {type: 'not_found'} : {type: 'details', id: first};
}
