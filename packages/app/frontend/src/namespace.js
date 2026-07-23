// ---------------------------
// window.App namespace
// ---------------------------
// Consolidates all public globals under a single namespace.
// Inline page scripts use App.* instead of individual global references.
// Rename 'App' to whatever suits your project.

import {
    getRouteParams,
    populateSelect,
    escapeHtml,
    apiFetch,
    HandlrToast,
    openGlobalModal,
    closeGlobalModal,
    isGlobalModalOpen,
    HandlrConfirm,
    openFormModal,
    createBurst,
    attachClickBurst,
    initClickBurst,
    createTypeahead,
    onAfterSwap,
    onAfterSettle,
    onPageLoad,
    onHistoryRestore,
} from '@phillipsharring/handlr-frontend';

window.App = {
    // API client
    api: { fetch: apiFetch },

    // Shared utilities
    getRouteParams,
    populateSelect,
    escapeHtml,

    // UI widgets
    ui: {
        toast: HandlrToast,
        modal: {
            open: openGlobalModal,
            close: closeGlobalModal,
            isOpen: isGlobalModalOpen,
        },
        confirm: HandlrConfirm,
        openFormModal,
        clickBurst: {
            create: createBurst,
            attach: attachClickBurst,
            init: initClickBurst,
        },
        createTypeahead,
    },

    // Lifecycle hooks
    hooks: { onAfterSwap, onAfterSettle, onPageLoad, onHistoryRestore },
};
