import {TextFieldProps} from '@mui/material';
import TextType from './TextType';

export default class TextareaType extends TextType {
    public getFieldProps(): TextFieldProps {
        return {
            type: 'text',
            rows: 3,
            multiline: true,
        };
    }
}
