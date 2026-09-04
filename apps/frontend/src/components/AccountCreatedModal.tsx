import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Check, CircleCheck, Mail } from 'lucide-react';

interface AccountCreatedModalProps {
  isOpen: boolean;
  onClose: () => void;
  orderId?: number;
}

export default function AccountCreatedModal({
  isOpen,
  onClose,
  orderId,
}: AccountCreatedModalProps) {
  const navigate = useNavigate();

  // Блокируем скролл при открытии модалки
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }

    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  // Обработка закрытия по Escape
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        handleClose();
      }
    };

    if (isOpen) {
      document.addEventListener('keydown', handleEscape);
    }

    return () => {
      document.removeEventListener('keydown', handleEscape);
    };
  }, [isOpen]);

  const handleClose = () => {
    onClose();
    // Редирект на страницу заказов
    if (orderId) {
      navigate(`/orders/${orderId}`);
    } else {
      navigate('/orders');
    }
  };

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-50 overflow-y-auto"
      aria-labelledby="modal-title"
      role="dialog"
      aria-modal="true"
      onClick={(e) => {
        if (e.target === e.currentTarget) {
          handleClose();
        }
      }}
    >
      {/* Backdrop */}
      <div className="fixed inset-0 bg-black/50 transition-opacity"></div>

      {/* Modal */}
      <div className="flex min-h-full items-center justify-center p-4">
        <div
          className="bg-white rounded-2xl p-8 max-w-md w-full relative shadow-xl"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="text-center mb-6">
            <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
              <CircleCheck className="size-[1em] text-4xl text-green-600" />
            </div>
            <h3 className="text-2xl font-bold text-gray-900 mb-2">Аккаунт активирован!</h3>
            <p className="text-gray-600">Для вас автоматически создан личный кабинет</p>
          </div>

          {/* Content */}
          <div className="mb-6">
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
              <div className="flex items-start">
                <Mail className="size-[1em] text-blue-600 text-xl mr-3 mt-0.5" />
                <div className="flex-1">
                  <p className="text-sm text-blue-900 font-medium mb-1">
                    Данные для входа отправлены на email
                  </p>
                  <p className="text-xs text-blue-700">
                    Проверьте почту - там вы найдете логин и пароль для входа в личный кабинет
                  </p>
                </div>
              </div>
            </div>

            <div className="space-y-3 text-sm text-gray-600">
              <div className="flex items-center">
                <Check className="size-[1em] text-green-600 mr-2" />
                <span>Заказ успешно оформлен</span>
              </div>
              <div className="flex items-center">
                <Check className="size-[1em] text-green-600 mr-2" />
                <span>Личный кабинет готов к использованию</span>
              </div>
              <div className="flex items-center">
                <Check className="size-[1em] text-green-600 mr-2" />
                <span>Вы можете отслеживать заказы в личном кабинете</span>
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="flex flex-col gap-3">
            <button
              onClick={handleClose}
              className="w-full bg-red-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-red-700 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
            >
              Перейти к заказам
            </button>
            <button
              onClick={handleClose}
              className="w-full bg-gray-100 text-gray-700 py-3 px-4 rounded-lg font-medium hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
            >
              Закрыть
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
