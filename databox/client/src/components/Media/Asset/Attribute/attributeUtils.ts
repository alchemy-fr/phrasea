import {AttributeDefinition, EntityList, Workspace} from '../../../../types';
import {AttributeWidgetOptions} from './types/types';

export function createWidgetOptionsFromDefinition(
    definition: AttributeDefinition
): AttributeWidgetOptions {
    return {
        list: definition.entityList as EntityList | undefined,
        workspaceId: (definition.workspace as Workspace).id,
    };
}
