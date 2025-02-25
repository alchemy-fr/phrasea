import type {Meta, StoryObj} from '@storybook/react';
import {workflowSample} from './workflowSample';
import WorkflowPlayground from '../WorkflowPlayground';
import WorkflowUpdater from '../WorkflowUpdater';

const meta = {
    title: 'Updates',
    component: WorkflowUpdater,
    // This component will have an automatically generated Autodocs entry: https://storybook.js.org/docs/react/writing-docs/autodocs
    tags: ['autodocs'],
    parameters: {
        // More on how to position stories at: https://storybook.js.org/docs/react/configure/story-layout
        layout: 'fullscreen',
    },
    decorators: [
        Story => {
            return (
                <WorkflowPlayground
                    style={{
                        height: 500,
                    }}
                >
                    <Story />
                </WorkflowPlayground>
            );
        },
    ],
} satisfies Meta<typeof WorkflowUpdater>;

export default meta;
type Story = StoryObj<typeof WorkflowUpdater>;

const rerun = async (_jobId: string): Promise<void> => {
    return new Promise<void>(resolve => {
        setTimeout(() => {
            resolve();
        }, 200);
    });
};

export const Updates: Story = {
    args: {
        workflow: workflowSample,
        onRerunJob: rerun,
    },
};
