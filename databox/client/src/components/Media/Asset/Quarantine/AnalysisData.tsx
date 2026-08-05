import {
    Box,
    Table,
    TableBody,
    TableCell,
    TableRow,
    Typography,
} from '@mui/material';

export type DataRow = {
    label: string;
    value: React.ReactNode;
};

type Props = {
    rows: DataRow[];
};

/**
 * Compact key/value table used by analyzers to display the technical `data`
 * they extracted from the file (checksum, dimensions, colorspace, ...).
 */
export default function AnalysisData({rows}: Props) {
    const visibleRows = rows.filter(
        r => r.value !== undefined && r.value !== null && r.value !== ''
    );

    if (visibleRows.length === 0) {
        return null;
    }

    return (
        <Table size={'small'} sx={{mt: 1}}>
            <TableBody>
                {visibleRows.map(row => (
                    <TableRow key={row.label}>
                        <TableCell
                            component={'th'}
                            sx={{
                                width: '40%',
                                border: 0,
                                py: 0.5,
                                verticalAlign: 'top',
                            }}
                        >
                            <Typography
                                variant={'body2'}
                                color={'text.secondary'}
                            >
                                {row.label}
                            </Typography>
                        </TableCell>
                        <TableCell sx={{border: 0, py: 0.5}}>
                            <Box
                                component={'div'}
                                sx={{
                                    fontSize: '0.8125rem',
                                    wordBreak: 'break-all',
                                }}
                            >
                                {row.value}
                            </Box>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
