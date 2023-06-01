/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */
// any CSS you import will output into a single css file (admin.css in this case)
import './styles/admin.css';

// start the Stimulus application
import './bootstrap';
// import './controllers/taggroupchoice_controller'
import './controllers/initialValuesSource_controller'
import './controllers/initialValuesAdvanced_controller'
import './controllers/initialValuesAll_controller'
//import './controllers/initialValueAll_controller'
// import './controllers/advancedFieldSource_controller'

console.log("assets/admin.js");

