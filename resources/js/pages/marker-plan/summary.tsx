import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';

import MarkerPlanController from '@/actions/App/Http/Controllers/MarkerPlanController';
import MarkerPlanOptionController from '@/actions/App/Http/Controllers/MarkerPlanOptionController';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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


type SummaryInfo = {
  skcl_no: string;
  file_no: string;
  buyer: string | null;
  style: string;
  shipment: string | null;
  plan_count: number;
  active_plan_count: number;
  total_qty: number;
};

type SummaryRow = {
  order_id: number;
  item_name: string;
  color_name: string;
  plans: number;
  quantities: Record<string, number>;
  total: number;
};

type PageProps = {
  skclNo: string;
  colorName: string;
  found: boolean | null;
  info: SummaryInfo | null;
  sizes: string[];
  rows: SummaryRow[];
  totals: { by_size: Record<string, number>; grand: number } | null;
};

// Built by hand on purpose: the SKCL contains "/" and must not be URL-encoded
const colorOptionsUrl = (skcl: string): string => `/marker-plan-options/colors/${skcl}`;


function InfoItem({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="space-y-1">
      <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
      <dd className="font-medium">{children}</dd>
    </div>
  );
}

const cell = (n: number): string | number => (n === 0 ? '-' : n);

export default function Summary({ skclNo, colorName, found, info, sizes, rows, totals }: PageProps) {
  const [skcl, setSkcl] = useState<string>(skclNo ?? '');
  const [color, setColor] = useState<string>(colorName ?? '');

  useEffect(() => {
    setSkcl(skclNo ?? '');
    setColor(colorName ?? '');
  }, [skclNo, colorName]);

  const handleSkclChange = (value: string) => {
    setSkcl(value);
    setColor(''); // the color list depends on the SKCL
  };

  const handleSearch = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!skcl) return;

    router.get(
      MarkerPlanController.summary.url(),
      { skcl_no: skcl, color_name: color },
      { preserveState: true },
    );
  };

  const planTotal = rows.reduce((sum, r) => sum + r.plans, 0);

  return (
    <>
      <Head title="Marker Plan Summary" />
      <div className="mx-auto max-w-6xl space-y-6 p-6">
        {/* Filter */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-3">
            <CardTitle>Marker Plan Summary</CardTitle>
            <Button asChild variant="outline" size="sm">
              <Link href={MarkerPlanController.index.url()}>← Back to List</Link>
            </Button>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSearch} className="flex flex-wrap items-end gap-3">
              <div className="grid w-64 gap-2">
                <Label>SKCL No</Label>
                <SearchableSelect
                  apiUrl={MarkerPlanOptionController.skcl.url()}
                  value={skcl}
                  onChange={handleSkclChange}
                  placeholder="Select SKCL No"
                />
              </div>

              <div className="grid w-56 gap-2">
                <Label>Color (optional)</Label>
                {skcl ? (
                  <SearchableSelect
                    key={skcl}
                    apiUrl={colorOptionsUrl(skcl)}
                    value={color}
                    onChange={setColor}
                    placeholder="All colors"
                  />
                ) : (
                  <Button
                    type="button"
                    variant="outline"
                    disabled
                    className="w-full justify-between font-normal text-muted-foreground"
                  >
                    Select SKCL first
                  </Button>
                )}
              </div>

              <Button type="submit" disabled={!skcl}>
                Show
              </Button>
            </form>

            {found === null && (
              <p className="mt-3 text-sm text-muted-foreground">
                Select an SKCL No (and optionally a color) to see its marker plans.
              </p>
            )}
            {found === false && (
              <p className="mt-3 text-sm text-destructive">
                No order found for this SKCL No / Color.
              </p>
            )}
          </CardContent>
        </Card>

        {found && info && (
          <>
            {/* Header: marker plan information */}
            <Card>
              <CardHeader className="flex flex-row items-center justify-between gap-3">
                <CardTitle className="text-base">Marker Plan Information</CardTitle>
                <Button asChild variant="outline" size="sm">
                  <Link href={MarkerPlanController.index.url({ query: { search: info.skcl_no } })}>
                    Open plans list
                  </Link>
                </Button>
              </CardHeader>
              <CardContent>
                <dl className="grid grid-cols-2 gap-x-6 gap-y-4 text-sm md:grid-cols-4">
                  <InfoItem label="SKCL No">{info.skcl_no}</InfoItem>
                  <InfoItem label="File No">{info.file_no || '-'}</InfoItem>
                  <InfoItem label="Buyer">{info.buyer ?? '-'}</InfoItem>
                  <InfoItem label="Style">{info.style || '-'}</InfoItem>
                  <InfoItem label="Shipment">{info.shipment ?? '-'}</InfoItem>
                  <InfoItem label="Color">{colorName || 'All colors'}</InfoItem>
                  <InfoItem label="Approved marker plans">{info.plan_count}</InfoItem>
                  <InfoItem label="Total approved qty">{info.total_qty}</InfoItem>
                </dl>
              </CardContent>
            </Card>

            {/* Detail: color wise size pivot (quantity only) */}
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Color wise Quantity</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {sizes.length === 0 || !totals ? (
                  <p className="text-sm text-muted-foreground">
                    No marker plan quantities to show for this selection.
                  </p>
                ) : (
                  <div className="overflow-x-auto rounded-md border">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Item</TableHead>
                          <TableHead>Color</TableHead>
                          <TableHead className="text-center">Plans</TableHead>
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
                          <TableRow key={r.order_id}>
                            <TableCell>{r.item_name}</TableCell>
                            <TableCell>{r.color_name}</TableCell>
                            <TableCell className="text-center">{cell(r.plans)}</TableCell>
                            {sizes.map((sz) => (
                              <TableCell key={sz} className="text-center">
                                {cell(r.quantities[sz] ?? 0)}
                              </TableCell>
                            ))}
                            <TableCell className="text-right font-medium">
                              {r.total}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                      <TableFooter>
                        <TableRow>
                          <TableCell colSpan={2} className="font-semibold">
                            Total
                          </TableCell>
                          <TableCell className="text-center font-semibold">
                            {planTotal}
                          </TableCell>
                          {sizes.map((sz) => (
                            <TableCell key={sz} className="text-center font-semibold">
                              {totals.by_size[sz] ?? 0}
                            </TableCell>
                          ))}
                          <TableCell className="text-right font-bold">
                            {totals.grand}
                          </TableCell>
                        </TableRow>
                      </TableFooter>
                    </Table>
                  </div>
                )}
                <p className="text-xs text-muted-foreground">
                  Cancelled marker plans are not counted in the quantities.
                </p>
              </CardContent>
            </Card>
          </>
        )}
      </div>
    </>
  );
}
