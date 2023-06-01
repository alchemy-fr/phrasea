import { Controller } from '@hotwired/stimulus';
import $ from 'jquery'

/*
 * This is an example Stimulus controller!
 *
 * Any element with a data-controller="tag_group_choice" attribute will cause
 * this controller to be executed. The name "taggroupchoice" comes from the filename:
 * tag_group_choice_controller.js -> "tag_group_choice"
 *
 * Delete this file or adapt it for your use!
 */
export default class extends Controller {
    static targets = ['input'];

    initialize() {
        console.log("initialValuesAdvanced::initialize");
    }

    connect() {
        console.log("initialValuesAdvanced::connect");
        this.render();
    }

    render(event) {
        console.log("initialValuesAdvanced::render");
        const v = this.inputTarget.value;

        const advanced = event && event.srcElement.checked;
        if(advanced) {
        //    $('.fieldSource').hide();
            $('.initialValuesAll').show(); //.prop('disabled', false);
        }
        else {
        //    $('.fieldSource').show();
            $('.initialValuesAll').hide(); //.prop('disabled', true);
            $('.initialValuesSource input').val('');
        }
    }

}
