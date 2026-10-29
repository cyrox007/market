import type { ReactNode } from 'react';
import { X } from 'lucide-react';
import { useMediaMatch } from '../../../hooks/useMediaQuery';
import Sheet from './Sheet';

interface ModalProps {
  open: boolean;
  /** Крестик, Esc, фон, «Назад». Состояние open меняет вызывающий */
  onClose: () => void;
  title: ReactNode;
  footer?: ReactNode;
  className?: string;
  children: ReactNode;
}

/** 768 и уже — шторка снизу (решение 13, market-docs/56) */
const NARROW = '(max-width: 768px)';

/** Окно: шире 768 по центру, уже — шторка снизу; поведение общее со Sheet (market-docs/57) */
export default function Modal({ onClose, ...rest }: ModalProps) {
  const narrow = useMediaMatch(NARROW);
  return (
    <Sheet
      {...rest}
      onClose={onClose}
      side={narrow ? 'bottom' : 'center'}
      headerAction={
        <button
          type="button"
          aria-label="Закрыть"
          onClick={onClose}
          className="-m-2 shrink-0 rounded-pill p-2 text-ink transition-colors hover:text-ink-secondary"
        >
          <X className="size-6" />
        </button>
      }
    />
  );
}
