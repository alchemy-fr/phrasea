import {ReactNode} from 'react';
import {Alert} from '@mui/material';
import nl2br from 'react-nl2br';
import FormRow from './FormRow';

type Props = {
    errors?: ReactNode[] | undefined;
};

export default function RemoteErrors({errors}: Props) {
    if (!errors || errors.length === 0) {
        return null;
    }

    return (
        <FormRow>
            {errors.map((e, i) => (
                <Alert key={i} severity="error">
                    <div
                        style={{
                            whiteSpace: 'pre',
                        }}
                    >
                        {nl2br(e)}
                    </div>
                </Alert>
            ))}
        </FormRow>
    );
}
