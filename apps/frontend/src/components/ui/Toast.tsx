import { useEffect } from 'react';
import Icon from './icons/Icon';
import { CircleCheck, TriangleAlert, X } from 'lucide-react';

interface ToastProps {
  message: string;
  type: 'success' | 'error';
  isVisible: boolean;
  onClose: () => void;
  duration?: number;
  action?: { label: string; onClick: () => void };
}

export default function Toast({
  message,
  type,
  isVisible,
  onClose,
  duration = 4000,
  action,
}: ToastProps) {
  useEffect(() => {
    if (isVisible && duration > 0) {
      const timer = setTimeout(() => {
        onClose();
      }, duration);
      return () => clearTimeout(timer);
    }
  }, [isVisible, duration, onClose]);

  if (!isVisible) return null;

  const bgColor = type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
  const textColor = type === 'success' ? 'text-green-700' : 'text-red-700';
  const icon = type === 'success' ? CircleCheck : TriangleAlert;

  return (
    <div className="fixed top-4 right-4 z-[9999] max-w-[calc(100vw-32px)] animate-[slideIn_0.3s_ease-out]">
      <div
        className={`${bgColor} ${textColor} border-2 rounded-lg px-4 py-3 shadow-lg flex flex-wrap items-center gap-3 min-w-[min(300px,calc(100vw-32px))] max-w-[500px]`}
      >
        <Icon name={icon} className="size-[1em] text-2xl flex-shrink-0" />
        <p className="flex-1 text-sm font-medium">{message}</p>
        {action && (
          <button
            type="button"
            onClick={action.onClick}
            className="text-sm font-semibold underline"
          >
            {action.label}
          </button>
        )}
        <button
          onClick={onClose}
          className={`${textColor} hover:opacity-70 transition-opacity flex-shrink-0`}
        >
          <X className="size-[1em] text-xl" />
        </button>
      </div>
      <style>{`
        @keyframes slideIn {
          from {
            transform: translateX(100%);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
      `}</style>
    </div>
  );
}
