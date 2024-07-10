import React from "react";
import {AttributeDefinition, StateSetter} from "../../types.ts";

type Props = {
    attributeDefinitions: AttributeDefinition[];
    setDefinition: StateSetter<AttributeDefinition | undefined>;
}

export function useTabShortcut({
    attributeDefinitions,
    setDefinition,
}: Props) {
    React.useEffect(() => {
        const onTab = (e: KeyboardEvent) => {
            if (e.key === 'Tab') {
                e.preventDefault();

                setDefinition(p => {
                    if (p) {
                        const index = attributeDefinitions.findIndex(ad => ad.id === p?.id);

                        if (index >= 0) {
                            return attributeDefinitions[(attributeDefinitions.length + index + (e.shiftKey ? -1 : 1)) % attributeDefinitions.length];
                        }
                    }

                    return attributeDefinitions[0];
                });
            }
        };

        window.addEventListener('keydown', onTab);

        return () => {
            window.removeEventListener('keydown', onTab);
        }
    }, [attributeDefinitions]);
}
