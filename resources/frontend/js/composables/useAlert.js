import { inject } from 'vue';

export const ALERT_KEY = Symbol('aiNexusAlert');

export function useAlert() {
    const alertState = inject(ALERT_KEY, null);

    function alertError(message) {
        if (!alertState) {
            return;
        }

        alertState.type = 'error';
        alertState.autoClose = false;
        alertState.message = message;
    }

    function alertSuccess(message, autoClose = false) {
        if (!alertState) {
            return;
        }

        alertState.type = 'success';
        alertState.autoClose = autoClose;
        alertState.message = message;
    }

    function alertConfirm(message, success, failure = null) {
        if (!alertState) {
            return;
        }

        alertState.type = 'confirmation';
        alertState.autoClose = false;
        alertState.message = message;
        alertState.confirmationProceed = success;
        alertState.confirmationCancel = failure;
    }

    return {
        alertState,
        alertError,
        alertSuccess,
        alertConfirm,
    };
}
