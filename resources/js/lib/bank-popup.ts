import { openCenteredPopup } from '@/lib/popup';

/**
 * Opens the bank picker (or, with an id, the reconnect screen) in a popup.
 * Returns false when the browser blocked the popup.
 */
export function openBankPopup(reconnectConnectionId?: number): boolean {
    const url =
        reconnectConnectionId === undefined
            ? '/wallets/add-account'
            : `/wallets/add-account?reconnect=${reconnectConnectionId}`;

    const popup = openCenteredPopup(url, 'bank-add-account', {
        width: 520,
        height: 760,
    });

    if (!popup) {
        return false;
    }

    popup.focus();

    return true;
}

/**
 * Opens Plaid Link for a UK bank in the same popup window. Falls back to this window
 * when popups are blocked (the finish page then returns to the dashboard).
 */
export function openPlaidPopup(): void {
    const popup = openCenteredPopup('/banking/plaid', 'bank-add-account', {
        width: 520,
        height: 760,
    });

    if (!popup) {
        window.location.assign('/banking/plaid');

        return;
    }

    popup.focus();
}
