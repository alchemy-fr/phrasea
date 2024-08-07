type Props = {
    x: number;
    y: number;
    s?: number;
    c?: string;
};

export default function PointAnnotation({x, y, s = 30, c = '#000'}: Props) {
    return (
        <div
            data-type={'point'}
            style={{
                position: 'absolute',
                top: `${y * 100}%`,
                left: `${x * 100}%`,
                width: s,
                height: s,
                borderRadius: '50%',
                transform: `translateX(-50%) translateY(-50%)`,
                backgroundColor: c,
            }}
        />
    );
}
