import React, {useEffect, useState} from 'react';
import {useParams} from "react-router-dom";
import {getWorkflow} from "../../api/workflow";
import {CircularProgress, Paper} from "@mui/material";
import {VisualWorkflow, Workflow} from "@alchemy/visual-workflow";
import "@alchemy/visual-workflow/style.css";

type Props = {};

export default function WorkflowView({}: Props) {
    const {id} = useParams();

    const [data, setData] = useState<Workflow>();

    useEffect(() => {
        getWorkflow(id!).then(c => setData(c));
    }, [id]);

    if (!data) {
        return <CircularProgress/>
    }

    return <>
        <Paper
            sx={{
                zIndex: 10,
                p: 1,
                position: 'absolute',
                top: 0,
                left: 0,
                right: 0,
            }}
        >
            <span>
                <b>{data.name}</b> #{data.id}{' - '}
            </span>
            <span>
                {(data as any).duration}
            </span>
        </Paper>
        <VisualWorkflow
            workflow={data}
        />
    </>
}
