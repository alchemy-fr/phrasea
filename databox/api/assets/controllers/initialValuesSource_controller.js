import { Controller } from '@hotwired/stimulus';
import $ from 'jquery'
import TomSelect      from "tom-select"

export default class extends Controller {
    static targets = ['input'];


    initialize(e) {
    }

    connect(e) {
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
                2
            );
        }

        const otherController = this.application.getControllerForElementAndIdentifier($('.initialValuesAll')[0], 'initialValuesAll');
        otherController.tagChanged(js);

        // this.dispatch('tagChanged', {detail: js});
    }

    jsTagChanged(tagName) {
        const tom = $('.initialValuesSource SELECT')[0].tomselect;
        let handler;

        // load("") would load 100 first elements, we don't want that
        if(tagName) {
            // ??? buggy if we declare the callback immediatly (tagName value is one step "old")
            // solution is to _generate_ the callback, injecting the current tagName value ???
            const gethandler = function(tn) {
                return function (data) {
                    this.off('load', handler)
                    if (data && data[0].entityId === tn) {
                        this.setValue(data[0].entityId, true);
                    }
                    else {
                        this.setValue('', true);    // silent=true: do NOT generate "changed" event (else it will clear the js)
                    }
                };
            };
            // we can update the select value only after load is finished
            handler = gethandler(tagName);
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
