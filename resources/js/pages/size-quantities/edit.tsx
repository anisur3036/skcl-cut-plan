import { type FormEvent, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

import SizeQuantityController from '@/actions/App/Http/Controllers/SizeQuantityController';
import PivotForm from './pivot-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { FormShape, Meta, PivotRow } from '@/types/size-quantity';

type PageProps = {
    refNo: string;
    skclNo: string;
    meta: Meta;
    sizes: string[];
    rows: PivotRow[];
};

export default function Edit({ refNo, skclNo, meta, sizes, rows }: PageProps) {
    const { data, setData, put, processing, errors } = useForm<FormShape>({
        skcl_no: skclNo,
        rows,
    });

    const rowsSig = JSON.stringify(rows);

    useEffect(() => {
        setData({ skcl_no: skclNo, rows });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [skclNo, refNo, rowsSig]);

    const handleSave = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        put(SizeQuantityController.update.url({ ref: refNo }), { preserveScroll: true });
    };

    return (
        <>
            <Head title={`Edit ${refNo}`} />
            <div className="mx-auto max-w-6xl space-y-6 p-6">
                <form onSubmit={handleSave}>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between gap-3">
                            <CardTitle className="text-base">
                                Edit Ref: {refNo} | SKCL: {skclNo} | File: {meta.file_no} | Order:{' '}
                                {meta.order_no} | Style: {meta.style_no}
                            </CardTitle>
                            <Button asChild variant="outline" size="sm">
                                <Link href={SizeQuantityController.index.url()}>← Back to List</Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <PivotForm
                                key={refNo}
                                sizes={sizes}
                                rows={data.rows}
                                onRowsChange={(next) => setData('rows', next)}
                            />

                            {errors.rows && <p className="text-sm text-destructive">{errors.rows}</p>}
                            {!errors.rows && Object.keys(errors).length > 0 && (
                                <p className="text-sm text-destructive">
                                    There are errors in the input. Please check the numbers.
                                </p>
                            )}

                            <Button type="submit" disabled={processing}>
                                {processing ? 'Updating...' : 'Update'}
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}
