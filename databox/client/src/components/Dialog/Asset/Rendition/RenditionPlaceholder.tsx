import React from 'react';
import {RenditionDefinition} from '../../../../types.ts';
import {Dimensions} from '../../../Media/Asset/Players';
import {Button} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {RenditionStructure} from './RenditionStructure.tsx';
import UploadIcon from '@mui/icons-material/Upload';
import DoNotDisturbIcon from '@mui/icons-material/DoNotDisturb';

type Props = {
    definition: RenditionDefinition;
    onUpload: (definition: RenditionDefinition) => void;
    dimensions: Dimensions;
};

export function RenditionPlaceholder({
    dimensions,
    definition,
    onUpload,
}: Props) {
    const {t} = useTranslation();
    const {nameTranslated} = definition;

    const uploadRendition = () => {
        onUpload(definition);
    };

    return (
        <RenditionStructure
            title={nameTranslated}
            dimensions={dimensions}
            media={
                <div>
                    <DoNotDisturbIcon fontSize={'large'} />
                </div>
            }
            info={<></>}
            actions={
                definition.substitutable && (
                    <Button
                        variant={'contained'}
                        startIcon={<UploadIcon />}
                        onClick={uploadRendition}
                    >
                        {t('renditions.upload', 'Upload rendition')}
                    </Button>
                )
            }
        />
    );
}
