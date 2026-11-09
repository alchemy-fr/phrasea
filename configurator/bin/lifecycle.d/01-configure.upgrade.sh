# Sourced by the common lifecycle script (alchemy/core-bundle).

# Provisions what the new version adds, before its migrations run.
configure_presets=""
for configure_preset in ${CONFIGURATOR_PRESETS}; do
    configure_presets="${configure_presets} --preset ${configure_preset}"
done
# shellcheck disable=SC2086
"${CONSOLE}" configure -vvv ${configure_presets}
