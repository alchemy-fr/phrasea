import { Controller } from '@hotwired/stimulus';
import $ from 'jquery'

export default class extends Controller {
    static targets = ['input'];
    static outlets = ['initialValuesSource'];

    initialValuesSourceOutletConnected(e) {
        // tomselect will only be set AFTER connect (?) so we update the select after return (in fact 1 ms is enough...)
        // https://github.com/hotwired/stimulus/issues/618
        setTimeout(
            () => { this.render(); },
            100
        );
    }

    tagChanged(js) {
        this.inputTarget.value = js;
    }

    render() {
        let tagName = '';
        try {
            const js = JSON.parse(this.inputTarget.value);
            if (js.type === 'metadata') {
                tagName = js.value;
            }
        }
        catch (e) {
            // bad json, ignore
        }

        const otherController = this.application.getControllerForElementAndIdentifier($('.initialValuesSource')[0], 'initialValuesSource');
        otherController.jsTagChanged(tagName);
    }
}
