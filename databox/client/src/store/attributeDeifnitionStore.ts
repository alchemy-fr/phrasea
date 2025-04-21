import {create} from 'zustand';
import {AttributeDefinition} from '../types';
import {getAttributeDefinitions} from '../api/attributes.ts';

type State = {
    definitions: AttributeDefinition[];
    loaded: boolean;
    loading: boolean;
    load: (force?: boolean) => Promise<void>;
};

export const useAttributeDefinitionStore = create<State>((set, getState) => ({
    loaded: false,
    loading: false,
    definitions: [],

    load: async force => {
        const state = getState();
        if (state.loaded && !force) {
            return;
        }

        if (state.loading) {
            return;
        }

        set({
            loading: true,
        });

        try {
            const data = await getAttributeDefinitions();

            set({
                definitions: data,
                loading: false,
                loaded: true,
            });
        } finally {
            set({loading: false});
        }
    },
}));
