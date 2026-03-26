import React, {PropsWithChildren, useState} from 'react';
import {Box} from '@mui/material';

type Props = PropsWithChildren<{
    backgroundColor?: string;
    itemsCount: number;
    height: number;
    delay?: number;
}>;

export default function CarouselStructure({
    backgroundColor,
    itemsCount,
    height,
    children,
    delay,
}: Props) {
    const [current, setCurrent] = useState(0);
    const intervalRef = React.useRef<ReturnType<typeof setInterval>>();
    const [manualInc, setManualInc] = useState(0);

    const onCurrentChange = (newCurrent: number) => {
        setCurrent(newCurrent);
        setManualInc(prev => prev + 1);
    };

    React.useEffect(() => {
        setCurrent(0);
    }, [itemsCount]);

    React.useEffect(() => {
        if (delay && itemsCount > 1) {
            intervalRef.current = setInterval(() => {
                setCurrent(prev => (prev + 1) % itemsCount);
            }, delay);

            return () => clearInterval(intervalRef.current);
        }
    }, [delay, intervalRef, manualInc, itemsCount]);

    return (
        <>
            <Box
                sx={{
                    backgroundColor,
                    overflow: 'hidden',
                    width: '100%',
                    height,
                    position: 'relative',
                    img: {
                        maxHeight: height,
                    },
                }}
            >
                {itemsCount > 1 && (
                    <Box
                        sx={theme => ({
                            position: 'absolute',
                            zIndex: 1,
                            left: 0,
                            right: 0,
                            bottom: theme.spacing(1),
                            width: '100%',
                            display: 'flex',
                            flexDirection: 'row',
                            justifyContent: 'center',
                        })}
                    >
                        {Array(itemsCount)
                            .fill(0)
                            .map((_, i) => {
                                const isCurrent = i === current;
                                return (
                                    <div
                                        key={i}
                                        onClick={() => {
                                            onCurrentChange(i);
                                        }}
                                        style={{
                                            cursor: 'pointer',
                                            width: 10,
                                            height: 10,
                                            borderRadius: '50%',
                                            backgroundColor: isCurrent
                                                ? 'rgba(0, 0, 0, 0.5)'
                                                : 'rgba(255, 255, 255, 0.8)',
                                            border: isCurrent
                                                ? '1px solid rgba(255, 255, 255, 0.8)'
                                                : '1px solid rgba(0, 0, 0, 0.8)',
                                            margin: '0 5px',
                                            display: 'inline-block',
                                        }}
                                    />
                                );
                            })}
                    </Box>
                )}
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'row',
                        height,
                        width: `${itemsCount * 100}%`,
                        transition: 'transform 0.3s ease',
                        transform: `translateX(-${current * (100 / itemsCount)}%)`,
                    }}
                >
                    {children}
                </div>
            </Box>
        </>
    );
}
