import {PropsWithChildren} from "react";
import {Box} from "@mui/material";

type Props = PropsWithChildren<{}>;

export default function FormRow({children}: Props) {
    return <Box
        sx={{
            mb: 3
        }}
    >
        {children}
    </Box>
}
