import { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import { ChevronRight, MessageSquare, Paperclip, Search, Send } from 'lucide-react';

interface Message {
  id: number;
  text: string;
  sender: 'user' | 'seller';
  time: string;
}

interface Chat {
  id: number;
  orderId: string;
  sellerName: string;
  sellerAvatar: string;
  lastMessage: string;
  lastMessageTime: string;
  unreadCount: number;
  messages: Message[];
}

const chatsData: Chat[] = [
  {
    id: 1,
    orderId: '#12847',
    sellerName: 'Мебельный салон "Комфорт"',
    sellerAvatar: 'https://ui-avatars.com/api/?name=Комфорт&background=ef4444&color=fff&size=128',
    lastMessage: 'Ваш заказ готов к отправке',
    lastMessageTime: '10:30',
    unreadCount: 2,
    messages: [
      {
        id: 1,
        text: 'Здравствуйте! Когда будет доставлен мой заказ?',
        sender: 'user',
        time: '09:15',
      },
      {
        id: 2,
        text: 'Добрый день! Ваш заказ находится на складе и будет отправлен сегодня.',
        sender: 'seller',
        time: '09:45',
      },
      { id: 3, text: 'Отлично! А можно изменить адрес доставки?', sender: 'user', time: '10:00' },
      { id: 4, text: 'Конечно! Пожалуйста, укажите новый адрес.', sender: 'seller', time: '10:15' },
      { id: 5, text: 'Ваш заказ готов к отправке', sender: 'seller', time: '10:30' },
    ],
  },
  {
    id: 2,
    orderId: '#12756',
    sellerName: 'Магазин "Уют"',
    sellerAvatar: 'https://ui-avatars.com/api/?name=Уют&background=10b981&color=fff&size=128',
    lastMessage: 'Спасибо за покупку!',
    lastMessageTime: 'Вчера',
    unreadCount: 0,
    messages: [
      {
        id: 1,
        text: 'Здравствуйте! Получил заказ, все отлично!',
        sender: 'user',
        time: 'Вчера 14:20',
      },
      {
        id: 2,
        text: 'Рады, что вам понравилось! Спасибо за покупку!',
        sender: 'seller',
        time: 'Вчера 14:35',
      },
    ],
  },
  {
    id: 3,
    orderId: '#12623',
    sellerName: 'Салон "Элегант"',
    sellerAvatar: 'https://ui-avatars.com/api/?name=Элегант&background=3b82f6&color=fff&size=128',
    lastMessage: 'Можем предложить скидку 10%',
    lastMessageTime: '2 дня назад',
    unreadCount: 1,
    messages: [
      {
        id: 1,
        text: 'Добрый день! Интересует диван из вашего каталога.',
        sender: 'user',
        time: '2 дня назад 11:00',
      },
      {
        id: 2,
        text: 'Здравствуйте! С удовольствием расскажем о нем подробнее.',
        sender: 'seller',
        time: '2 дня назад 11:20',
      },
      { id: 3, text: 'Можем предложить скидку 10%', sender: 'seller', time: '2 дня назад 15:45' },
    ],
  },
];

export default function Chats() {
  const location = useLocation();
  const [chats] = useState<Chat[]>(chatsData);
  const [activeChat, setActiveChat] = useState<Chat | null>(null);
  const [messageText, setMessageText] = useState('');

  useEffect(() => {
    // Если передан orderId через state, открываем соответствующий чат
    if (location.state?.orderId) {
      const chat = chatsData.find((c) => c.orderId === location.state.orderId);
      if (chat) {
        setActiveChat(chat);
      }
    } else {
      // По умолчанию открываем первый чат
      setActiveChat(chatsData[0]);
    }
  }, [location.state]);

  usePageSeo({
    title: buildTitle('Чаты с продавцами'),
    description: 'Чаты поддержки в интернет-магазине Светофор-Мебель.',
    image: '/logo.png',
    robots: 'noindex, follow',
    open_graph_title: buildTitle('Чаты с продавцами'),
    locale: 'ru_RU',
  });

  const handleSendMessage = () => {
    if (!messageText.trim() || !activeChat) return;

    const newMessage: Message = {
      id: activeChat.messages.length + 1,
      text: messageText,
      sender: 'user',
      time: new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }),
    };

    const updatedChat = {
      ...activeChat,
      messages: [...activeChat.messages, newMessage],
    };

    setActiveChat(updatedChat);
    setMessageText('');
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSendMessage();
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      <div className="flex-1 flex flex-col">
        <div className="max-w-7xl w-full mx-auto px-4 py-6">
          {/* Breadcrumbs */}
          <div className="flex items-center gap-2 text-sm mb-4">
            <Link to="/" className="text-gray-600 hover:text-red-600 cursor-pointer">
              Главная
            </Link>
            <ChevronRight className="size-[1em] text-gray-400" />
            <Link to="/account" className="text-gray-600 hover:text-red-600 cursor-pointer">
              Профиль
            </Link>
            <ChevronRight className="size-[1em] text-gray-400" />
            <span className="text-gray-900">Чаты</span>
          </div>

          <h1 className="text-4xl font-bold mb-6">Чаты с продавцами</h1>

          {/* Chat Interface */}
          <div
            className="bg-white rounded-3xl shadow-sm overflow-hidden flex"
            style={{ height: 'calc(100vh - 300px)', minHeight: '500px' }}
          >
            {/* Chat List */}
            <div className="w-80 border-r border-gray-100 flex flex-col">
              <div className="p-4 border-b border-gray-100">
                <div className="relative">
                  <input
                    type="text"
                    placeholder="Поиск чатов..."
                    className="w-full pl-10 pr-4 py-2.5 bg-gray-50 border-0 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500/20"
                  />
                  <Search className="size-[1em] absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                </div>
              </div>

              <div className="flex-1 overflow-y-auto">
                {chats.map((chat) => (
                  <div
                    key={chat.id}
                    onClick={() => setActiveChat(chat)}
                    className={`p-4 border-b border-gray-50 cursor-pointer transition-all ${
                      activeChat?.id === chat.id ? 'bg-red-50/50' : 'hover:bg-gray-50'
                    }`}
                  >
                    <div className="flex items-start gap-3">
                      <img
                        src={chat.sellerAvatar}
                        alt={chat.sellerName}
                        className="w-12 h-12 rounded-full flex-shrink-0"
                      />
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between mb-1">
                          <h3 className="font-semibold text-sm truncate">{chat.sellerName}</h3>
                          {chat.unreadCount > 0 && activeChat?.id !== chat.id && (
                            <span className="bg-red-600 text-white text-xs px-2 py-0.5 rounded-full flex-shrink-0">
                              {chat.unreadCount}
                            </span>
                          )}
                        </div>
                        <p className="text-xs text-gray-500 mb-1">Заказ {chat.orderId}</p>
                        <p className="text-sm text-gray-600 truncate">{chat.lastMessage}</p>
                        <p className="text-xs text-gray-400 mt-1">{chat.lastMessageTime}</p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Chat Window */}
            {activeChat ? (
              <div className="flex-1 flex flex-col">
                {/* Chat Header */}
                <div className="p-5 border-b border-gray-100 flex items-center gap-3">
                  <img
                    src={activeChat.sellerAvatar}
                    alt={activeChat.sellerName}
                    className="w-12 h-12 rounded-full"
                  />
                  <div className="flex-1">
                    <h2 className="font-bold text-lg">{activeChat.sellerName}</h2>
                    <Link
                      to="/orders"
                      className="text-sm text-red-600 hover:text-red-700 cursor-pointer hover:underline"
                    >
                      Заказ {activeChat.orderId}
                    </Link>
                  </div>
                </div>

                {/* Messages */}
                <div className="flex-1 overflow-y-auto p-6 space-y-4">
                  {activeChat.messages.map((message) => (
                    <div
                      key={message.id}
                      className={`flex ${message.sender === 'user' ? 'justify-end' : 'justify-start'}`}
                    >
                      <div
                        className={`max-w-md px-5 py-3 rounded-2xl ${
                          message.sender === 'user'
                            ? 'bg-gradient-to-br from-red-500 to-red-600 text-white shadow-sm'
                            : 'bg-gray-100 text-gray-900'
                        }`}
                      >
                        <p className="text-sm leading-relaxed">{message.text}</p>
                        <p
                          className={`text-xs mt-1.5 ${
                            message.sender === 'user' ? 'text-red-100' : 'text-gray-500'
                          }`}
                        >
                          {message.time}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>

                {/* Message Input */}
                <div className="p-5 border-t border-gray-100">
                  <div className="flex gap-3 items-end">
                    <button className="w-11 h-11 flex items-center justify-center text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all cursor-pointer flex-shrink-0">
                      <Paperclip className="size-[1em] text-xl" />
                    </button>
                    <input
                      type="text"
                      value={messageText}
                      onChange={(e) => setMessageText(e.target.value)}
                      onKeyPress={handleKeyPress}
                      placeholder="Введите сообщение..."
                      className="flex-1 px-5 py-3 bg-gray-50 border-0 rounded-2xl focus:outline-none focus:ring-2 focus:ring-red-500/20"
                    />
                    <button
                      onClick={handleSendMessage}
                      disabled={!messageText.trim()}
                      className="w-11 h-11 bg-red-600 text-white rounded-xl font-medium hover:bg-red-700 transition-all disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center justify-center cursor-pointer flex-shrink-0"
                    >
                      <Send className="size-[1em] text-lg" fill="currentColor" />
                    </button>
                  </div>
                </div>
              </div>
            ) : (
              <div className="flex-1 flex items-center justify-center">
                <div className="text-center">
                  <div className="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <MessageSquare className="size-[1em] text-5xl text-gray-400" />
                  </div>
                  <h3 className="text-xl font-bold text-gray-900 mb-2">Выберите чат</h3>
                  <p className="text-gray-600">Выберите чат из списка слева</p>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
