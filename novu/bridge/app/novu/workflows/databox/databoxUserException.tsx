import {workflow} from "@novu/framework";
import {z} from "zod";

export const databoxUserException = workflow(
    "databox-user-exception",
    async ({step, payload}) => {
        await step.inApp("In-App Step", async () => {
            return {
                // @ts-expect-error unknown issue
                subject: payload.subject,
                // @ts-expect-error unknown issue
                body: payload.message,
            };
        });
    },
    {
        payloadSchema: z.object({
            subject: z
                .string()
                .describe("The error subject"),
            message: z
                .string()
                .describe("The error message"),
        })
    },
);
