import { type FormEvent, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';

import SizeQuantityController from '@/actions/App/Http/Controllers/SizeQuantityController';
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
import type { Paginated, RefListItem } from '@/types/size-quantity';

type PageProps = {
  refs: Paginated<RefListItem>;
  search: string;
  highlight: string;
};

export default function Index({ refs, search, highlight }: PageProps) {
  const [term, setTerm] = useState<string>(search ?? '');

  const handleSearch = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    router.get(
      SizeQuantityController.index.url(),
      { search: term.trim() },
      { preserveState: true },
    );
  };

  const goTo = (url: string | null) => {
    if (url) router.get(url, {}, { preserveScroll: true });
  };

  return (
    <>
      <Head title="Size Quantity List" />
      <div className="mx-auto max-w-6xl space-y-6 p-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-3">
            <CardTitle>Size Quantity References</CardTitle>
            <Button asChild>
              <Link href={SizeQuantityController.create.url()}>New Entry</Link>
            </Button>
          </CardHeader>
          <CardContent className="space-y-4">
            {highlight && (
              <p className="text-sm text-green-600">Saved ✔ Ref: {highlight}</p>
            )}

            <form onSubmit={handleSearch} className="flex gap-2">
              <Input
                placeholder="Search by Ref No or SKCL No"
                value={term}
                onChange={(e) => setTerm(e.target.value)}
                className="w-72"
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
                    <TableHead>File</TableHead>
                    <TableHead>Order</TableHead>
                    <TableHead>Style</TableHead>
                    <TableHead>Table</TableHead>
                    <TableHead className="text-right">Fixed Qty</TableHead>
                    <TableHead>Colors</TableHead>
                    <TableHead className="text-right">Total Qty</TableHead>
                    <TableHead>Created</TableHead>
                    <TableHead>Updated</TableHead>
                    <TableHead />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {refs.data.length === 0 && (
                    <TableRow>
                      <TableCell
                        colSpan={10}
                        className="py-6 text-center text-muted-foreground"
                      >
                        No references found.
                      </TableCell>
                    </TableRow>
                  )}
                  {refs.data.map((r) => (
                    <TableRow
                      key={`${r.ref_no}-${r.skcl_no}`}
                      className={r.ref_no === highlight ? 'bg-muted/60' : ''}
                    >
                      <TableCell className="font-medium">{r.ref_no}</TableCell>
                      <TableCell>{r.skcl_no}</TableCell>
                      <TableCell>{r.file_no}</TableCell>
                      <TableCell>{r.order_no}</TableCell>
                      <TableCell>{r.style_no}</TableCell>
                      <TableCell>{r.table_name ?? '-'}</TableCell>
                      <TableCell className="text-right">{r.fixed_qty ?? '-'}</TableCell>
                      <TableCell>{r.colors}</TableCell>
                      <TableCell className="text-right">{r.total_qty}</TableCell>
                      <TableCell>{r.created_at}</TableCell>
                      <TableCell>{r.updated_at}</TableCell>
                      <TableCell className="text-right">
                        <Button asChild size="sm" variant="outline">
                          <Link
                            href={SizeQuantityController.edit.url(
                              { ref: r.ref_no },
                              { query: { skcl_no: r.skcl_no } },
                            )}
                          >
                            Edit
                          </Link>
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            <div className="flex items-center justify-between text-sm">
              <span className="text-muted-foreground">
                Page {refs.current_page} of {refs.last_page} · {refs.total} total
              </span>
              <div className="flex gap-2">
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={!refs.prev_page_url}
                  onClick={() => goTo(refs.prev_page_url)}
                >
                  Previous
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={!refs.next_page_url}
                  onClick={() => goTo(refs.next_page_url)}
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
