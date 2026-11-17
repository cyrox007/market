import { useEffect, useId, useRef, useState } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { Button, IconButton, Popover } from '../../components/ui/primitives';
import { dateKey } from './dateKey';

export interface DeliverySlot {
  id: string;
  start: string;
  end: string;
}
export interface DeliveryDay {
  date: string;
  slots: DeliverySlot[];
}
export interface DeliverySelection {
  date: string;
  slotId: string;
}

const parseDate = (value: string) => {
  const [year, month, day] = value.split('-').map(Number);
  return new Date(year, month - 1, day, 12);
};
const fullDate = (value: string) =>
  parseDate(value).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
const monthKey = (value: Date) => value.getFullYear() * 12 + value.getMonth();

/** Только dev-прототип. В рабочем checkout слотами пока не управляет API. */
export default function DeliverySchedule({
  days,
  value,
  onChange,
}: {
  days: DeliveryDay[];
  value: DeliverySelection;
  onChange: (value: DeliverySelection) => void;
}) {
  const [open, setOpen] = useState(false);
  const [month, setMonth] = useState(() => parseDate(value.date));
  const anchor = useRef<HTMLButtonElement>(null);
  const dayRow = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const row = dayRow.current;
    const selected = row?.querySelector<HTMLElement>('button[aria-pressed="true"]');
    if (!row || !selected) return;
    const bounds = row.getBoundingClientRect();
    const item = selected.getBoundingClientRect();
    if (item.right > bounds.right) row.scrollLeft += item.right - bounds.right;
    else if (item.left < bounds.left) row.scrollLeft -= bounds.left - item.left;
  }, [value.date]);
  const id = useId();
  const selectedDay = days.find((day) => day.date === value.date);
  const selectedSlot = selectedDay?.slots.find((slot) => slot.id === value.slotId);
  const quickDays = days.slice(0, 7);
  if (selectedDay && !quickDays.some((day) => day.date === selectedDay.date))
    quickDays.push(selectedDay);
  const start = new Date(month.getFullYear(), month.getMonth(), 1, 12);
  start.setDate(start.getDate() - ((start.getDay() + 6) % 7));
  const calendarDays = Array.from({ length: 42 }, (_, offset) => {
    const date = new Date(start);
    date.setDate(date.getDate() + offset);
    return date;
  });
  function choose(day: DeliveryDay) {
    if (!day.slots.length) return;
    onChange({
      date: day.date,
      slotId: day.slots.some((slot) => slot.id === value.slotId) ? value.slotId : day.slots[0].id,
    });
    setOpen(false);
    if (open) anchor.current?.focus();
  }
  function moveMonth(delta: number) {
    setMonth(new Date(month.getFullYear(), month.getMonth() + delta, 1, 12));
  }
  return (
    <div className="space-y-3">
      <p aria-label="Выбранная доставка" aria-live="polite" className="text-14">
        Доставим: {fullDate(value.date)}
        {selectedSlot && ` с ${selectedSlot.start} до ${selectedSlot.end}`}
      </p>
      <div
        ref={dayRow}
        className="scroll-hidden flex min-w-0 gap-3 overflow-x-auto overscroll-x-contain"
        role="group"
        aria-label="День доставки"
      >
        {quickDays.map((day) => (
          <Button
            key={day.date}
            size="sm"
            variant={
              day.date === value.date && days.slice(0, 7).some((item) => item.date === day.date)
                ? 'primary'
                : 'secondary'
            }
            disabled={!day.slots.length}
            aria-label={fullDate(day.date)}
            aria-pressed={day.date === value.date}
            onClick={() => choose(day)}
            className={`!h-[72px] !w-[42px] shrink-0 !px-0 ${day.date === value.date && !days.slice(0, 7).some((item) => item.date === day.date) ? 'border border-brand-yellow !bg-surface' : ''}`}
          >
            <span className="flex flex-col gap-2">
              <span className="text-12 font-medium uppercase">
                {parseDate(day.date).toLocaleDateString('ru-RU', { weekday: 'short' })}
              </span>
              <span className="text-14 font-semibold">{parseDate(day.date).getDate()}</span>
            </span>
          </Button>
        ))}
        <button
          ref={anchor}
          type="button"
          aria-label="Открыть календарь доставки"
          aria-expanded={open}
          aria-controls={id}
          aria-haspopup="dialog"
          onClick={() => {
            setMonth(parseDate(value.date));
            setOpen(!open);
          }}
          className="flex h-[72px] w-[42px] shrink-0 flex-col items-center justify-center gap-2 rounded-pill bg-surface-grey text-ink outline-none hover:bg-surface-border focus-visible:ring-2 focus-visible:ring-brand-green"
        >
          <span className="text-12 text-ink-secondary">…</span>
          <CalendarDays aria-hidden="true" className="size-5" />
        </button>
      </div>
      <div className="flex flex-wrap gap-3" role="group" aria-label="Интервал доставки">
        {selectedDay?.slots.map((slot) => (
          <Button
            key={slot.id}
            size="sm"
            variant={slot.id === value.slotId ? 'primary' : 'secondary'}
            className="!h-8 !px-3 !text-14"
            aria-pressed={slot.id === value.slotId}
            onClick={() => onChange({ date: value.date, slotId: slot.id })}
          >
            {slot.start} – {slot.end} МСК
          </Button>
        ))}
      </div>
      <Popover
        open={open}
        onClose={() => setOpen(false)}
        anchorRef={anchor}
        id={id}
        label="Календарь доставки"
        flip
        className="w-[330px]"
      >
        <div className="mb-3 flex items-center justify-between gap-2">
          <h3 className="text-14 font-medium capitalize" aria-live="polite">
            {month.toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' })}
          </h3>
          <div className="flex gap-1">
            <IconButton
              label="Предыдущий месяц"
              size="sm"
              disabled={monthKey(month) <= monthKey(parseDate(days[0].date))}
              onClick={() => moveMonth(-1)}
            >
              <ChevronLeft aria-hidden="true" className="size-4" />
            </IconButton>
            <IconButton
              label="Следующий месяц"
              size="sm"
              disabled={monthKey(month) >= monthKey(parseDate(days[days.length - 1].date))}
              onClick={() => moveMonth(1)}
            >
              <ChevronRight aria-hidden="true" className="size-4" />
            </IconButton>
          </div>
        </div>
        <div className="grid grid-cols-7 gap-y-1 text-center text-12">
          {['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'].map((day) => (
            <span key={day} className="py-2 text-ink-secondary">
              {day}
            </span>
          ))}
          {calendarDays.map((date) => {
            const key = dateKey(date);
            const day = days.find((item) => item.date === key);
            const available = !!day?.slots.length;
            const selected = key === value.date;
            return (
              <button
                key={key}
                type="button"
                disabled={!available}
                aria-label={fullDate(key)}
                aria-pressed={selected}
                onClick={() => day && choose(day)}
                className={`relative mx-auto flex size-9 items-center justify-center rounded-full outline-none focus-visible:ring-2 focus-visible:ring-brand-green ${selected ? 'bg-brand-yellow font-semibold' : available ? 'hover:bg-surface-grey' : 'text-ink-secondary/40'} ${date.getMonth() !== month.getMonth() ? 'opacity-50' : ''}`}
              >
                {date.getDate()}
                {available && !selected && (
                  <span
                    aria-hidden="true"
                    className="absolute bottom-0.5 size-1 rounded-full bg-brand-yellow"
                  />
                )}
              </button>
            );
          })}
        </div>
      </Popover>
    </div>
  );
}
