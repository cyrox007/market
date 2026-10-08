import { Checkbox } from '../../../../components/ui/primitives';

export interface CheckboxOption {
  slug: string;
  name: string;
  /** 0 — товаров с этим значением нет: галочка выключена */
  count?: number;
}

interface CheckboxListProps {
  options: CheckboxOption[];
  value: string[];
  onChange: (slugs: string[]) => void;
}

/** Галочки раздела: через 12, выбор нескольких (Figma 730:53152) */
export default function CheckboxList({ options, value, onChange }: CheckboxListProps) {
  return (
    <ul className="flex flex-col gap-3">
      {options.map((o) => (
        <li key={o.slug} className="flex">
          <Checkbox
            label={o.name}
            checked={value.includes(o.slug)}
            disabled={o.count === 0 && !value.includes(o.slug)}
            onChange={(checked) =>
              onChange(checked ? [...value, o.slug] : value.filter((v) => v !== o.slug))
            }
          />
        </li>
      ))}
    </ul>
  );
}
