import { Controller } from '@hotwired/stimulus';
import { StreamActions } from '@hotwired/turbo';

export default class extends Controller {
    static targets = ["locale", "seed", "likes", "reviews", "frame", "loader", "likesValue"];

    connect() {
        this.page = 1;
        // Set initial random seed
        this.randomizeSeed(); 
        
        // Setup IntersectionObserver for infinite scrolling
        this.observer = new IntersectionObserver(this.handleIntersect.bind(this), {
            root: null, // viewport
            rootMargin: '0px',
            threshold: 1.0
        });
        this.observer.observe(this.loaderTarget);
    }

    disconnect() {
        this.observer.disconnect();
    }
    
    // Called when an input changes
    generate() {
        this.page = 1; // Reset to page 1
        const url = this.buildUrl();
        this.frameTarget.src = url; // Turbo automatically fetches and replaces the frame content
    }

    // Special handler for the slider to update the value display
    updateAndGenerate() {
        this.likesValueTarget.textContent = this.likesTarget.value;
        this.generate();
    }
    
    randomizeSeed(event) {
        if (event) event.preventDefault();
        this.seedTarget.value = Math.floor(Math.random() * 1000000) + 1;
        this.generate();
    }

    // Called by the IntersectionObserver
    async handleIntersect(entries) {
        if (entries[0].isIntersecting) {
            this.page++;
            const url = this.buildUrl();
            const response = await fetch(url, {
                headers: {
                    Accept: 'text/vnd.turbo-stream.html'
                }
            });

            const html = await response.text();
         const fragment = document.createRange().createContextualFragment(html);
         document.body.appendChild(fragment); // This triggers Turbo Stream
        }
    }
    
    buildUrl() {
        const params = new URLSearchParams({
            locale: this.localeTarget.value,
            seed: this.seedTarget.value,
            likes: this.likesTarget.value,
            reviews: this.reviewsTarget.value,
            page: this.page
        });
        return `/api/books?${params.toString()}`;
    }
}