import {useEffect, useState} from "react";
import Thumb from "../../components/Media/Asset/Thumb";

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
    size: number;
}

export function FileBlobThumb({
                                  file,
                                  size,
                              }: Props) {

    const [dataUri, setDataUri] = useState<string>();

    useEffect(() => {
        fileToDataUri(file).then(setDataUri);
    }, [file]);

    return <Thumb
        size={size}
    >
        {dataUri && <img
            style={{
                objectFit: "contain",
            }}
            src={dataUri}
            alt={file.name}
        />}
    </Thumb>
}
