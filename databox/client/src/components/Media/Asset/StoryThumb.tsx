import React, {useCallback} from 'react';
import assetClasses from '../../AssetList/classes';
import {SxProps} from '@mui/material';
import {Theme} from '@mui/material/styles';
import {getStoryThumbnails} from '../../../api/asset.ts';

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
            const storyBox = e.currentTarget.getBoundingClientRect();
            const x =
                ((e.clientX - storyBox.left) / storyBox.width) *
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
            className={assetClasses.storyThumb}
        >
            <div>
                {thumbnails
                    ? thumbnails.map((thumb, index) => (
                          <img
                              key={index}
                              src={thumb}
                              alt={`Story thumbnail ${index + 1}`}
                          />
                      ))
                    : null}
            </div>
        </div>
    );
}

export function createStoryStyle(thumbSize: number, _theme: Theme): SxProps {
    return {
        [`.${assetClasses.storyThumb}`]: {
            'display': 'none !important',
            'flexDirection': 'column',
            'backgroundColor': '#000',
            'height': '100%',
            'width': thumbSize,
            'overflow': 'hidden',
            'justifyContent': 'center',
            'alignItems': 'center',
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

        [`.${assetClasses.thumbWrapper}:hover .${assetClasses.storyThumb}`]: {
            display: 'flex !important',
        },

        [`.${assetClasses.thumbWrapper}:hover .${assetClasses.storyShouldHide}`]:
            {
                display: 'none',
            },
    };
}
