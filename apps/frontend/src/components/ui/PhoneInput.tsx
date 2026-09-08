import React, { useEffect } from 'react';
import { useIMask } from 'react-imask';

interface PhoneInputProps extends Omit<
  React.InputHTMLAttributes<HTMLInputElement>,
  'value' | 'onChange'
> {
  value: string;
  onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
}

export default function PhoneInput({ value, onChange, className, ...props }: PhoneInputProps) {
  const { ref, setValue } = useIMask(
    {
      mask: '+{7} (000) 000-00-00',
      lazy: false,
    },
    {
      onAccept: (maskedValue: string) => {
        // Создаем синтетическое событие для совместимости с существующим кодом
        const syntheticEvent = {
          target: {
            value: maskedValue,
            name: props.name || 'phone',
          },
          currentTarget: {
            value: maskedValue,
            name: props.name || 'phone',
          },
        } as React.ChangeEvent<HTMLInputElement>;

        onChange(syntheticEvent);
      },
    },
  );

  // Синхронизируем внешнее value с внутренним состоянием маски
  useEffect(() => {
    if (value !== undefined) {
      const currentValue = (ref.current as any)?.value || '';
      if (currentValue !== value) {
        setValue(value || '');
      }
    }
  }, [value, setValue, ref]);

  return (
    <input
      ref={ref as React.RefObject<HTMLInputElement>}
      type="tel"
      className={className}
      {...props}
    />
  );
}
