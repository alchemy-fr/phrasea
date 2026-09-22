export type CommandCommonOptions = {
    debug: boolean;
    /**
     * Whether to start the asset HTTP server alongside the indexation.
     * Commander sets this to false when `--no-server` is passed; it defaults
     * to true, which is the historical behaviour.
     */
    server?: boolean;
};
