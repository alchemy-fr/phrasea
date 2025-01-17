import {workflow} from "@novu/framework";
import {z} from "zod";

export const databoxDiscussionNewComment = workflow(
    "databox-discussion-new-comment",
    async ({step, payload}) => {
        await step.inApp("In-App Step", async () => {
            return {
                subject: `New comment on ${payload.object}`,
                body: `${payload.author} has commented on ${payload.object}.`,
            };
        });
    },
    {
        payloadSchema: z.object({
            object: z
                .string()
                .describe("The object title"),
            author: z
                .string()
                .describe("The author of the message"),
        })
    },
);
