import {styled} from '@mui/material/styles';

const Label = styled('div')(({theme}) => ({
    position: 'absolute',
    transform: 'translateY(-50%)',
    top: '50%',
    right: 0,
    paddingLeft: theme.spacing(1),
    color: theme.palette.primary.main,
}));

const Container = styled('div')(({theme}) => ({
    position: 'relative',
    height: 15,
    marginLeft: theme.spacing(2),
    fontSize: 12,
}));

const ProgressContainer = styled('div')(() => ({
    position: 'relative',
    height: '100%',
}));

const Progress = styled('div')(({theme}) => ({
    height: '100%',
    backgroundColor: theme.palette.primary.main,
}));

export const partPercentageClassName = 'part-percent';

type Props = {
    part: number;
    width: number;
    displayPercents: boolean;
    total: number;
};

export default function PartPercentage({part, width, displayPercents, total}: Props) {
    const textOffset = displayPercents ? 45 : 70;

    return (
        <Container
            style={{
                width,
            }}
            className={partPercentageClassName}
        >
            <ProgressContainer
                style={{
                    width: width - textOffset,
                }}
            >
                <Progress
                    style={{
                        width: `${part}%`,
                    }}
                />
            </ProgressContainer>
            <Label>
                {displayPercents ? `${Math.round(part * 10) / 10}%` : `${Math.round(part * total / 100)}/${total}`}
            </Label>
        </Container>
    );
}
