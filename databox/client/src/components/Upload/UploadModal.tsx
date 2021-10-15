import React, {useEffect, useRef, useState} from 'react';
import Button from "../ui/Button";
import Modal from "../Layout/Modal";
import {UploadFiles} from "../../api/file";
import {Field, FieldProps, Form, Formik, FormikErrors, FormikProps} from "formik";
import {TextField} from "formik-material-ui";
import {FormControl, InputLabel, MenuItem, Select} from "@material-ui/core";
import {Workspace} from "../../types";
import {getWorkspaces} from "../../api/collection";
import FileCard from "./FileCard";
import {makeStyles} from "@material-ui/core/styles";
import {CollectionsTreeView} from "../Media/Collection/CollectionsTreeView";

type Props = {
    userId: string;
    onClose: () => void;
    files: File[];
}

type FormProps = {
    destinations?: string[];
};

function validate(values: FormProps) {
    const errors: FormikErrors<FormProps> = {};

    if (!values.destinations || values.destinations.length === 0) {
        errors.destinations = 'You must select one destination at least';
    }

    return errors;
}

const useStyles = makeStyles((theme) => ({
    files: {
        display: 'flex',
        flexWrap: 'wrap',
        justifyContent: 'start',
        '& > *': {
            margin: theme.spacing(1),
            width: 350
        },
        maxHeight: 400,
        overflow: 'auto',
    },
    formControl: {
        minWidth: 200,
    }
}));


export default function UploadModal({userId, files, onClose}: Props) {
    const formRef = useRef<FormikProps<FormProps>>(null);
    const [workspaces, setWorkspaces] = useState<Workspace[]>();

    const classes = useStyles();

    useEffect(() => {
        getWorkspaces().then(setWorkspaces);
    }, []);

    function onSubmit(data: FormProps) {
        UploadFiles(userId, files, {
            destinations: data.destinations!,
        });
        onClose();
    }

    const initialValues: FormProps = {
        destinations: [],
    };

    return <Modal
        onClose={onClose}
        header={() => <>
            Upload
        </>}
        footer={() => <>
            <Button
                onClick={onClose}
                className={'btn-secondary'}
            >
                Cancel
            </Button>
            <Button
                onClick={() => formRef.current!.submitForm()}
                className={'btn-primary'}
            >
                Upload
            </Button>
        </>}
    >
        <div className={classes.files}>
            {files.map((f, i) => <FileCard
                key={i}
                file={f}
            />)}
        </div>
        {workspaces && <Formik
            innerRef={formRef}
            initialValues={initialValues}
            onSubmit={(values: FormProps, actions) => {
                onSubmit(values);
            }}
            validate={validate}
        >
            <Form>
                <div className="form-group">
                    <Field
                        component={TextField}
                        name="title"
                        type="text"
                        label="Asset title"
                        required={true}
                    />
                </div>
                <Field
                    name="destinations"
                >
                    {({field, form: {errors, setFieldValue}}: FieldProps) => {
                        return <FormControl
                            variant="outlined"
                            className={classes.formControl}
                        >
                            <label>
                                Where?
                            </label>
                            <CollectionsTreeView
                                onChange={(selection) => setFieldValue(field.name, selection)}
                                workspaces={workspaces} />
                            {errors.destinations && <div className="error">{errors.destinations}</div>}
                        </FormControl>
                    }}
                </Field>
            </Form>
        </Formik>}
    </Modal>
}
