import {workflow} from "@novu/framework";
import {z} from "zod";

const payloadSchema = z.object({
    url: z
        .string()
        .default('/null')
        .describe("The resource URL"),
    title: z
        .string()
        .describe("The Asset title"),
    author: z
        .string()
        .describe("The author of the message"),
});

export const databoxAssetUpdate = workflow(
    "databox-asset-update",
    async ({step, payload}) => {
        await step.inApp("In-App Step", async () => {
            return {
                // @ts-expect-error unknown issue
                subject: `Asset **${payload.title}** updated`,
                // @ts-expect-error unknown issue
                body: `**${payload.author}** has updated asset **${payload.title}**.`,
                redirect: {
                    // @ts-expect-error unknown issue
                    url: payload.url,
                },
            };
        });
    },
    {
        payloadSchema: payloadSchema
    },
);
