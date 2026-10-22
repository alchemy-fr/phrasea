// ***********************************************************
// This example plugins/index.js can be used to load plugins
//
// You can change the location of this file or turn off loading
// the plugins file with the 'pluginsFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/plugins-guide
// ***********************************************************

// This function is called when a project is opened or re-opened (e.g. due to
// the project's config changing)

module.exports = (on, config) => {
  on('task', {
    log(message) {
      console.log(message);

      return null;
    },
  });
  // `on` is used to hook into various events Cypress emits
  // `config` is the resolved Cypress config

  // CYPRESS_* variables only land in `config.env`, which specs cannot read
  // synchronously: expose them for `Cypress.expose()`.
  config.expose = {...config.expose, ...config.env};

  return config;
}
