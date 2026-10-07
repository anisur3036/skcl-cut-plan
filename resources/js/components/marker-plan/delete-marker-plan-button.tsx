import { router } from '@inertiajs/react';
import { useState } from 'react';

import MarkerPlanController from '@/actions/App/Http/Controllers/MarkerPlanController';
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
  planId: number;
  refNo: string;
};

export default function DeleteMarkerPlanButton({ planId, refNo }: Props) {
  const [processing, setProcessing] = useState<boolean>(false);

  const handleDelete = () => {
    router.delete(MarkerPlanController.destroy.url(planId), {
      preserveScroll: true,
      onStart: () => setProcessing(true),
      onFinish: () => setProcessing(false),
    });
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
            Ref {refNo} এবং এর সব size-quantity স্থায়ীভাবে মুছে যাবে। এটি আর ফেরানো যাবে না।
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
