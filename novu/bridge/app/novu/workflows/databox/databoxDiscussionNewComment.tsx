import {workflow} from "@novu/framework";
import {z} from "zod";

export const databoxDiscussionNewComment = workflow(
    "databox-discussion-new-comment",
    async ({step, payload}) => {
        await step.inApp("In-App Step", async () => {
            return {
                subject: `New comment on ${payload.object}`,
                body: `${payload.author} has commented on ${payload.object}.`,
                redirect: {
                    url: payload.url,
                },
            };
        });
    },
    {
        payloadSchema: z.object({
            url: z
                .string()
                .describe("The resource URL"),
            object: z
                .string()
                .describe("The object title"),
            author: z
                .string()
                .describe("The author of the message"),
        })
    },
);
