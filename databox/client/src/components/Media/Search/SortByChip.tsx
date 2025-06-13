import {SortBy} from './Filter';
import ArrowUpwardIcon from '@mui/icons-material/ArrowUpward';
import ArrowDownwardIcon from '@mui/icons-material/ArrowDownward';
import {Chip} from '@mui/material';
import {AttributeDefinition} from '../../../types.ts';

type Props = {
    definition: AttributeDefinition;
    sortBy: SortBy;
};

export default function SortByChip({definition, sortBy}: Props) {
    if (!definition) {
        console.warn(`Missing definition for ${sortBy.a}`);
        return null;
    }

    return (
        <Chip
            sx={{
                ml: 1,
            }}
            key={sortBy.a}
            label={
                <>
                    {definition.nameTranslated ?? definition.name}{' '}
                    {sortBy.w ? (
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
