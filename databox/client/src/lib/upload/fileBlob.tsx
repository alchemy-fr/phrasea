import {useEffect, useState} from "react";

export function fileToDataUri(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (event) => {
            resolve(event.target!.result as string);
        };
        reader.readAsDataURL(file);
    });
}

type Props = {
    file: File;
    width: number | string;
    height?: number | string;
}

export function FileBlobThumb({
                                  file,
                                  width,
                                  height
                              }: Props) {

    const [dataUri, setDataUri] = useState<string>();

    useEffect(() => {
        fileToDataUri(file).then(setDataUri);
    }, []);

    height = height === undefined ? width : height;

    if (!dataUri) {
        return <div
            style={{
                backgroundColor: '#CCC',
                width,
                height,
            }}
        />
    }

    return <img
        style={{
            backgroundColor: '#EEE',
            objectFit: "contain",
        }}
        src={dataUri}
        width={width}
        height={height}
        alt={file.name}
    />
}
