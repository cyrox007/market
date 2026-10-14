import { Checkbox } from '../../../../components/ui/primitives';

interface ManufacturerListProps {
  options: { slug: string; name: string; count?: number }[];
  value: string[];
  onChange: (slugs: string[]) => void;
}

export default function ManufacturerList({ options, value, onChange }: ManufacturerListProps) {
  const toggle = (slug: string, checked: boolean) => {
    if (!checked) return onChange(value.filter((v) => v !== slug));
    onChange([...value, slug]);
  };

  return (
    <ul className="flex flex-col gap-3">
      {options.map((o) => (
        <li key={o.slug} className="flex">
          <Checkbox
            label={o.name}
            checked={value.includes(o.slug)}
            disabled={o.count === 0}
            onChange={(checked) => toggle(o.slug, checked)}
          />
        </li>
      ))}
    </ul>
  );
}
