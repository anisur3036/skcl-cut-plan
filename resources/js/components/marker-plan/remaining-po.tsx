import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { toNum } from '@/lib/marker-plan';
import type { PivotRow } from '@/types/marker-plan';

type Props = {
  sizes: string[];
  rows: PivotRow[];
};

const redIfNegative = (n: number) => (n < 0 ? 'text-destructive font-semibold' : '');

export default function RemainingPo({ sizes, rows }: Props) {
  // Remaining = PO − আগের marker plan-এ ব্যবহৃত − এই ফর্মে এখনকার quantity
  const remaining = (r: PivotRow, sz: string): number =>
    (r.po_quantities?.[sz] ?? 0) - (r.used_quantities?.[sz] ?? 0) - toNum(r.quantities[sz]);

  const rowRemaining = (r: PivotRow): number =>
    sizes.reduce((sum, sz) => (r.available.includes(sz) ? sum + remaining(r, sz) : sum), 0);

  const colRemaining = (sz: string): number =>
    rows.reduce((sum, r) => (r.available.includes(sz) ? sum + remaining(r, sz) : sum), 0);

  const grandRemaining = rows.reduce((sum, r) => sum + rowRemaining(r), 0);

  const anyOver = rows.some((r) =>
    sizes.some((sz) => r.available.includes(sz) && remaining(r, sz) < 0),
  );

  return (
    <div className="space-y-2">
      <h3 className="text-sm font-semibold">Remaining PO Quantity</h3>

      <div className="overflow-x-auto rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Country</TableHead>
              <TableHead>Item</TableHead>
              <TableHead>Color</TableHead>
              {sizes.map((sz) => (
                <TableHead key={sz} className="text-center">
                  {sz}
                </TableHead>
              ))}
              <TableHead className="text-right">Total</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((r) => (
              <TableRow key={`${r.country}-${r.item_name}-${r.color_name}`}>
                <TableCell>{r.country}</TableCell>
                <TableCell>{r.item_name}</TableCell>
                <TableCell>{r.color_name}</TableCell>
                {sizes.map((sz) => {
                  if (!r.available.includes(sz)) {
                    return (
                      <TableCell
                        key={sz}
                        className="text-center text-muted-foreground"
                      >
                        -
                      </TableCell>
                    );
                  }

                  const rem = remaining(r, sz);
                  const po = r.po_quantities?.[sz] ?? 0;
                  const used = r.used_quantities?.[sz] ?? 0;
                  const now = toNum(r.quantities[sz]);

                  return (
                    <TableCell
                      key={sz}
                      className={`text-center ${redIfNegative(rem)}`}
                      title={`PO ${po} − Used ${used} − This plan ${now} = ${rem}`}
                    >
                      {rem}
                    </TableCell>
                  );
                })}
                <TableCell
                  className={`text-right font-medium ${redIfNegative(rowRemaining(r))}`}
                >
                  {rowRemaining(r)}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
          <TableFooter>
            <TableRow>
              <TableCell colSpan={3} className="font-semibold">
                Total
              </TableCell>
              {sizes.map((sz) => (
                <TableCell
                  key={sz}
                  className={`text-center font-semibold ${redIfNegative(colRemaining(sz))}`}
                >
                  {colRemaining(sz)}
                </TableCell>
              ))}
              <TableCell
                className={`text-right font-bold ${redIfNegative(grandRemaining)}`}
              >
                {grandRemaining}
              </TableCell>
            </TableRow>
          </TableFooter>
        </Table>
      </div>

      <p className="text-xs text-muted-foreground">
        Remaining = PO Qty − আগের marker plan − এই ফর্মের quantity (ঘরের উপর মাউস রাখলে হিসাব দেখা যাবে)।
      </p>
      {anyOver && (
        <p className="text-sm text-destructive">
          কিছু সাইজে quantity PO quantity ছাড়িয়ে গেছে (লাল সংখ্যা)।
        </p>
      )}
    </div>
  );
}
