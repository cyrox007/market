import { AccordionSection, Checkbox, Input } from '../../../components/ui/primitives';
import type { WarehouseOption } from '../../../lib/api';
import CheckoutSelect from './CheckoutSelect';

interface Props {
  services: WarehouseOption['service_options'];
  assembly: boolean;
  setAssembly: (value: boolean) => void;
  handling: number | null;
  setHandling: (value: number | null) => void;
  floor: number;
  setFloor: (value: number) => void;
  additional: number[];
  setAdditional: (value: number[]) => void;
}

const priceLabel = (price: number | null) =>
  price == null
    ? 'Стоимость уточняется'
    : price === 0
      ? 'Бесплатно'
      : `${price.toLocaleString('ru-RU')} ₽`;

export default function CheckoutServices(c: Props) {
  const { services } = c;
  const selectedHandling = services.handling.find((option) => option.id === c.handling);
  if (
    !services.assembly.available &&
    !services.assembly.required &&
    !services.handling.length &&
    !services.additional.length
  )
    return null;
  return (
    <section aria-label="Дополнительные услуги">
      <AccordionSection title="Дополнительные услуги">
        <div className="space-y-4 pt-2">
          {(services.assembly.available || services.assembly.required) && (
            <Checkbox
              className="w-full !items-start"
              checked={c.assembly || services.assembly.required}
              disabled={services.assembly.required}
              onChange={c.setAssembly}
              label={
                <span className="flex flex-wrap justify-between gap-x-4 gap-y-1">
                  <span>
                    Сборка мебели
                    {services.assembly.required && (
                      <span className="block text-12 text-ink-secondary">Обязательная</span>
                    )}
                  </span>
                  <span>{priceLabel(services.assembly.price)}</span>
                </span>
              }
            />
          )}
          {!!services.handling.length && (
            <div className="space-y-3">
              <Checkbox
                label="Подъём / занос"
                checked={!!selectedHandling}
                onChange={(checked) => c.setHandling(checked ? services.handling[0].id : null)}
              />
              {selectedHandling && (
                <div className="space-y-3 pl-[30px]">
                  <CheckoutSelect
                    label="Вариант подъёма"
                    value={String(selectedHandling.id)}
                    onChange={(value) => c.setHandling(Number(value))}
                    options={services.handling.map((option) => ({
                      value: String(option.id),
                      label: option.name,
                    }))}
                  />
                  {selectedHandling.requires_floor && (
                    <label className="block space-y-2 text-14 text-ink-secondary">
                      Этаж для подъёма
                      <Input
                        type="number"
                        min={1}
                        max={selectedHandling.max_floor}
                        value={c.floor}
                        onChange={(event) => c.setFloor(Number(event.target.value))}
                        shape="rounded"
                        tone="outline"
                      />
                    </label>
                  )}
                  <p className="text-12 text-ink-secondary">
                    Стоимость подъёма учитывается в расчёте доставки и услуг.
                  </p>
                </div>
              )}
            </div>
          )}
          {services.additional.map((service) => (
            <Checkbox
              key={service.id}
              className="w-full !items-start"
              checked={c.additional.includes(service.id)}
              onChange={(checked) =>
                c.setAdditional(
                  checked
                    ? [...c.additional, service.id]
                    : c.additional.filter((id) => id !== service.id),
                )
              }
              label={
                <span className="flex flex-wrap justify-between gap-x-4 gap-y-1">
                  <span>{service.name}</span>
                  <span>{priceLabel(service.price)}</span>
                </span>
              }
            />
          ))}
        </div>
      </AccordionSection>
    </section>
  );
}
