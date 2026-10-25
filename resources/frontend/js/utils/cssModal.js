/**
 * Show/hide Bootstrap-styled modals without Bootstrap JS.
 * Toggles `.show` / `modal-open` / `.modal-backdrop` expected by Bootstrap CSS.
 * Escape closes the topmost modal that registered an `onEscape` handler.
 */

let openCount = 0;

/** @type {Array<{element: HTMLElement, onEscape: () => void}>} */
const escapeStack = [];

/** @type {((event: KeyboardEvent) => void)|null} */
let escapeListener = null;

/**
 * @returns {void}
 */
function ensureEscapeListener() {
    if (escapeListener) {
        return;
    }

    escapeListener = (event) => {
        if (event.key !== 'Escape' || escapeStack.length === 0) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        escapeStack[escapeStack.length - 1].onEscape();
    };

    document.addEventListener('keydown', escapeListener);
}

/**
 * @returns {void}
 */
function releaseEscapeListener() {
    if (escapeStack.length > 0 || !escapeListener) {
        return;
    }

    document.removeEventListener('keydown', escapeListener);
    escapeListener = null;
}

/**
 * @param {HTMLElement} element
 * @returns {void}
 */
function unregisterEscape(element) {
    for (let index = escapeStack.length - 1; index >= 0; index -= 1) {
        if (escapeStack[index].element === element) {
            escapeStack.splice(index, 1);
            break;
        }
    }

    releaseEscapeListener();
}

/**
 * @param {HTMLElement|null|undefined} element
 * @param {{onEscape?: () => void}|(() => void)|null|undefined} options Escape callback or options bag.
 * @returns {void}
 */
export function showCssModal(element, options = null) {
    if (!element || element.classList.contains('show')) {
        return;
    }

    element.classList.add('show');
    element.style.display = 'block';
    element.setAttribute('aria-modal', 'true');
    element.setAttribute('role', 'dialog');
    element.removeAttribute('aria-hidden');

    document.body.classList.add('modal-open');
    openCount += 1;

    if (!document.querySelector('.modal-backdrop[data-speculum-modal]')) {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.dataset.speculumModal = '1';
        document.body.appendChild(backdrop);
    }

    const onEscape =
        typeof options === 'function'
            ? options
            : options && typeof options.onEscape === 'function'
              ? options.onEscape
              : null;

    if (onEscape) {
        unregisterEscape(element);
        escapeStack.push({ element, onEscape });
        ensureEscapeListener();
    }
}

/**
 * @param {HTMLElement|null|undefined} element
 * @param {(() => void)|null|undefined} onHidden
 * @returns {void}
 */
export function hideCssModal(element, onHidden = null) {
    if (element) {
        unregisterEscape(element);
    }

    if (!element || !element.classList.contains('show')) {
        if (typeof onHidden === 'function') {
            onHidden();
        }

        return;
    }

    element.classList.remove('show');
    element.style.display = 'none';
    element.setAttribute('aria-hidden', 'true');
    element.removeAttribute('aria-modal');

    openCount = Math.max(0, openCount - 1);

    if (openCount === 0) {
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop[data-speculum-modal]').forEach((node) => {
            node.remove();
        });
    }

    if (typeof onHidden === 'function') {
        onHidden();
    }
}
