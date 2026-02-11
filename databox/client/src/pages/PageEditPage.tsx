import PageEditor from '../components/Landing/Editor/PageEditor.tsx';
import {Container} from '@mui/material';

type Props = {};

export default function PageEditPage({}: Props) {
    return (
        <>
            <Container>
                <PageEditor />
            </Container>
        </>
    );
}
