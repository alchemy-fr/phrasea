import {Dimensions} from '@alchemy/core';
import {RenditionStructure} from './RenditionStructure.tsx';
import {Skeleton} from '@mui/material';

export function RenditionSkeleton({dimensions}: {dimensions: Dimensions}) {
    return (
        <RenditionStructure
            title={<Skeleton variant={'text'} />}
            info={<Skeleton variant={'text'} width={'50%'} />}
            dimensions={dimensions}
            media={<Skeleton {...dimensions} variant={'rectangular'} />}
            actions={
                <Skeleton width={150} height={60} variant={'rectangular'} />
            }
        />
    );
}
