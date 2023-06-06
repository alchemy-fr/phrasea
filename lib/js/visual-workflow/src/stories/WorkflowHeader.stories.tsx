import type {Meta, StoryObj} from '@storybook/react';
import {WorkflowStatus} from "../types";
import {workflowSample} from "./workflowSample";
import WorkflowHeader from "../WorkflowHeader";
import WorkflowPlayground from "../WorkflowPlayground";

const meta = {
    title: 'WorkflowHeader',
    component: WorkflowHeader,
    // This component will have an automatically generated Autodocs entry: https://storybook.js.org/docs/react/writing-docs/autodocs
    tags: ['autodocs'],
    parameters: {
        // More on how to position stories at: https://storybook.js.org/docs/react/configure/story-layout
        layout: 'fullscreen',
    },
    decorators: [
        (Story) => {
            return <WorkflowPlayground
                style={{
                    padding: 15,
                    backgroundColor: '#AAA',
                }}
            >
                <Story/>
            </WorkflowPlayground>
        }
    ]
} satisfies Meta<typeof WorkflowHeader>;

export default meta;
type Story = StoryObj<typeof WorkflowHeader>;

const refresh = async (): Promise<void> => {
    return new Promise<void>((resolve) => {
        console.log(`Refreshing workflow`);
        setTimeout(() => {
            resolve();
        }, 1000);
    });
}

export const HeaderOne: Story = {
    args: {
        workflow: workflowSample,
        onRefreshWorkflow: refresh,
    },
};

export const HeaderComplex: Story = {
    args: {
        workflow: {
            id: '07b3aaba-04f1-4e7e-9e55-0d699e5a55ef',
            name: 'My custom workflow',
            status: WorkflowStatus.Started,
            stages: [],
            startedAt: '2023-05-24T10:22:25.495639+00:00',
            outputs: {
                foo: 'bar',
                results: [
                    {ID: '1', Name: 'Alice'},
                    {ID: '2', Name: 'Bob'},
                ]
            },
            event: {
                name: 'trigger',
                inputs: {
                    foo: 'bar',
                    baz: 42,
                },
            },
            context: {
                userId: '42424242',
                itemId: '987-654-321',
            }
        },
        onRefreshWorkflow: refresh,
    },
};
