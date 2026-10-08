import { type FormEvent, useEffect, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';

import MarkerPlanController from '@/actions/App/Http/Controllers/MarkerPlanController';
import MarkerPlanOptionController from '@/actions/App/Http/Controllers/MarkerPlanOptionController';
import PivotForm from '@/components/marker-plan//pivot-form';
import StatusSelect from '@/components/marker-plan/status-select';
import TableSelect from '@/components/marker-plan/table-select';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import type {
    FormShape,
    Meta,
    PivotChange,
    PivotRow,
    StatusOption,
    TableOption,
} from '@/types/marker-plan';

type PageProps = {
    skclNo: string;
    colorName: string;
    tables: TableOption[];
    statuses: StatusOption[];
    found: boolean | null;
    meta: Meta | null;
    sizes: string[];
    rows: PivotRow[];
};

const defaultRatios = (sizes: string[]): Record<string, string> =>
    Object.fromEntries(sizes.map((s) => [s, '1']));

// Built by hand on purpose: the SKCL contains "/" and must not be URL-encoded
const colorOptionsUrl = (skcl: string): string => `/marker-plan-options/colors/${skcl}`;

export default function Create({
    skclNo,
    colorName,
    tables,
    statuses,
    found,
    meta,
    sizes,
    rows,
}: PageProps) {
    const [skcl, setSkcl] = useState<string>(skclNo ?? '');
    const [color, setColor] = useState<string>(colorName ?? '');

    const { data, setData, post, processing, errors } = useForm<FormShape>({
        skcl_no: skclNo ?? '',
        table_no_id: '',
        status: statuses[0]?.value ?? 'draft',
        fixed_qty: '',
        ratios: defaultRatios(sizes),
        rows: rows ?? [],
    });

    // Reset the form only when the loaded rows really change (typed input survives validation errors)
    const rowsSig = JSON.stringify(rows ?? []);

    useEffect(() => {
        setData((d) => ({
            skcl_no: skclNo ?? '',
            table_no_id: d.table_no_id, // keep the selected table and status across searches
            status: d.status,
            fixed_qty: '',
            ratios: defaultRatios(sizes),
            rows: rows ?? [],
        }));
        setSkcl(skclNo ?? '');
        setColor(colorName ?? '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [skclNo, colorName, rowsSig]);

    const handleSkclChange = (value: string) => {
        setSkcl(value);
        setColor(''); // the color list depends on the SKCL
    };

    const handleSearch = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!skcl) return;

        router.get(
            MarkerPlanController.create.url(),
            { skcl_no: skcl, color_name: color },
            { preserveState: true },
        );
    };

    const handlePivotChange = (next: PivotChange) =>
        setData((d) => ({
            ...d,
            rows: next.rows,
            fixed_qty: next.fixedQty,
            ratios: next.ratios,
        }));

    // Whether the selected status locks the plan after saving (for example Approved)
    const lockOnSave = statuses.find((s) => s.value === data.status)?.locked ?? false;

    const handleSave = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        if (
            lockOnSave &&
            !window.confirm(
                'Saving with this status locks the marker plan(s). They can no longer be edited or deleted. Continue?',
            )
        ) {
            return;
        }

        post(MarkerPlanController.store.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="New Marker Plan" />
            <div className="mx-auto max-w-6xl space-y-6 p-6">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-3">
                        <CardTitle>New Marker Plan</CardTitle>
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
                                Search
                            </Button>
                        </form>

                        {found === false && (
                            <p className="mt-3 text-sm text-destructive">
                                No data found for this SKCL No / Color.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {found && meta && (
                    <form onSubmit={handleSave}>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    SKCL: {skclNo} | File: {meta.file_no} | Buyer: {meta.buyer ?? '-'} | Style:{' '}
                                    {meta.style}
                                    {colorName && ` | Color: ${colorName}`}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <PivotForm
                                    key={`${skclNo}|${colorName}`}
                                    sizes={sizes}
                                    rows={data.rows}
                                    fixedQty={data.fixed_qty}
                                    ratios={data.ratios}
                                    onChange={handlePivotChange}
                                >
                                    <TableSelect
                                        tables={tables}
                                        value={data.table_no_id}
                                        onChange={(v) => setData('table_no_id', v)}
                                        error={errors.table_no_id}
                                    />
                                    <StatusSelect
                                        statuses={statuses}
                                        value={data.status}
                                        onChange={(v) => setData('status', v)}
                                        error={errors.status}
                                    />
                                </PivotForm>

                                {errors.rows && <p className="text-sm text-destructive">{errors.rows}</p>}
                                {errors.fixed_qty && (
                                    <p className="text-sm text-destructive">{errors.fixed_qty}</p>
                                )}

                                {lockOnSave && (
                                    <p className="text-sm text-amber-600">
                                        ⚠ Saving with this status locks the marker plan(s). They can no longer be
                                        edited or deleted.
                                    </p>
                                )}

                                <div className="flex flex-wrap items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving...' : 'Save Marker Plan'}
                                    </Button>
                                    <p className="text-sm text-muted-foreground">
                                        A separate marker plan (with its own ref) is created for every row that has a
                                        quantity.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                )}
            </div>
        </>
    );
}
