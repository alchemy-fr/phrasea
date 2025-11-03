import {useEffect, useState} from 'react';
import {fileToDataUri} from '../../api/file.ts';
import Thumb from '../Media/Asset/Thumb.tsx';

type Props = {
    file: File;
    size: number;
};

export function FileBlobThumb({file, size}: Props) {
    const [dataUri, setDataUri] = useState<string>();

    useEffect(() => {
        fileToDataUri(file).then(setDataUri);
    }, [file]);

    return (
        <Thumb size={size}>
            {dataUri && (
                <img
                    style={{
                        objectFit: 'contain',
                    }}
                    src={dataUri}
                    alt={file.name}
                />
            )}
        </Thumb>
    );
}
