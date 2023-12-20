import type {Meta, StoryObj} from '@storybook/react';
import VisualWorkflow from "../VisualWorkflow";
import {workflowSample} from "./workflowSample";
import WorkflowPlayground from "../WorkflowPlayground";

const meta = {
    title: 'VisualWorkflow',
    component: VisualWorkflow,
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
                    height: 500,
                }}
            >
                <Story/>
            </WorkflowPlayground>
        }
    ]
} satisfies Meta<typeof VisualWorkflow>;

export default meta;
type Story = StoryObj<typeof VisualWorkflow>;

const rerun = async (jobId: string): Promise<void> => {
    return new Promise<void>((resolve) => {
        setTimeout(() => {
            resolve();
        }, 1000);
    });
}

export const WorkflowOne: Story = {
    args: {
        workflow: workflowSample,
        nodeWith: 300,
        nodeHeight: 55,
        stageXPadding: 30,
        nodeYPadding: 15,
        onRerunJob: rerun,
    },
};
