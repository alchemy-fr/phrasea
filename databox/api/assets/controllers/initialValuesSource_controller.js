import { Controller } from '@hotwired/stimulus';
import $ from 'jquery'
import TomSelect      from "tom-select"

export default class extends Controller {
    static targets = ['input'];


    initialize(e) {
        console.log("initialValuesSource::initialize");
    }

    connect(e) {
        console.log("initialValuesSource::connect");
    }

    render(event) {
        console.log("initialValuesSource::render");

        const tagName = event.target.tomselect.getValue();
        let js;
        if(tagName === '') {
            js = '';
        }
        else {
            js = JSON.stringify(
                {
                    'type': 'metadata',
                    'value': tagName
                },
                null,
                2
            );
        }

        const otherController = this.application.getControllerForElementAndIdentifier($('.initialValuesAll')[0], 'initialValuesAll');
        otherController.tagChanged(js);

        // this.dispatch('tagChanged', {detail: js});
    }

    jsTagChanged(tagName) {
        console.log('jsTagChanged', tagName);
        const tom = $('.initialValuesSource SELECT')[0].tomselect;

        // load("") would load 100 first elements, we don't want that
        if(tagName) {
            const handler = function (data) {
                this.off('load', handler)
                if (data && data[0].entityId === tagName) {
                    this.setValue(data[0].entityId, true);
                }
                else {
                    this.setValue('', true);    // true (=silent): do NOT generate "changed" event (else it will clear the js)
                }
            };
            // we can update the select value only after load is finished
            tom.on('load', handler);
            tom.load(tagName);
            // tom will NOT call the handler if no need to fetch data (cache ?) so we enforce setValue here in case of...
            tom.setValue(tagName, true);
        }
        else {
            tom.setValue('', true);
        }
    }

}
