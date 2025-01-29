import { serve } from "@novu/framework/next";
import {
    databoxAssetUpdate,
    databoxDiscussionNewComment,
    exposeDownloadLink,
    exposeZippyDownloadLink,
    uploaderCommitAcknowledged,
} from "../../novu/workflows";

// the workflows collection can hold as many workflow definitions as you need
export const { GET, POST, OPTIONS } = serve({
  workflows: [
      uploaderCommitAcknowledged,
      exposeZippyDownloadLink,
      exposeDownloadLink,
      databoxDiscussionNewComment,
      databoxAssetUpdate,
  ],
});
