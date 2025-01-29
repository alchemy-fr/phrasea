import { serve } from "@novu/framework/next";
import * as workflows from "../../novu/workflows";

// the workflows collection can hold as many workflow definitions as you need
export const { GET, POST, OPTIONS } = serve({
  workflows: Object.entries(workflows).map(([name, workflow]) => workflow),
});
