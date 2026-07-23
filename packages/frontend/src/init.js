// ---------------------------
// Handlr Framework initialization (side effects)
// ---------------------------
// Import this module to register all framework event listeners,
// CSRF interceptors, boosted-nav handlers, form error handling, etc.
// Order matters: csrf must be before auth-state.

import './core/csrf.js';
import './core/boosted-nav.js';
import './core/auth-state.js';
import './core/forms.js';
import './core/search.js';
import './core/sortable.js';
import './hooks.js';

// Declarative behavior layer (ADR 0003) — delegated data-action / data-toggle
// and data-on-success / data-on-error. These expose initX() functions (not
// import-time self-registration) and are turned on here.
import { initActions } from './core/actions.js';
import { initFormResponses } from './core/form-response.js';

initActions();
initFormResponses();
