import { useState, useEffect } from 'react';
import { api } from '../../../lib/api';
import Toast from '../../../components/ui/Toast';

export default function Newsletter() {
  const [email, setEmail] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [emailError, setEmailError] = useState<string | null>(null);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);
  const [isFocused, setIsFocused] = useState(false);

  // Валидация email на клиенте
  const validateEmail = (emailValue: string): boolean => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailValue.trim()) {
      setEmailError('Пожалуйста, введите email');
      return false;
    }
    if (!emailRegex.test(emailValue.trim())) {
      setEmailError('Введите корректный email адрес');
      return false;
    }
    setEmailError(null);
    return true;
  };

  // Очистка ошибки при изменении email
  useEffect(() => {
    if (email && emailError) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (emailRegex.test(email.trim())) {
        setEmailError(null);
      }
    }
  }, [email, emailError]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setEmailError(null);
    setIsSuccess(false);

    // Валидация на клиенте
    if (!validateEmail(email)) {
      return;
    }

    setIsLoading(true);

    try {
      const response = await api.newsletter.subscribe(email.trim());
      setIsSuccess(true);
      setEmail('');
      setToast({ 
        type: 'success', 
        message: response.message || 'Вы успешно подписались на рассылку!' 
      });
      
      // Сброс состояния успеха через 3 секунды
      setTimeout(() => {
        setIsSuccess(false);
      }, 3000);
    } catch (error: any) {
      const errorMessage = error?.data?.message || 
                          error?.data?.errors?.email?.[0] || 
                          'Произошла ошибка. Попробуйте еще раз.';
      setEmailError(errorMessage);
      setToast({ 
        type: 'error', 
        message: errorMessage 
      });
    } finally {
      setIsLoading(false);
    }
  };

  const handleCloseToast = () => {
    setToast(null);
  };

  return (
    <>
      <section className="px-6 lg:px-12 py-16 relative overflow-hidden">
        <div className="max-w-7xl mx-auto">
          <div className="bg-gradient-to-r from-red-600 to-yellow-500 rounded-3xl p-8 md:p-12 text-center relative overflow-hidden">
            {/* Декоративные элементы */}
            <div className="absolute top-0 left-0 w-full h-full opacity-10">
              <div className="absolute top-10 left-10 w-32 h-32 bg-white rounded-full blur-3xl"></div>
              <div className="absolute bottom-10 right-10 w-40 h-40 bg-white rounded-full blur-3xl"></div>
            </div>

            <div className="relative z-10">
              {/* Иконка */}
              <div className="mb-6 flex justify-center">
                <div className={`w-16 h-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center transition-all duration-500 ${
                  isSuccess ? 'scale-110 rotate-360' : ''
                }`}>
                  <i className={`text-3xl text-white ${
                    isSuccess ? 'ri-checkbox-circle-fill' : 'ri-mail-send-line'
                  }`}></i>
                </div>
              </div>

              <h2 className="text-3xl md:text-4xl font-bold text-white mb-4">
                {isSuccess ? 'Спасибо за подписку!' : 'Подпишитесь на рассылку'}
              </h2>
              
              <p className="text-white/90 text-lg mb-8 max-w-2xl mx-auto">
                {isSuccess 
                  ? 'Мы отправим вам эксклюзивные предложения и новости на указанный email'
                  : 'Получайте эксклюзивные предложения, новости о новинках и советы по дизайну интерьера'
                }
              </p>
              
              {!isSuccess && (
                <form onSubmit={handleSubmit} className="max-w-md mx-auto">
                  <div className="space-y-3">
                    <div className="relative">
                      <div className={`flex gap-3 transition-all duration-300 ${
                        isFocused ? 'scale-[1.02]' : ''
                      }`}>
                        <div className="flex-1 relative">
                          <i className="ri-mail-line absolute left-5 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none"></i>
                          <input
                            type="email"
                            placeholder="Ваш email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            onFocus={() => setIsFocused(true)}
                            onBlur={() => setIsFocused(false)}
                            className={`w-full pl-12 pr-6 py-4 rounded-full text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white transition-all duration-200 ${
                              emailError ? 'ring-2 ring-red-300 focus:ring-red-300' : ''
                            } ${isLoading ? 'opacity-70' : ''}`}
                            required
                            disabled={isLoading}
                          />
                        </div>
                        <button
                          type="submit"
                          disabled={isLoading || !email.trim()}
                          className={`bg-white text-red-600 px-6 md:px-8 py-4 rounded-full font-semibold hover:bg-gray-100 transition-all duration-200 cursor-pointer whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 min-w-[140px] justify-center ${
                            isLoading ? 'pointer-events-none' : ''
                          }`}
                        >
                          {isLoading ? (
                            <>
                              <i className="ri-loader-4-line animate-spin text-lg"></i>
                              <span className="hidden md:inline">Отправка...</span>
                            </>
                          ) : (
                            <>
                              <i className="ri-send-plane-fill text-lg"></i>
                              <span>Подписаться</span>
                            </>
                          )}
                        </button>
                      </div>
                      
                      {/* Сообщение об ошибке */}
                      {emailError && (
                        <div className="mt-2 text-left pl-5 animate-[slideDown_0.2s_ease-out]">
                          <div className="flex items-center gap-2 text-red-100 text-sm">
                            <i className="ri-error-warning-line"></i>
                            <span>{emailError}</span>
                          </div>
                        </div>
                      )}
                    </div>
                  </div>
                </form>
              )}

              {isSuccess && (
                <div className="max-w-md mx-auto animate-[fadeInUp_0.5s_ease-out]">
                  <div className="bg-white/20 backdrop-blur-sm rounded-2xl p-6 border border-white/30">
                    <div className="flex items-center justify-center gap-3 text-white">
                      <i className="ri-checkbox-circle-fill text-3xl"></i>
                      <p className="text-lg font-medium">Подписка оформлена!</p>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>

        <style>{`
          @keyframes slideDown {
            from {
              opacity: 0;
              transform: translateY(-10px);
            }
            to {
              opacity: 1;
              transform: translateY(0);
            }
          }
          @keyframes fadeInUp {
            from {
              opacity: 0;
              transform: translateY(20px);
            }
            to {
              opacity: 1;
              transform: translateY(0);
            }
          }
        `}</style>
      </section>

      {/* Toast уведомление */}
      <Toast
        message={toast?.message || ''}
        type={toast?.type || 'success'}
        isVisible={!!toast}
        onClose={handleCloseToast}
        duration={5000}
      />
    </>
  );
}
