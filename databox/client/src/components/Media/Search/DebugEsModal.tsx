import {ESDebug} from "../../../api/asset";
import {Button, Dialog, DialogActions, DialogContent, DialogTitle} from "@mui/material";

type Props = {
    onClose: () => void;
    debug: ESDebug;
}

export default function DebugEsModal({
                                         debug,
    onClose,
}: Props) {
    return <Dialog onClose={onClose}
                   aria-labelledby="customized-dialog-title"
                   open={true}
                   maxWidth={'lg'}
    >
        <DialogTitle id="customized-dialog-title">
            Search Debug
            <br/><small>Elasticsearch response time: {Math.round(debug.esQueryTime * 1000)}ms</small>
            <br/><small>Total response time: {Math.round(debug.totalResponseTime * 1000) / 1000}ms</small>
        </DialogTitle>
        <DialogContent dividers>
            <pre>
                {JSON.stringify(debug.query, undefined, 2)}
            </pre>
        </DialogContent>
        <DialogActions>
            <Button autoFocus onClick={onClose} color="primary">
               Close
            </Button>
        </DialogActions>
    </Dialog>
}
