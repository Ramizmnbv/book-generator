import { Application } from '@hotwired/stimulus';
import { definitionsFromContext } from '@hotwired/stimulus-webpack-helpers';

// Start Stimulus app
const application = Application.start();

// Auto-register all controllers in assets/controllers
const context = require.context('./controllers', true, /\.js$/);
application.load(definitionsFromContext(context));
