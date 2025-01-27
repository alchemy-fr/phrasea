import {workflow} from "@novu/framework";
import {z} from "zod";

export const databoxAssetUpdate = workflow(
    "databox-asset-update",
    async ({step, payload}) => {
        await step.inApp("In-App Step", async () => {
            return {
                subject: `Asset ${payload.title} updated`,
                body: `${payload.author} has updated asset ${payload.title}.`,
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
            title: z
                .string()
                .describe("The Asset title"),
            author: z
                .string()
                .describe("The author of the message"),
        })
    },
);
