import { useState } from 'react';
import { Button, Input, Modal } from '../../components/ui/primitives';

/** Окно страницы товара (market-docs/57, задача 02). Данные выдуманные */
export default function ModalDemo() {
  const [open, setOpen] = useState(false);
  return (
    <section className="border-t border-surface-border pt-8">
      <h2 className="text-24 font-semibold text-ink">Окно</h2>
      <p className="mt-1 max-w-[70ch] text-14 text-ink-secondary">
        Шире 768 — по центру, 768 и уже — шторка снизу. Закрывается крестиком, Esc, фоном и «Назад».
        Макет — Figma 595:40888.
      </p>
      <div className="mt-6">
        <Button variant="secondary" size="sm" shape="rounded" onClick={() => setOpen(true)}>
          Купить в 1 клик
        </Button>
      </div>
      <Modal open={open} onClose={() => setOpen(false)} title="Купить в один клик">
        <div className="flex flex-col gap-4">
          <Input aria-label="ФИО" placeholder="ФИО" tone="outline" shape="rounded" />
          <Input
            aria-label="Телефон"
            placeholder="+7 (___) ___-__-__"
            tone="outline"
            shape="rounded"
          />
          <Button onClick={() => setOpen(false)}>Оформить заказ</Button>
        </div>
      </Modal>
    </section>
  );
}
