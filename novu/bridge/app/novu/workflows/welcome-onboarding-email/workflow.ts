import { workflow } from "@novu/framework";
import { renderEmail } from "../../emails/novu-onboarding-email";
import { emailControlSchema, payloadSchema } from "./schemas";

export const welcomeOnboardingEmail = workflow(
  "welcome-onboarding-email",
  async ({ step, payload }) => {
    await step.inApp("In-App Step", async () => {
      return {
        subject: payload.inAppSubject,
        body: payload.inAppBody,
        avatar: payload.inAppAvatar,
      };
    });
  },
  {
    payloadSchema,
  },
);
