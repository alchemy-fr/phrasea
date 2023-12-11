import {useEffect, useState} from 'react';

type WindowSize = {
    innerWidth: number;
    innerHeight: number;
};

function getSize(): WindowSize {
    return {
        innerWidth: window.innerWidth,
        innerHeight: window.innerHeight,
    };
}

export function useWindowSize(): WindowSize {
    const [size, setSize] = useState(getSize());

    useEffect(() => {
        const r = () => {
            setSize(getSize());
        };
        window.addEventListener('resize', r);

        return () => {
            window.removeEventListener('resize', r);
        };
    });

    return size;
}
