import { useEffect } from 'react';
import Icon from './icons/Icon';
import { CircleCheck, TriangleAlert, X } from 'lucide-react';

interface ToastProps {
  message: string;
  type: 'success' | 'error';
  isVisible: boolean;
  onClose: () => void;
  duration?: number;
}

export default function Toast({ message, type, isVisible, onClose, duration = 4000 }: ToastProps) {
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
    <div className="fixed top-4 right-4 z-[9999] animate-[slideIn_0.3s_ease-out]">
      <div className={`${bgColor} ${textColor} border-2 rounded-lg px-4 py-3 shadow-lg flex items-center gap-3 min-w-[300px] max-w-[500px]`}>
        <Icon name={icon} className="size-[1em] text-2xl flex-shrink-0" />
        <p className="flex-1 text-sm font-medium">{message}</p>
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


