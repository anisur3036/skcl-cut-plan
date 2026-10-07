import { type FormEvent, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';

import MarkerPlanController from '@/actions/App/Http/Controllers/MarkerPlanController';
import DeleteMarkerPlanButton from '@/components/marker-plan/delete-marker-plan-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import type { MarkerPlanListItem, Paginated, StatusOption } from '@/types/marker-plan';

type PageProps = {
  plans: Paginated<MarkerPlanListItem>;
  statuses: StatusOption[];
  search: string;
  highlight: string[];
  deleted: string;
  error: string | null;
};

const statusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
  if (status === 'completed') return 'default';
  if (status === 'approved') return 'secondary';
  if (status === 'cancelled') return 'destructive';
  return 'outline';
};

export default function Index({ plans, statuses, search, highlight, deleted, error }: PageProps) {
  const [term, setTerm] = useState<string>(search ?? '');

  const statusLabel = (value: string) => statuses.find((s) => s.value === value)?.label ?? value;

  const handleSearch = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    router.get(MarkerPlanController.index.url(), { search: term.trim() }, { preserveState: true });
  };

  const goTo = (url: string | null) => {
    if (url) router.get(url, {}, { preserveScroll: true });
  };

  return (
    <>
      <Head title="Marker Plans" />
      <div className="mx-auto max-w-7xl space-y-6 p-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-3">
            <CardTitle>Marker Plans</CardTitle>
            <Button asChild>
              <Link href={MarkerPlanController.create.url()}>New Marker Plan</Link>
            </Button>
          </CardHeader>
          <CardContent className="space-y-4">
            {highlight.length > 0 && (
              <p className="text-sm text-green-600">
                Saved ✔ Ref: {highlight.join(', ')}
              </p>
            )}
            {deleted && (
              <p className="text-sm text-destructive">Deleted ✔ Ref: {deleted}</p>
            )}

            {error && <p className="text-sm text-destructive">{error}</p>}

            <form onSubmit={handleSearch} className="flex gap-2">
              <Input
                placeholder="Search Ref, SKCL, Country, Item, Color or Table"
                value={term}
                onChange={(e) => setTerm(e.target.value)}
                className="w-96"
              />
              <Button type="submit" variant="secondary">
                Search
              </Button>
            </form>

            <div className="overflow-x-auto rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Ref No</TableHead>
                    <TableHead>SKCL No</TableHead>
                    <TableHead>Order</TableHead>
                    <TableHead>Style</TableHead>
                    <TableHead>Country</TableHead>
                    <TableHead>Item</TableHead>
                    <TableHead>Color</TableHead>
                    <TableHead>Table</TableHead>
                    <TableHead className="text-right">Lay Qty</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Total Qty</TableHead>
                    <TableHead>Created</TableHead>
                    <TableHead />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {plans.data.length === 0 && (
                    <TableRow>
                      <TableCell
                        colSpan={13}
                        className="py-6 text-center text-muted-foreground"
                      >
                        No marker plans found.
                      </TableCell>
                    </TableRow>
                  )}
                  {plans.data.map((p) => (
                    <TableRow
                      key={p.id}
                      className={highlight.includes(p.ref_no) ? 'bg-muted/60' : ''}
                    >
                      <TableCell className="font-medium whitespace-nowrap">
                        {p.ref_no}
                      </TableCell>
                      <TableCell>{p.skcl_no}</TableCell>
                      <TableCell>{p.order_no}</TableCell>
                      <TableCell>{p.style_no}</TableCell>
                      <TableCell>{p.country}</TableCell>
                      <TableCell>{p.item_name}</TableCell>
                      <TableCell>{p.color_name}</TableCell>
                      <TableCell>{p.table_name ?? '-'}</TableCell>
                      <TableCell className="text-right">{p.fixed_qty ?? '-'}</TableCell>
                      <TableCell>
                        <Badge variant={statusVariant(p.status)}>
                          {statusLabel(p.status)}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">{p.total_qty}</TableCell>
                      <TableCell className="whitespace-nowrap">{p.created_at}</TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-2">
                          {p.locked ? (
                            <span title="Approved হওয়ায় edit করা যায় না">
                              <Button type="button" size="sm" variant="outline" disabled>
                                Edit
                              </Button>
                            </span>
                          ) : (
                            <Button asChild size="sm" variant="outline">
                              <Link href={MarkerPlanController.edit.url(p.id)}>Edit</Link>
                            </Button>
                          )}

                          <Button asChild size="sm" variant="outline">
                            <a href={MarkerPlanController.pdf.url(p.id)} target="_blank" rel="noreferrer">
                              PDF
                            </a>
                          </Button>

                          {p.locked ? (
                            <span title="Approved হওয়ায় delete করা যায় না">
                              <Button type="button" size="sm" variant="destructive" disabled>
                                Delete
                              </Button>
                            </span>
                          ) : (
                            <DeleteMarkerPlanButton planId={p.id} refNo={p.ref_no} />
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            <div className="flex items-center justify-between text-sm">
              <span className="text-muted-foreground">
                Page {plans.current_page} of {plans.last_page} · {plans.total} total
              </span>
              <div className="flex gap-2">
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={!plans.prev_page_url}
                  onClick={() => goTo(plans.prev_page_url)}
                >
                  Previous
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={!plans.next_page_url}
                  onClick={() => goTo(plans.next_page_url)}
                >
                  Next
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  );
}
