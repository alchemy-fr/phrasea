import {Alert, AlertTitle, Typography, Button} from "@mui/material";

type Props = {
    onRestart: () => void;
};

export default function UploadDone({
    onRestart
}: Props) {
    return (
        <>
            <Alert
                sx={{
                    p: 3,
                }}
                action={<Button
                    color={'success'}
                    variant={'contained'}
                    onClick={onRestart}
                >
                    Go back Home
                </Button>}
            >
                <AlertTitle>You're done!</AlertTitle>
                <Typography variant={'body1'}>
                    All files have been uploaded.
                </Typography>
            </Alert>
        </>
    )
}
