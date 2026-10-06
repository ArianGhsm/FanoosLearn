/*
 * Prices as the owner types and reads them: Tomans with Persian digits and
 * the Persian thousands separator, stored in Rials (x10), the unit the
 * gateway takes. No DOM here, so the rules can be tested on their own.
 */

export const faDigits = (value) => String(value).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
const toAscii = (value) => String(value).replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));

/** «۱۵۰٬۰۰۰» -- Persian digits with the Persian thousands separator. */
export function groupDigits(number) {
    return faDigits(Number(number).toLocaleString('en-US')).replace(/,/g, '٬');
}

/** «۱۵۰٬۰۰۰ تومان» from Rials. */
export function tomanText(rial) {
    if (rial === null || rial === undefined) return 'بدون قیمت';
    return `${groupDigits(Math.round(Number(rial) / 10))} تومان`;
}

/** Rials from whatever was typed as Tomans (Persian digits, separators); null if it is not a number. */
export function rialFromTyped(typed) {
    const digits = toAscii(typed).replace(/[\s,٬.]/g, '');
    if (!/^[0-9]{1,12}$/.test(digits)) return null;
    return Number(digits) * 10;
}

/** «٪۲۰ تخفیف» or «۵۰٬۰۰۰ تومان تخفیف», and the product when the discount is for one. */
export function discountText(item) {
    const value = item.kind === 'percent' ? `٪${faDigits(item.percent)} تخفیف` : `${tomanText(item.amount_minor)} تخفیف`;
    return item.product_name ? `${value} برای «${item.product_name}»` : value;
}
