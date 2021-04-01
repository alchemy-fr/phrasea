import React from "react";
import { useForm } from "react-hook-form";
import {Workspace} from "../../../types";
import apiClient from "../../../api/api-client";

type Props = {
    data: Workspace
};


export default function WorkspaceForm({data}: Props) {
    const { register, handleSubmit, errors } = useForm();

    async function submitWorkspace(formData: Workspace): Promise<void> {
        await apiClient.put(`/workspaces/${data.id}`, formData);
    }

    return <form onSubmit={handleSubmit(submitWorkspace)}>
        <input name="name" defaultValue={data.name} ref={register({required: true})} />
        {errors.name && <span>This field is required</span>}

        <input type="submit" />
    </form>
}
