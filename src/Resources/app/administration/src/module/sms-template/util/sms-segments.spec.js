import { measure, isGsmEncodable, countUnits, ENCODING_GSM, ENCODING_UCS2 } from './sms-segments';

describe('sms-segments', () => {
    describe('isGsmEncodable', () => {
        it('accepts plain Latin text', () => {
            expect(isGsmEncodable('Your order has shipped')).toBe(true);
        });

        it('accepts the GSM extension characters', () => {
            expect(isGsmEncodable('total: 50€ [urgent]')).toBe(true);
        });

        // The classic support ticket: a word processor turns ' into ’ and the
        // merchant's message silently costs more than twice as much.
        it('rejects a typographic apostrophe', () => {
            expect(isGsmEncodable('your order’s on its way')).toBe(false);
        });

        it('rejects emoji', () => {
            expect(isGsmEncodable('shipped 🎉')).toBe(false);
        });
    });

    describe('countUnits', () => {
        it('counts plain GSM characters once each', () => {
            expect(countUnits('abc')).toBe(3);
        });

        it('charges GSM extension characters twice', () => {
            expect(countUnits('€')).toBe(2);
            expect(countUnits('a€b')).toBe(4);
        });

        it('counts UCS-2 messages in code units', () => {
            expect(countUnits('ж')).toBe(1);
        });
    });

    describe('measure', () => {
        it('reports an empty message as zero segments', () => {
            const result = measure('');

            expect(result.segments).toBe(0);
            expect(result.characters).toBe(0);
            expect(result.encoding).toBe(ENCODING_GSM);
        });

        it('treats null as empty rather than throwing', () => {
            expect(measure(null).segments).toBe(0);
        });

        it('fits 160 GSM characters in one segment', () => {
            const result = measure('a'.repeat(160));

            expect(result.segments).toBe(1);
            expect(result.remaining).toBe(0);
            expect(result.encoding).toBe(ENCODING_GSM);
        });

        // The boundary that costs money: one more character does not add a
        // second segment of 160, it re-bills the whole message at 153.
        it('spills to two segments of 153 at 161 characters', () => {
            const result = measure('a'.repeat(161));

            expect(result.segments).toBe(2);
            expect(result.limit).toBe(306);
            expect(result.remaining).toBe(145);
        });

        it('fits 70 UCS-2 characters in one segment', () => {
            const result = measure('ж'.repeat(70));

            expect(result.segments).toBe(1);
            expect(result.encoding).toBe(ENCODING_UCS2);
            expect(result.remaining).toBe(0);
        });

        it('spills to two segments of 67 at 71 UCS-2 characters', () => {
            const result = measure('ж'.repeat(71));

            expect(result.segments).toBe(2);
            expect(result.limit).toBe(134);
        });

        // One emoji in an otherwise plain message drops the whole thing to
        // UCS-2, which is the counter's reason for existing.
        it('a single emoji forces the whole message to UCS-2', () => {
            const result = measure(`${'a'.repeat(100)}🎉`);

            expect(result.encoding).toBe(ENCODING_UCS2);
            expect(result.segments).toBe(2);
        });

        it('counts a surrogate pair as one character but two billed units', () => {
            const result = measure('🎉');

            expect(result.characters).toBe(1);
            expect(result.units).toBe(2);
        });
    });
});
