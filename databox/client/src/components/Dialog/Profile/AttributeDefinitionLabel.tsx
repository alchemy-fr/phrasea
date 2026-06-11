import {BaseAttributeDefinition} from '../../../types.ts';
import * as React from 'react';

type Props = {data: BaseAttributeDefinition};

export default function AttributeDefinitionLabel({data}: Props) {
    return (
        <>
            {data.builtIn ? (
                <strong>{data.displayName ?? data.name}</strong>
            ) : (
                (data.displayName ?? data.name)
            )}
        </>
    );
}
