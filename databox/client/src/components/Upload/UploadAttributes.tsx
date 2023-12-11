import {FormLabel, Skeleton} from '@mui/material';
import {useAttributeEditor} from '../Media/Asset/Attribute/useAttributeEditor';
import AttributesEditor from '../Media/Asset/Attribute/AttributesEditor';
import FormRow from '../Form/FormRow';
import React from 'react';

type Props = {
    usedAttributeEditor: ReturnType<typeof useAttributeEditor>;
};

export default function UploadAttributes({usedAttributeEditor}: Props) {
    const {attributes, definitionIndex, onChangeHandler} = usedAttributeEditor;

    return (
        <>
            {attributes && definitionIndex ? (
                <AttributesEditor
                    attributes={attributes}
                    definitions={definitionIndex}
                    disabled={false}
                    onChangeHandler={onChangeHandler}
                />
            ) : (
                <>
                    {[0, 1, 2].map(x => (
                        <React.Fragment key={x}>
                            <FormRow>
                                <FormLabel>
                                    <Skeleton
                                        width={'200'}
                                        variant={'text'}
                                        style={{
                                            display: 'inline-block',
                                            width: '200px',
                                        }}
                                    />
                                </FormLabel>
                                <Skeleton
                                    width={'100%'}
                                    height={56}
                                    variant={'rectangular'}
                                    sx={{
                                        mb: 2,
                                    }}
                                />
                            </FormRow>
                        </React.Fragment>
                    ))}
                </>
            )}
        </>
    );
}
