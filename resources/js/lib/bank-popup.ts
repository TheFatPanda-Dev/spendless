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
