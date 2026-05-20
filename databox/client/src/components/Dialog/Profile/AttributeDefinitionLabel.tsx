import {AttributeDefinition} from '../../../types.ts';
import * as React from 'react';

type Props = {data: AttributeDefinition};

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
