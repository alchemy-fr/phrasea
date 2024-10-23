import {CircularProgress, Typography} from '@mui/material';
import {TreeItem} from '@mui/x-tree-view';
import React from 'react';
import {useTranslation} from 'react-i18next';

type Props = {};

function TreeItemLoader({}: Props) {
    const {t} = useTranslation();
    return (
        <TreeItem
            disabled={true}
            nodeId={'__loading'}
            label={
                <Typography
                    variant={'body1'}
                    sx={{
                        p: 1,
                    }}
                >
                    <CircularProgress
                        size={15}
                        sx={{
                            mr: 2,
                        }}
                    />
                    {t('common.loading', 'Loading…')}
                </Typography>
            }
        />
    );
}

export default React.memo(TreeItemLoader, () => true);
