import { Controller } from '@hotwired/stimulus';
import $ from 'jquery'
import TomSelect      from "tom-select"

export default class extends Controller {
    static targets = ['input'];
    static values = {
        v: String
    }

    initialize(e) {
        console.log("initialValuesSource::initialize");
    }

    connect(e) {
        console.log("initialValuesSource::connect");
//        this.render();
    }

    render(event) {
        console.log("initialValuesSource::render");
       // this.inputTarget.value = 'Adobe\\Adobe';
        const tagName = this.inputTarget.value;
        let js;
        if(tagName === '') {
            // $('.initialValuesAll TEXTAREA').val('');
            js = '';
        }
        else {
            js = JSON.stringify(
                {
                    'type': 'metadata',
                    'value': this.inputTarget.value
                },
                null,
                2
            );
            // $('.initialValuesAll TEXTAREA').val(JSON.stringify(js, null, 2));
        }
        this.vValue = '';
        this.inputTarget.value = 'Adobe\\Adobe';
        const otherController = this.application.getControllerForElementAndIdentifier($('.initialValuesAll')[0], 'initialValuesAll');
        otherController.tagChanged(js);
        // this.dispatch('tagChanged', {detail: js});
    }

    jsTagChanged(tagName) {
        console.log('jsTagChanged', tagName);
        this.inputTarget.value = tagName;
        this.render();
    }

}
