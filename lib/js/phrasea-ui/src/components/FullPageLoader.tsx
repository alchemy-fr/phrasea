import {CircularProgress} from "@mui/material";

type Props = {};

export default function FullPageLoader({}: Props) {
    return <div style={{
        background: 'rgba(0,0,0,0.3)',
        position: 'absolute',
        height: '100vh',
        width: '100vw',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center'
    }}>
        <CircularProgress/>
    </div>
}
