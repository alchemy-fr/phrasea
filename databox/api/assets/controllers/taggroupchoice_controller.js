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
        console.log("taggroupchoice:initialize");
        // const js = JSON.parse($('.initialValuesAll TEXTAREA').val());
        // if(js.type === 'metadata') {
        //     console.log(js.value);
        //     $(this.inputTarget).val(js.value).show();
        //     $(".advancedFieldSource input").attr('checked', false)
        // }
        // else if(js.type === 'template') {
        //     $(this.inputTarget).val('').hide();
        //     $(".advancedFieldSource input").attr('checked', true)
        // }
        // else {
        //     // invalid type
        // }
    }

    connect() {
        console.log("taggroupchoice:connect");
//        this.render();
    }

    taggroupchoice_render(event) {
        console.log("taggroupchoice:render");
        const tagName = this.inputTarget.value;
        if(tagName === '') {
            $('.initialValuesAll TEXTAREA').val('');
        }
        else {
            const js = {
                'type': 'metadata',
                'value': this.inputTarget.value
            };
            $('.initialValuesAll TEXTAREA').val(JSON.stringify(js, null, 2));
        }
    }

    render(event) {
        console.log("advancedFieldSource:render");
        const advanced = event && event.srcElement.checked;
        if(advanced) {
            //    $('.fieldSource').hide();
            $('.initialValuesAll').show(); //.prop('disabled', false);
        }
        else {
            $('.fieldSource').show();
            $('.initialValuesAll').hide(); //.prop('disabled', true);
        }
    }

}
