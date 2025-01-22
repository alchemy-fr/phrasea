import {CircularProgress} from "@mui/material";

type Props = {};

export default function LoadingPdfPage({}: Props) {
    const ratio = 2;
    return <>
        <div
            style={{
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
                height: 297 * ratio,
                width: 210 * ratio,
            }}
        >
            <CircularProgress />
        </div>
    </>
}
