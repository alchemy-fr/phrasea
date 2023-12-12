import {createContext} from 'react';
import {UploaderUser} from '../types.ts';

export type TUploaderUserContext = {
    uploaderUser?: UploaderUser | undefined;
};

export default createContext<TUploaderUserContext>({});
