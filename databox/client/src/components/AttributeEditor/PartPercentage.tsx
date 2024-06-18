import {styled} from "@mui/material/styles";

type Props = {
    part: number;
    width: number;
};

const Label = styled('div')(({theme}) => ({
    position: 'absolute',
    transform: 'translateY(-50%)',
    top: '50%',
    right: 0,
    paddingLeft: theme.spacing(1)
}));

const Container = styled('div')(({theme}) => ({
    position: 'relative',
    height: 20,
    fontSize: 12,
    marginLeft: theme.spacing(2)
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

export default function PartPercentage({
    part,
    width,
}: Props) {
    return <Container
        className={partPercentageClassName}
        style={{
            width,
        }}
    >
        <ProgressContainer style={{
            width: width - 45,
        }}>

        <Progress style={{
            width: `${part}%`,
        }}/>
        </ProgressContainer>

        <Label>
            {part}%
        </Label>
    </Container>
}
