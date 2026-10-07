import { router } from '@inertiajs/react';
import { useState } from 'react';

import SizeQuantityController from '@/actions/App/Http/Controllers/SizeQuantityController';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button, buttonVariants } from '@/components/ui/button';

type Props = {
  refNo: string;
  skclNo: string;
};

export default function DeleteRefButton({ refNo, skclNo }: Props) {
  const [processing, setProcessing] = useState<boolean>(false);

  const handleDelete = () => {
    router.delete(
      SizeQuantityController.destroy.url({ ref: refNo }, { query: { skcl_no: skclNo } }),
      {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
      },
    );
  };

  return (
    <AlertDialog>
      <AlertDialogTrigger asChild>
        <Button type="button" size="sm" variant="destructive" disabled={processing}>
          Delete
        </Button>
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete this marker plan?</AlertDialogTitle>
          <AlertDialogDescription>
            Ref {refNo} (SKCL {skclNo}) এবং এর সব quantity স্থায়ীভাবে মুছে যাবে। এটি আর ফেরানো
            যাবে না।
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction
            className={buttonVariants({ variant: 'destructive' })}
            onClick={handleDelete}
          >
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
