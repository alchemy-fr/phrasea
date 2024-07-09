type Props = {
    x: number;
    y: number;
    r: number;
    b?: number;
    c?: string;
    f?: string;
};

export default function CircleAnnotation({
    x,
    y,
    r,
    b = 3,
    c = '#000',
    f,
}: Props) {
    return (
        <div
            data-type={'circle'}
            style={{
                position: 'absolute',
                top: `${y * 100}%`,
                left: `${x * 100}%`,
                width: `${r * 100}%`,
                aspectRatio: `1 / 1`,
                borderRadius: '50%',
                transform: `translateX(-50%) translateY(-50%)`,
                backgroundColor: f,
                border: `${b}px solid ${c}`,
            }}
        />
    );
}
