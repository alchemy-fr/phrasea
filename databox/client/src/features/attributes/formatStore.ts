import {create} from 'zustand';
import {useCallback} from 'react';

type State = {
    byType: Record<string, string>;
    byDefinition: Record<string, string>;
    setTypeFormat: (type: string, format: string) => void;
    setDefinitionFormat: (definitionId: string, format: string) => void;
};

/**
 * Session-scoped display format choices (per attribute type, overridable per
 * definition). Changing a format from the "eye" button rotates through the
 * type's available formats.
 */
export const useAttributeFormatStore = create<State>(set => ({
    byType: {},
    byDefinition: {},
    setTypeFormat: (type, format) =>
        set(s => ({byType: {...s.byType, [type]: format}})),
    setDefinitionFormat: (definitionId, format) =>
        set(s => ({byDefinition: {...s.byDefinition, [definitionId]: format}})),
}));

export function useAttributeFormats() {
    const byType = useAttributeFormatStore(s => s.byType);
    const byDefinition = useAttributeFormatStore(s => s.byDefinition);
    const setTypeFormat = useAttributeFormatStore(s => s.setTypeFormat);
    const setDefinitionFormat = useAttributeFormatStore(
        s => s.setDefinitionFormat
    );

    const getFormat = useCallback(
        (type: string, definitionId?: string): string | undefined =>
            (definitionId ? byDefinition[definitionId] : undefined) ??
            byType[type],
        [byType, byDefinition]
    );

    return {getFormat, setTypeFormat, setDefinitionFormat};
}
