import React from 'react';
import {Chip} from '@mui/material';
import {AttributeFormatterProps} from './types';
import TextType from './TextType';
import AssetTypeIcon from '../../AssetTypeIcon.tsx';

export default class FileTypeType extends TextType {
    public isRich = true;

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        if (!value) {
            return null;
        }
        const mimeType = value.toString();

        return (
            <Chip
                size="small"
                title={mimeType}
                icon={<AssetTypeIcon mimeType={mimeType} fontSize="small" />}
            />
        );
    }
}
