const { defineConfig } = require('cypress')

const outputDir = 'output';

module.exports = defineConfig({
  reporter: 'junit',
  reporterOptions: {
    mochaFile: outputDir + '/results/output-[hash].xml',
    toConsole: true
  },
  screenshotsFolder: outputDir + '/screenshots',
  videosFolder: outputDir + '/videos',
  downloadsFolder: outputDir + '/downloads',
  e2e: {
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      return require('./cypress/plugins/index.js')(on, config)
    },
  },
  chromeWebSecurity: false
})
