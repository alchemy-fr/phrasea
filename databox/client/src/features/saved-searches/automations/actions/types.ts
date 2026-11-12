import type {FC, ReactNode} from 'react';
import type {TFunction} from 'i18next';
import type {AutomationActionConfig, AutomationScope} from '@/types/api';

export type ActionFormProps = {
    value: AutomationActionConfig;
    onChange: (value: AutomationActionConfig) => void;
    /** Messages indexed by configuration key */
    errors?: Record<string, string>;
    /** Scope of the automation, some options depend on it */
    scope?: AutomationScope;
    /** Workspace the results of the automation are limited to */
    workspaceId?: string;
};

/** What the actions may know about their automation */
export type ActionContext = {
    workspaceId?: string;
};

/**
 * Client side of an automation action type (see AutomationActionInterface in the API).
 */
export type ActionDefinition = {
    label: (t: TFunction) => string;
    icon: ReactNode;
    Form: FC<ActionFormProps>;
    summary: (config: AutomationActionConfig, t: TFunction) => string;
    /** Configuration of a new action */
    defaultConfig: () => AutomationActionConfig;
    /** Whether the configuration can be submitted (the API validates it anyway) */
    isComplete: (
        config: AutomationActionConfig,
        context?: ActionContext
    ) => boolean;
    /** The configuration once the workspace of the automation changed */
    onWorkspaceChange?: (
        config: AutomationActionConfig
    ) => AutomationActionConfig;
};
