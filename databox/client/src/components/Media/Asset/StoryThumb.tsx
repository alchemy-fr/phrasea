import React, {useCallback} from 'react';
import assetClasses from '../../AssetList/classes';
import {Skeleton, SxProps} from '@mui/material';
import {Theme} from '@mui/material/styles';
import {getStoryThumbnails} from '../../../api/asset.ts';
import classNames from 'classnames';

type Props = {
    assetId: string;
};

export default function StoryThumb({assetId}: Props) {
    const [thumbnails, setThumbnails] = React.useState<string[] | undefined>();
    const divRef = React.useRef<HTMLDivElement | null>(null);
    const initRef = React.useRef<boolean>(false);

    const onMouseOver = useCallback(() => {
        if (initRef.current || thumbnails) {
            return;
        }
        initRef.current = true;
        getStoryThumbnails(assetId).then(r => {
            setThumbnails(r);
            initRef.current = false;
        });
    }, [assetId, initRef, divRef]);

    const onMouseMove = useCallback(
        (e: React.MouseEvent) => {
            if (!divRef.current) {
                return;
            }
            const sideOffset = 10;
            const storyBox = e.currentTarget.getBoundingClientRect();
            const x =
                (Math.max(0, e.clientX - sideOffset - storyBox.left) /
                    (storyBox.width - 2 * sideOffset)) *
                (divRef.current.scrollWidth - storyBox.width);

            divRef.current?.scroll({left: x});
        },
        [divRef]
    );

    return (
        <div
            ref={divRef}
            onMouseOver={onMouseOver}
            onMouseMove={onMouseMove}
            className={classNames({
                [assetClasses.storyThumb]: true,
                [assetClasses.storyThumbsLoaded]: !!thumbnails,
            })}
        >
            <div>
                <div>
                    {thumbnails ? (
                        thumbnails.map((thumb, index) => (
                            <img
                                key={index}
                                src={thumb}
                                alt={`Story thumbnail ${index + 1}`}
                            />
                        ))
                    ) : (
                        <Skeleton variant={'rectangular'} />
                    )}
                </div>
            </div>
        </div>
    );
}

export function createStorySx(thumbSize: number, theme: Theme): SxProps {
    return {
        [`.${assetClasses.thumbWrapper} .${assetClasses.storyThumb}`]: {
            'display': 'flex',
            'flexDirection': 'column',
            'backgroundColor': theme.palette.background.paper,
            'width': thumbSize,
            'height': 0,
            'overflow': 'hidden',
            'justifyContent': 'center',
            'alignItems': 'center',
            'opacity': 0,
            'transition': 'opacity 1s ease-in-out',
            '> div': {
                '> div': {
                    'display': 'flex',
                    'flexDirection': 'row',
                    'alignItems': 'center',
                    'flexShrink': 0,
                    '> img': {
                        flexShrink: 0,
                        display: 'block',
                    },
                },
            },
        },
        [`.${assetClasses.thumbWrapper} .${assetClasses.storyThumbsLoaded}`]: {
            backgroundColor: '#000',
        },
        [`.${assetClasses.thumbWrapper} .MuiSkeleton-root`]: {
            width: thumbSize,
            height: thumbSize,
        },
        [`.${assetClasses.thumbWrapper}:hover .${assetClasses.storyThumb}`]: {
            opacity: '1',
            height: '100%',
        },

        [`.${assetClasses.thumbWrapper}:hover .${assetClasses.storyShouldHide}`]:
            {
                display: 'none',
            },
    };
}
