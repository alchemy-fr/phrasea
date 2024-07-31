import React, {PropsWithChildren} from 'react';

type Props = PropsWithChildren<{
    defaultWidth: number;
    minWidth?: number;
    maxWidth?: number;
}> &
    React.HTMLAttributes<HTMLDivElement>;

export default function Resizable({
    children,
    defaultWidth,
    minWidth,
    maxWidth,
    style,
    ...props
}: Props) {
    const ref = React.useRef<HTMLDivElement | null>(null);
    const width = React.useRef<number>(defaultWidth);

    React.useEffect(() => {
        const onMouseDown = (e: MouseEvent) => {
            const t = e.currentTarget as HTMLDivElement;
            const x = e.clientX;
            const w = t.clientWidth;

            const onMouseMove = (e: MouseEvent) => {
                width.current = w + x - e.clientX;
                t.style.width = `${width.current}px`;
            };

            const onMouseUp = (_e: MouseEvent) => {
                ref.current?.removeEventListener('mousemove', onMouseMove);
                ref.current?.removeEventListener('mouseup', onMouseUp);
            };

            t.addEventListener('mousemove', onMouseMove);
            window.document.addEventListener('mouseup', onMouseUp);
        };

        ref.current!.addEventListener('mousedown', onMouseDown);

        return () => {
            ref.current?.removeEventListener('mousedown', onMouseDown);
            ref.current?.removeEventListener('mousedown', onMouseDown);
        };
    }, [width, ref]);

    return (
        <div
            {...props}
            ref={ref}
            style={{
                ...style,
                width: width.current,
                height: 'auto',
            }}
        >
            {children}
        </div>
    );
}
