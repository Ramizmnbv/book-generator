import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["details"];

    toggle() {
        this.detailsTarget.classList.toggle('hidden');
    }
}