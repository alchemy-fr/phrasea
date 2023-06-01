import { Controller } from '@hotwired/stimulus';
import snarkdown from 'snarkdown';

const document = window.document;
export default class extends Controller {
    static targets = ['input'];
    outputElement = null;
    initialize() {
        console.log("snarkdown init");
        this.outputElement = document.createElement('div');
        this.outputElement.className = 'markdown-preview';
        this.outputElement.textContent = 'MARKDOWN WILL BE RENDERED HERE';
        this.element.append(this.outputElement);
    }
    connect() {
        console.log("snarkdown connect");
        this.render();
    }
    render() {
        console.log("snarkdown render");
        const markdownContent = this.inputTarget.value;
        this.outputElement.innerHTML = snarkdown(markdownContent);
    }
}
