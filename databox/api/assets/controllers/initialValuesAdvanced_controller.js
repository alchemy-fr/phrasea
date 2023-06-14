import { Controller } from '@hotwired/stimulus';
import $ from 'jquery'

export default class extends Controller {
    static targets = ['input'];

    initialize() {
    }

    connect() {
        this.render();
    }

    render(event) {
        console.log("initialValuesAdvanced::render");

        const advanced = event && event.target.checked;
        if(advanced) {
            $('.initialValuesAll').show(); //.prop('disabled', false);
        }
        else {
            $('.initialValuesAll').hide(); //.prop('disabled', true);
            $('.initialValuesSource input').val('');
        }
    }
}
