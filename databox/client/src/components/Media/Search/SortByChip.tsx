import {SortBy} from './Filter';
import ArrowUpwardIcon from '@mui/icons-material/ArrowUpward';
import ArrowDownwardIcon from '@mui/icons-material/ArrowDownward';
import {Chip} from '@mui/material';

type Props = {} & SortBy;

export default function SortByChip({t, w, a}: Props) {
    return (
        <Chip
            sx={{
                ml: 1,
            }}
            key={a}
            label={
                <>
                    {t}{' '}
                    {w ? (
                        <ArrowDownwardIcon
                            fontSize={'small'}
                            sx={{
                                verticalAlign: 'middle',
                            }}
                        />
                    ) : (
                        <ArrowUpwardIcon
                            fontSize={'small'}
                            sx={{
                                verticalAlign: 'middle',
                            }}
                        />
                    )}
                </>
            }
            color={'primary'}
        />
    );
}
