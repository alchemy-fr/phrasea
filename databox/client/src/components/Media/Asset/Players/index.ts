import {File} from "../../../../types";

export type FileWithUrl = {
    url: string;
} & File;

export type PlayerProps = {
    file: FileWithUrl;
    thumbSize: number;
};
