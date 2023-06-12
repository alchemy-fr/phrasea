import { Controller } from '@hotwired/stimulus';
import $ from 'jquery'

export default class extends Controller {
    static targets = ['input'];


    initialize(e) {
        console.log("initialValuesAll::initialize");
    }

    connect(e) {
        console.log("initialValuesAll::connect");
    }

    tagChanged(e) {
        console.log("initialValuesAll::tagChanged", e);
        this.inputTarget.value = e;
    }

    render() {
        console.log("initialValuesAll::render");

        let tagName = '';
        try {
            const js = JSON.parse(this.inputTarget.value);
            if (js.type === 'metadata') {
                tagName = js.value;
//                $(".fieldSource input").val(js.value);
            }
        }
        catch (e) {
            // bad json, ignore
        }

        const otherController = this.application.getControllerForElementAndIdentifier($('.initialValuesSource')[0], 'initialValuesSource');
        otherController.jsTagChanged(tagName);
    }
}
