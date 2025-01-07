import {PointAnnotation as TPointAnnotation} from "./annotationTypes.ts";

type Props = {} & TPointAnnotation;

export default function PointAnnotation({x, y, s = 15, c = '#000'}: Props) {
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
