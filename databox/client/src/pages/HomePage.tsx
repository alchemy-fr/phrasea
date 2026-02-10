import LandingEditor from '../components/Landing/Editor/LandingEditor.tsx';
import {Container} from '@mui/material';

type Props = {};

export default function HomePage({}: Props) {
    return (
        <>
            <Container>
                <LandingEditor />
            </Container>
        </>
    );
}
