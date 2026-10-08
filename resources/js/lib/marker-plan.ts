import type { PivotRow } from '@/types/size-quantity';

export const toNum = (v: string | undefined): number => parseInt(v ?? '', 10) || 0;
export const digitsOnly = (v: string) => v === '' || /^\d+$/.test(v);

export function recalc(
  rows: PivotRow[],
  fixed: number,
  ratios: Record<string, string>,
  onlySize?: string,
): PivotRow[] {
  return rows.map((r) => {
    const quantities = { ...r.quantities };
    r.available.forEach((s) => {
      if (onlySize && s !== onlySize) return;
      quantities[s] = String(fixed * toNum(ratios[s]));
    });
    return { ...r, quantities };
  });
}

/**
 * Edit পেজের জন্য: সেভ করা quantity ÷ fixed_qty থেকে ratio বের করে।
 * ভাগ না মিললে বা fixed_qty না থাকলে ratio = 1।
 */
export function deriveRatios(
  sizes: string[],
  rows: PivotRow[],
  fixedQty: string,
): Record<string, string> {
  const fixed = toNum(fixedQty);

  return Object.fromEntries(
    sizes.map((s) => {
      if (fixed > 0) {
        const q = rows.map((r) => r.quantities[s]).find((v) => v !== undefined && v !== '');
        if (q !== undefined) {
          const n = toNum(q);
          if (n % fixed === 0) return [s, String(n / fixed)];
        }
      }
      return [s, '1'];
    }),
  );
}
