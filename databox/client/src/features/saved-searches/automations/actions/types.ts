import type {FC, ReactNode} from 'react';
import type {TFunction} from 'i18next';
import type {AutomationActionConfig} from '@/types/api';

export type ActionFormProps = {
    value: AutomationActionConfig;
    onChange: (value: AutomationActionConfig) => void;
    /** Messages indexed by configuration key */
    errors?: Record<string, string>;
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
    isComplete: (config: AutomationActionConfig) => boolean;
};
