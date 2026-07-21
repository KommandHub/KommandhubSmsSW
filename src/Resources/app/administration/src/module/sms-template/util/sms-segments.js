/**
 * SMS length arithmetic.
 *
 * An SMS is not "160 characters". It is 160 *septets* — but only if every
 * character is in the GSM 03.38 alphabet. A single emoji, curly quote or
 * non-Latin letter forces the whole message to UCS-2 and the limit collapses to
 * 70 characters, which is how a merchant pasting a typographic apostrophe turns
 * one billable message into three.
 *
 * Concatenated messages are shorter still: six of the septets go to the header
 * that tells the handset how to reassemble the parts, so multipart GSM messages
 * hold 153 and multipart UCS-2 messages hold 67.
 *
 * Pure functions, no framework — this is the piece worth testing.
 */

/**
 * GSM 03.38 basic character set.
 */
const GSM_BASIC =
    '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?' +
    '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

/**
 * Characters reachable in GSM only via an escape sequence, so each one costs
 * two septets rather than one.
 */
const GSM_EXTENDED = '^{}\\[~]|€';

export const ENCODING_GSM = 'GSM-7';
export const ENCODING_UCS2 = 'UCS-2';

const LIMITS = {
    [ENCODING_GSM]: { single: 160, multi: 153 },
    [ENCODING_UCS2]: { single: 70, multi: 67 },
};

/**
 * Whether every character survives GSM encoding.
 *
 * @param {string} text
 * @returns {boolean}
 */
export function isGsmEncodable(text) {
    return [...text].every((char) => GSM_BASIC.includes(char) || GSM_EXTENDED.includes(char));
}

/**
 * Billable length in the message's own units: septets for GSM, code units for
 * UCS-2.
 *
 * Iterating with the spread operator gives whole code points, so an emoji built
 * from a surrogate pair is counted once here and then charged as the two UCS-2
 * code units it actually occupies.
 *
 * @param {string} text
 * @returns {number}
 */
export function countUnits(text) {
    if (!isGsmEncodable(text)) {
        // UCS-2 bills per 16-bit code unit, which is what .length already counts.
        return text.length;
    }

    return [...text].reduce((total, char) => total + (GSM_EXTENDED.includes(char) ? 2 : 1), 0);
}

/**
 * Full measurement of a message.
 *
 * @param {string} text
 * @returns {{encoding: string, characters: number, units: number, segments: number, limit: number, remaining: number}}
 */
export function measure(text) {
    const value = text ?? '';
    const encoding = isGsmEncodable(value) ? ENCODING_GSM : ENCODING_UCS2;
    const units = countUnits(value);
    const limits = LIMITS[encoding];

    if (units === 0) {
        return { encoding, characters: 0, units: 0, segments: 0, limit: limits.single, remaining: limits.single };
    }

    // Once the message spills past one segment, *every* segment pays the
    // concatenation header — including the first.
    const segments = units <= limits.single ? 1 : Math.ceil(units / limits.multi);
    const limit = segments === 1 ? limits.single : limits.multi * segments;

    return {
        encoding,
        characters: [...value].length,
        units,
        segments,
        limit,
        remaining: limit - units,
    };
}
