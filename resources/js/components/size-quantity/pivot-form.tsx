import { type ReactNode, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { digitsOnly, recalc, toNum } from '@/lib/size-quantity';
import type { PivotChange, PivotRow } from '@/types/size-quantity';
import RemainingPo from '@/components/size-quantity/remaining-po';

type Props = {
  sizes: string[];
  rows: PivotRow[];
  fixedQty: string;
  initialRatios?: Record<string, string>;
  onChange: (next: PivotChange) => void;
  children?: ReactNode;
};

export default function PivotForm({ sizes, rows, fixedQty, initialRatios, onChange, children }: Props) {
  // Ratio সেভ হয় না, শুধু হিসাবের জন্য
  const [ratios, setRatios] = useState<Record<string, string>>(
    () => initialRatios ?? Object.fromEntries(sizes.map((s) => [s, ''])),
  );

  const setQty = (rowIdx: number, size: string, value: string) => {
    if (!digitsOnly(value)) return;
    onChange({
      fixedQty,
      rows: rows.map((r, i) =>
        i === rowIdx ? { ...r, quantities: { ...r.quantities, [size]: value } } : r,
      ),
    });
  };

  const handleRatioChange = (size: string, value: string) => {
    if (!digitsOnly(value)) return;
    const next = { ...ratios, [size]: value };
    setRatios(next);
    if (fixedQty !== '') {
      onChange({ fixedQty, rows: recalc(rows, toNum(fixedQty), next, size) });
    }
  };

  const handleFixedChange = (value: string) => {
    if (!digitsOnly(value)) return;
    onChange({
      fixedQty: value,
      rows: value === '' ? rows : recalc(rows, toNum(value), ratios),
    });
  };

  const applyToRow = (idx: number) => {
    if (fixedQty === '') return;
    const fixed = toNum(fixedQty);
    onChange({
      fixedQty,
      rows: rows.map((r, i) => (i === idx ? recalc([r], fixed, ratios)[0] : r)),
    });
  };

  const rowTotal = (r: PivotRow): number =>
    sizes.reduce((sum, sz) => sum + toNum(r.quantities[sz]), 0);
  const colTotal = (sz: string): number =>
    rows.reduce((sum, r) => sum + toNum(r.quantities[sz]), 0);
  const grandTotal = rows.reduce((sum, r) => sum + rowTotal(r), 0);

  return (
    <div className="space-y-4">
      <RemainingPo sizes={sizes} rows={rows} />
      <div className="flex flex-wrap items-start gap-4 rounded-md border bg-muted/40 p-3">
        {children}

        <div className="grid gap-2">
          <Label htmlFor="fixed_qty">Lay Quantity</Label>
          <Input
            id="fixed_qty"
            inputMode="numeric"
            placeholder="e.g. 40"
            className="w-32"
            value={fixedQty}
            onChange={(e) => handleFixedChange(e.target.value)}
          />
        </div>

        <p className="self-end pb-2 text-sm text-muted-foreground">
          Quantity = Lay Quantity × Ratio (live update, e.g. 40 × 3 = 120)
        </p>
      </div>
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
              <TableHead />
            </TableRow>
            <TableRow className="bg-muted/40">
              <TableHead colSpan={3} className="text-right">
                Ratio
              </TableHead>
              {sizes.map((sz) => (
                <TableHead key={sz} className="p-1">
                  <Input
                    inputMode="numeric"
                    className="w-20 text-center"
                    value={ratios[sz] ?? ''}
                    onChange={(e) => handleRatioChange(sz, e.target.value)}
                  />
                </TableHead>
              ))}
              <TableHead colSpan={2} />
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((r, idx) => (
              <TableRow key={`${r.country}-${r.item_name}-${r.color_name}`}>
                <TableCell>{r.country}</TableCell>
                <TableCell>{r.item_name}</TableCell>
                <TableCell>{r.color_name}</TableCell>
                {sizes.map((sz) => (
                  <TableCell key={sz} className="p-1">
                    <Input
                      inputMode="numeric"
                      className="w-20 text-center"
                      value={r.quantities[sz] ?? ''}
                      disabled={!r.available.includes(sz)}
                      onChange={(e) => setQty(idx, sz, e.target.value)}
                    />
                  </TableCell>
                ))}
                <TableCell className="text-right font-medium">{rowTotal(r)}</TableCell>
                <TableCell className="p-1">
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => applyToRow(idx)}
                  >
                    Apply
                  </Button>
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
                <TableCell key={sz} className="text-center font-semibold">
                  {colTotal(sz)}
                </TableCell>
              ))}
              <TableCell className="text-right font-bold">{grandTotal}</TableCell>
              <TableCell />
            </TableRow>
          </TableFooter>
        </Table>
      </div>
    </div>
  );
}
