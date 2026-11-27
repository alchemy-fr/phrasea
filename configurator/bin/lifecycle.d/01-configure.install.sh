# Sourced by the common lifecycle script (alchemy/core-bundle).

# configure --install builds the current state of every service, so the
# migrations (Keycloak ones included) are only marked as executed afterwards.
configure_presets=""
for configure_preset in ${CONFIGURATOR_PRESETS}; do
    configure_presets="${configure_presets} --preset ${configure_preset}"
done
# shellcheck disable=SC2086
"${CONSOLE}" configure -vvv --install ${configure_presets}
