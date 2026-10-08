import { useState, useRef, useEffect } from 'react';

import { Check, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';

import { cn } from '@/lib/utils';

type Option = Record<string, any>;

type Props = {
    apiUrl: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;

    // ✅ NEW: dynamic keys
    labelKey?: string;   // default: name
    valueKey?: string;   // default: id
};

export function SearchableSelect({
    apiUrl,
    value,
    onChange,
    placeholder = 'Select option',
    labelKey = 'name',
    valueKey = 'id',
}: Props) {

    const [open, setOpen] = useState(false);
    const [options, setOptions] = useState<Option[]>([]);
    const [loading, setLoading] = useState(false);

    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        fetchInitialData();
    }, [apiUrl]);

    async function fetchInitialData() {
        setLoading(true);

        try {
            const response = await fetch(`${apiUrl}?limit=20`);
            let data = await response.json();

            const isValueInOptions = data.some(
                (o: Option) =>
                    String(o[valueKey]) === String(value)
            );

            if (value && !isValueInOptions) {
                const singleRes = await fetch(`${apiUrl}/${value}`);

                if (singleRes.ok) {
                    const singleData = await singleRes.json();
                    data = [singleData, ...data];
                }
            }

            setOptions(data);

        } catch (error) {
            console.error("Initial load error:", error);

        } finally {
            setLoading(false);
        }
    }

    const selected = options.find(
        (o) => String(o[valueKey]) === String(value)
    );

    function handleSelect(selectedValue: string) {
        onChange(selectedValue === value ? '' : selectedValue);
        setOpen(false);
    }

    async function searchBackend(term: string) {

        if (debounceRef.current)
            clearTimeout(debounceRef.current);

        if (!term) {
            fetchInitialData();
            return;
        }

        debounceRef.current = setTimeout(async () => {

            if (term.length < 2) return;

            setLoading(true);

            try {

                const response = await fetch(
                    `${apiUrl}?search=${encodeURIComponent(term)}`
                );

                const data = await response.json();

                setOptions(data);

            } catch (error) {

                console.error('Search error:', error);

            } finally {

                setLoading(false);
            }

        }, 300);
    }

    return (

        <Popover open={open} onOpenChange={setOpen}>

            <PopoverTrigger asChild>

                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className={cn(
                        'w-full justify-between font-normal',
                        !selected && 'text-muted-foreground'
                    )}
                >

                    {selected
                        ? selected[labelKey]
                        : placeholder}

                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />

                </Button>

            </PopoverTrigger>

            <PopoverContent
                className="w-[--radix-popover-trigger-width] p-0"
                align="start"
            >

                <Command>

                    <CommandInput
                        placeholder="Search..."
                        onValueChange={searchBackend}
                    />

                    <CommandList>

                        {loading && (
                            <div className="p-2 text-sm text-muted-foreground">
                                Loading...
                            </div>
                        )}

                        <CommandEmpty>
                            No results found.
                        </CommandEmpty>

                        <CommandGroup>

                            {options.map((option) => (

                                <CommandItem
                                    key={option[valueKey]}
                                    value={option[labelKey]}
                                    onSelect={() =>
                                        handleSelect(
                                            String(option[valueKey])
                                        )
                                    }
                                >

                                    <Check
                                        className={cn(
                                            'mr-2 h-4 w-4',
                                            String(option[valueKey]) ===
                                            String(value)
                                                ? 'opacity-100'
                                                : 'opacity-0'
                                        )}
                                    />

                                    {option[labelKey]}

                                </CommandItem>

                            ))}

                        </CommandGroup>

                    </CommandList>

                </Command>

            </PopoverContent>

        </Popover>
    );
}