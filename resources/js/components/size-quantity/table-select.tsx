import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import type { TableOption } from '@/types/size-quantity';

type Props = {
  tables: TableOption[];
  value: string;
  onChange: (value: string) => void;
  error?: string;
};

export default function TableSelect({ tables, value, onChange, error }: Props) {
  return (
    <div className="grid gap-2">
      <Label>Table</Label>
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger className="w-56">
          <SelectValue placeholder="Select table" />
        </SelectTrigger>
        <SelectContent>
          {tables.map((t) => (
            <SelectItem key={t.id} value={String(t.id)}>
              {t.name}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {error && <p className="text-sm text-destructive">{error}</p>}
    </div>
  );
}
