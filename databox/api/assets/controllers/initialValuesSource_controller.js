import { Controller } from '@hotwired/stimulus';
import $ from 'jquery'
import TomSelect      from "tom-select"

export default class extends Controller {
    static targets = ['input'];

    initialize() {
        this.handler = null;
        this.tagNameToSet = null;
    }

    render(event) {
        const tagName = event.target.tomselect.getValue();
        let js = '';
        if(tagName !== '') {
            js = JSON.stringify(
                {
                    'type': 'metadata',
                    'value': tagName
                },
                null,
                4
            );
        }

        const otherController = this.application.getControllerForElementAndIdentifier($('.initialValuesAll')[0], 'initialValuesAll');
        otherController.tagChanged(js);
    }

    gethandler(tom) {
        return (data) => {
            if (data && data[0].entityId === tom.tagNameToSet) {
                tom.setValue(data[0].entityId, true);
            }
            else {
                tom.setValue('', true);    // silent=true: do NOT generate "changed" event (else it will clear the js)
            }
        };
    };

    jsTagChanged(tagName) {
        const tom = $('.initialValuesSource SELECT')[0].tomselect;
        if(!tom) {
            return;
        }

        // load("") would load 100 first elements, we don't want that
        if(tagName) {
            // we can update the select value only after load is finished
            if(!this.handler) {
                tom.on('load', this.handler = this.gethandler(tom));
            }
            tom.load(tom.tagNameToSet = tagName);
            // tom will NOT call the handler if no need to fetch data (cache ?) so we enforce setValue just in case.
            tom.setValue(tagName, true);
        }
        else {
            tom.setValue('', true);
        }
    }
}
