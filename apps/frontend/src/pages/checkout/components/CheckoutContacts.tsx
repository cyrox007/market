import Input from '../../../components/ui/primitives/Input';

export interface CheckoutContact {
  lastName: string;
  firstName: string;
  middleName: string;
  phone: string;
  email: string;
}
export function CheckoutContacts({
  value,
  onChange,
}: {
  value: CheckoutContact;
  onChange: (value: CheckoutContact) => void;
}) {
  const fields = [
    ['lastName', 'Фамилия', 'family-name', 'text'],
    ['firstName', 'Имя', 'given-name', 'text'],
    ['middleName', 'Отчество', 'additional-name', 'text'],
    ['phone', 'Телефон', 'tel', 'tel'],
    ['email', 'Почта', 'email', 'email'],
  ] as const;
  return (
    <section aria-labelledby="checkout-contacts" className="space-y-4">
      <h2 id="checkout-contacts" className="text-20 font-semibold max-vsm:text-16">
        Личные данные
      </h2>
      <div className="space-y-2">
        {fields.map(([key, label, autoComplete, type]) => (
          <Input
            key={key}
            aria-label={label}
            name={key}
            placeholder={`${label}${key === 'middleName' ? '' : '*'}`}
            required={key !== 'middleName'}
            autoComplete={autoComplete}
            type={type}
            shape="rounded"
            inputSize="field"
            tone="outline"
            wrapperClassName="max-vsm:h-[41px]"
            value={value[key]}
            onChange={(event) => onChange({ ...value, [key]: event.target.value })}
          />
        ))}
      </div>
    </section>
  );
}
