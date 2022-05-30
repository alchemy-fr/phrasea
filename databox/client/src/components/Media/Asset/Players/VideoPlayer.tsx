import React, {MouseEvent, useContext, useState} from 'react';
import {PlayerProps} from "./index";
import ReactPlayer from "react-player";
import {Box, IconButton, LinearProgress} from "@mui/material";
import {DisplayContext} from "../../DisplayContext";
import PlayCircleIcon from '@mui/icons-material/PlayCircle';
import PauseIcon from '@mui/icons-material/Pause';
import {getSizeCase} from "../../../../lib/sizeCase";
import {FileTypeEnum, getFileTypeFromMIMEType} from "../../../../lib/file";
import {stopPropagation} from "../../../../lib/stdFuncs";

type Props = {
    autoPlayable: boolean;
} & PlayerProps;

type Progress = {
    played: number;
    loaded: number;
}

const playerActionsClass = 'pa';

export default function VideoPlayer({
                                        file,
                                        thumbSize,
                                        onLoad,
                                        autoPlayable,
                                        noInteraction,
                                    }: Props) {
    const [progress, setProgress] = useState<Progress>();
    const [duration, setDuration] = useState<number>();
    const {playVideos, setPlaying} = useContext(DisplayContext)!;
    const [play, setPlay] = useState(false);
    const type = getFileTypeFromMIMEType(file.type);
    const isAudio = type === FileTypeEnum.Audio;

    const autoPlay = autoPlayable && playVideos;

    const onPlay = (e: MouseEvent) => {
        e.stopPropagation();
        setPlay(p => {
            setPlaying({
                stop: !p ? () => setPlay(false) : () => {},
            });

            return !p;
        });
    };

    const PlayComponent = play ? PauseIcon : PlayCircleIcon;

    return <Box sx={theme => ({
        width: thumbSize,
        height: thumbSize,
        position: 'relative',
        backgroundColor: isAudio ? '#FFF' : '#000',
        display: 'flex',
        alignItems: 'center',
        [`.${playerActionsClass}`]: {
            pointerEvents: 'none',
            display: 'flex',
            flexDirection: 'column',
            justifyContent: 'center',
            alignItems: 'center',
            position: 'absolute',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            zIndex: 1,
            '.MuiButtonBase-root': {
                transition: theme.transitions.create(['opacity'], {duration: 100}),
                pointerEvents: 'auto',
                opacity: play ? 0 : undefined,
                bgcolor: 'primary.contrastText',
                '&:hover': {
                    bgcolor: 'primary.contrastText',
                }
            }
        },
        '&:hover': {
            '.MuiButtonBase-root': {
                opacity: 1,
            }
        }
    })}
    >
        {!autoPlay && !noInteraction && <div className={playerActionsClass}>
            <IconButton
                onClick={onPlay}
                onMouseDown={stopPropagation}
                color={'primary'}
            >
                <PlayComponent
                    fontSize={getSizeCase(typeof thumbSize === 'number' ? thumbSize : 9999, {
                        0: 'small',
                        100: 'medium',
                        250: 'large',
                    })}
                />
            </IconButton>
        </div>}
        <ReactPlayer
            url={file.url}
            playing={play || (!isAudio && autoPlay)}
            loop={true}
            onReady={(player) => {
                onLoad && onLoad();
                setDuration(player.getDuration());
            }}
            onProgress={({played, loaded}) => {
                setProgress({
                    played,
                    loaded,
                });
            }}
            progressInterval={duration ? (duration < 60 ? 100 : 1000) : 5}
            width={thumbSize}
            height={thumbSize}
            muted={autoPlay}
        />
        {progress && <LinearProgress
            variant={progress ? 'buffer' : 'indeterminate'}
            style={{
                position: 'absolute',
                bottom: 0,
                left: 0,
                right: 0,
                zIndex: 1,
            }}
            value={progress ? progress.played * 100 : undefined}
            valueBuffer={progress ? progress.loaded * 100 : undefined}
        />}
    </Box>
}
