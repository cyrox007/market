import Button from '../../components/ui/primitives/Button';

export function AccountDataError({ label, onRetry }: { label: string; onRetry: () => unknown }) {
  return (
    <div role="alert" className="mb-4 flex flex-wrap items-center gap-3 text-sm text-brand-red">
      <span>{label}</span>
      <Button variant="outline" onClick={() => { void onRetry(); }}>Повторить</Button>
    </div>
  );
}
