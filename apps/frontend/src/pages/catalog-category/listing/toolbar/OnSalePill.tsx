import { SwitchTrack } from '../../../../components/ui/primitives';
import FilterPill from './FilterPill';

interface OnSalePillProps {
  checked: boolean;
  onChange: (checked: boolean) => void;
}

/** «По акции» в строке: тумблер слева, применяется сразу (market-docs/44, Figma 721:46062) */
export default function OnSalePill({ checked, onChange }: OnSalePillProps) {
  return (
    <FilterPill
      role="switch"
      aria-checked={checked}
      chevron={false}
      icon={<SwitchTrack checked={checked} />}
      onClick={() => onChange(!checked)}
    >
      По акции
    </FilterPill>
  );
}
