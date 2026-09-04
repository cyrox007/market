import { useState } from 'react';
import { api } from '../../lib/api';
import Toast from '../ui/Toast';
import { Star, X } from 'lucide-react';

interface ReviewModalProps {
  productId: number;
  productName: string;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export default function ReviewModal({
  productId,
  productName,
  isOpen,
  onClose,
  onSuccess,
}: ReviewModalProps) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!name.trim()) {
      const errorMsg = 'Пожалуйста, укажите ваше имя';
      setError(errorMsg);
      setToast({ message: errorMsg, type: 'error' });
      return;
    }

    if (comment.trim().length < 10) {
      const errorMsg = 'Комментарий должен содержать минимум 10 символов';
      setError(errorMsg);
      setToast({ message: errorMsg, type: 'error' });
      return;
    }

    setIsSubmitting(true);

    try {
      await api.reviews.create(productId, {
        name: name.trim(),
        email: email.trim() || undefined,
        rating,
        comment: comment.trim(),
      });

      // Сброс формы
      setName('');
      setEmail('');
      setRating(5);
      setComment('');
      setError(null);

      // Показываем уведомление об успехе
      setToast({
        message: 'Спасибо! Ваш отзыв отправлен на модерацию и будет опубликован после проверки.',
        type: 'success',
      });

      // Закрываем модалку через небольшую задержку, чтобы пользователь увидел уведомление
      setTimeout(() => {
        onSuccess();
        onClose();
      }, 1500);
    } catch (err: any) {
      const errorMsg = err.message || 'Произошла ошибка при отправке отзыва. Попробуйте позже.';
      setError(errorMsg);
      setToast({ message: errorMsg, type: 'error' });
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleClose = () => {
    if (!isSubmitting) {
      setName('');
      setEmail('');
      setRating(5);
      setComment('');
      setError(null);
      onClose();
    }
  };

  if (!isOpen) return null;

  return (
    <>
      <Toast
        message={toast?.message || ''}
        type={toast?.type || 'success'}
        isVisible={!!toast}
        onClose={() => setToast(null)}
        duration={toast?.type === 'success' ? 5000 : 4000}
      />
      <div
        className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50"
        onClick={handleClose}
      >
        <div
          className="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
          onClick={(e) => e.stopPropagation()}
        >
          <div className="p-6">
            {/* Header */}
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-2xl font-bold">Написать отзыв</h2>
              <button
                onClick={handleClose}
                disabled={isSubmitting}
                className="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-gray-700 disabled:opacity-50"
              >
                <X className="size-[1em] text-2xl" />
              </button>
            </div>

            <p className="text-gray-600 mb-6">
              Товар: <span className="font-semibold">{productName}</span>
            </p>

            {/* Form */}
            <form onSubmit={handleSubmit} className="space-y-5">
              {/* Name */}
              <div>
                <label htmlFor="name" className="block text-sm font-medium mb-2">
                  Ваше имя <span className="text-red-600">*</span>
                </label>
                <input
                  type="text"
                  id="name"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                  disabled={isSubmitting}
                  className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-600 focus:border-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
                  placeholder="Введите ваше имя"
                />
              </div>

              {/* Email */}
              <div>
                <label htmlFor="email" className="block text-sm font-medium mb-2">
                  Email (необязательно)
                </label>
                <input
                  type="email"
                  id="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  disabled={isSubmitting}
                  className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-600 focus:border-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
                  placeholder="your@email.com"
                />
              </div>

              {/* Rating */}
              <div>
                <label className="block text-sm font-medium mb-2">
                  Оценка <span className="text-red-600">*</span>
                </label>
                <div className="flex gap-2">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <button
                      key={star}
                      type="button"
                      onClick={() => setRating(star)}
                      disabled={isSubmitting}
                      className={`text-3xl transition-all ${
                        star <= rating ? 'text-yellow-500' : 'text-gray-300'
                      } hover:scale-110 disabled:opacity-50 disabled:cursor-not-allowed`}
                    >
                      <Star
                        className="size-[1em]"
                        fill={star <= rating ? 'currentColor' : 'none'}
                      />
                    </button>
                  ))}
                </div>
              </div>

              {/* Comment */}
              <div>
                <label htmlFor="comment" className="block text-sm font-medium mb-2">
                  Комментарий <span className="text-red-600">*</span>
                </label>
                <textarea
                  id="comment"
                  value={comment}
                  onChange={(e) => setComment(e.target.value)}
                  required
                  minLength={10}
                  disabled={isSubmitting}
                  rows={6}
                  className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-600 focus:border-red-600 disabled:opacity-50 disabled:cursor-not-allowed resize-none"
                  placeholder="Расскажите о вашем опыте использования товара (минимум 10 символов)"
                />
                <p className="text-xs text-gray-500 mt-1">{comment.length} / 5000 символов</p>
              </div>

              {/* Error */}
              {error && (
                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                  {error}
                </div>
              )}

              {/* Buttons */}
              <div className="flex gap-3 pt-4">
                <button
                  type="button"
                  onClick={handleClose}
                  disabled={isSubmitting}
                  className="flex-1 px-6 py-3 border border-gray-300 rounded-lg font-medium hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Отмена
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting || !name.trim() || comment.trim().length < 10}
                  className="flex-1 px-6 py-3 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  {isSubmitting ? 'Отправка...' : 'Отправить отзыв'}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </>
  );
}
